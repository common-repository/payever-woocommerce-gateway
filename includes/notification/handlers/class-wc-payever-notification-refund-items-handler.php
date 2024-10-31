<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

class WC_Payever_Notification_Refund_Items_Handler extends WC_Payever_Notification_Abstract_Handler implements WC_Payever_Notification_Handler_Interface {

	public function __construct() {
		$this->get_wp_wrapper()->add_filter(
			'payever_notification_get_handler',
			array( $this, 'get_handler' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'payever_notification_handler_refund_items',
			array( $this, 'handle' ),
			10,
			2
		);
	}

	/**
	 * Determines the appropriate handler for a payment notification.
	 *
	 * @param string $handler The current handler
	 * @param RetrievePaymentResultEntity $payment The payment result entity
	 *
	 * @return string The handler to be used for the payment notification
	 */
	public function get_handler( $handler, RetrievePaymentResultEntity $payment ) {
		if ( $this->is_applicable( $payment ) ) {
			return 'payever_notification_handler_refund_items';
		}

		return $handler;
	}

	/**
	 * Handle refund items notification.
	 *
	 * @param WC_Order $order
	 * @param NotificationResultEntity|RetrievePaymentResultEntity $payment
	 * @throws Exception
	 */
	public function handle(
		WC_Order $order,
		RetrievePaymentResultEntity $payment
	) {
		$order_id = $order->get_id();

		$this->get_logger()->info(
			sprintf(
				'%s Handle refund items action. Order ID: %s. Payment ID: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$order_id,
				$payment->getId()
			)
		);

		$refund_amount  = $payment->getRefundAmount();
		$total_refunded = $payment->getTotalRefundedAmount();
		$items          = $payment->getRefundedItems();

		// Attention: `refunded_items` is historical, so update database. `refund_amount` is not historical.
		$processed_items = array();
		$amount          = 0;
		$order_items     = $this->get_order_total_model()->get_order_items( $order );

		$this->set_refunded_items( $order, $items, $order_items, $processed_items, $amount );
		$this->set_refunded_shipping_item( $order, $order_items, $total_refunded, $processed_items, $amount );
		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );

		// Update order status
		$payment->setStatus( Status::STATUS_REFUNDED );
		if ( $order->get_total() - $payment->getTotalRefundedAmount() <= 0.01 ) {
			$this->get_order_helper()->update_status( $order, $payment, null );
		}

		// Add order note
		$this->add_order_note(
			$order,
			$payment,
			$refund_amount,
			$processed_items
		);

		$this->get_logger()->info(
			sprintf(
				'%s Refunded items. Amount: %s. Transaction amount: %s. Items: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$amount,
				$refund_amount,
				wp_json_encode( $processed_items )
			)
		);
	}

	/**
	 * Set refunded items for the given order.
	 *
	 * @param WC_Order $order The order object.
	 * @param array $items The items to set as refunded.
	 * @param array    &$order_items The order items array to modify.
	 * @param array    &$processed_items The processed items array to add the refunded items.
	 * @param float    &$amount The total refunded amount.
	 *
	 * @return self Returns the current object.
	 */
	private function set_refunded_items(
		WC_Order $order,
		array $items,
		array &$order_items,
		array &$processed_items,
		&$amount
	) {
		foreach ( $items as $item ) {
			$item_id = $this->get_order_total_model()->get_order_item_id_by_identifier( $order, $item['identifier'] );
			if ( ! $item_id ) {
				continue;
			}

			$quantity = $item['quantity'];
			$amount  += $item['price'] * $quantity;
			foreach ( $order_items as $key => $order_item ) {
				if ( (string) $item_id === (string) $order_item['item_id'] ) {
					$order_items[ $key ]['refunded_qty'] = max( $order_item['refunded_qty'], $quantity );
					$processed_items[]                   = array(
						'name'     => $order_item['name'],
						'quantity' => $quantity,
					);

					break;
				}
			}
		}

		return $this;
	}

	/**
	 * Set the refunded shipping item for the given order.
	 *
	 * @param WC_Order $order The order object.
	 * @param array $order_items The array of order items.
	 * @param float $total_refunded_amount The total refunded amount for the order.
	 * @param array $processed_items The array of processed items.
	 * @param float $amount The current amount.
	 *
	 * @return $this Returns the current object.
	 */
	private function set_refunded_shipping_item(
		WC_Order $order,
		array &$order_items,
		$total_refunded_amount,
		array &$processed_items,
		&$amount
	) {
		$item = $this->get_order_total_model()->get_delivery_fee_item( $order );
		if ( ! $item ) {
			return $this;
		}

		// Calculate delivery fee
		$delivery_fee = $total_refunded_amount - $amount;
		if ( $delivery_fee >= 0.01 ) {
			$this->get_logger()->info(
				sprintf(
					'%s Calculated delivery fee: %s',
					NotificationRequestProcessor::LOG_PREFIX,
					$delivery_fee
				)
			);

			// Mark shipping refunded
			foreach ( $order_items as $key => $order_item ) {
				if ( 'shipping' === $order_item['type'] ) {
					$order_items[ $key ]['refunded_qty'] = 1;
					$processed_items[]                    = array(
						'name'     => $order_item['name'],
						'quantity' => 1,
					);

					$amount += $order_item['unit_price'];

					break;
				}
			}
		}

		return $this;
	}

	/**
	 * Check if the given payment is applicable for certain conditions.
	 *
	 * @param RetrievePaymentResultEntity $payment The payment entity to check.
	 *
	 * @return bool Returns true if the payment is applicable, false otherwise.
	 */
	private function is_applicable( RetrievePaymentResultEntity $payment ) {
		$status          = $payment->getStatus();
		$refunded_items  = $payment->getRefundedItems();
		$capture_amount  = $payment->getCaptureAmount();
		$cancel_amount   = $payment->getCancelAmount();

		return in_array( $status, array( Status::STATUS_REFUNDED, Status::STATUS_CANCELLED ) ) &&
			( ! $capture_amount && ! $cancel_amount ) &&
			( $refunded_items && count( $refunded_items ) > 0 );
	}
}
