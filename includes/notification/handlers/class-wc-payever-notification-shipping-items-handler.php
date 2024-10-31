<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

class WC_Payever_Notification_Shipping_Items_Handler extends WC_Payever_Notification_Abstract_Handler implements WC_Payever_Notification_Handler_Interface {

	public function __construct() {
		$this->get_wp_wrapper()->add_filter(
			'payever_notification_get_handler',
			array( $this, 'get_handler' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'payever_notification_handler_shipping_items',
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
			return 'payever_notification_handler_shipping_items';
		}

		return $handler;
	}

	/**
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
				'%s Handle shipping items action. Order ID: %s. Payment ID: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$order_id,
				$payment->getId()
			)
		);

		$capture_amount = $payment->getCaptureAmount();
		$total_captured = $payment->getTotalCapturedAmount();
		$items          = $payment->getCapturedItems();

		// Attention: `captured_items` is historical, so update database. `capture_amount` is not historical.
		$processed_items = array();
		$amount          = 0;
		$order_items     = $this->get_order_total_model()->get_order_items( $order );

		$this
			->set_captured_items( $order, $items, $order_items, $processed_items, $amount )
			->set_shipping_item( $order, $order_items, $total_captured, $processed_items, $amount );

		$order->update_meta_data(
			WC_Payever_Gateway::META_ORDER_ITEMS,
			$order_items
		);

		// Update order status
		$order_status = $this->get_order_helper()->get_order_status( Status::STATUS_PAID );
		if ( $order->get_total() - $total_captured <= 0.01 ) {
			$order_status = $this->get_helper()->get_shipping_status();
		}
		$this->get_order_helper()->update_status( $order, $payment, $order_status );

		// Add order note
		$this->add_order_note(
			$order,
			$payment,
			$capture_amount,
			$processed_items
		);

		$this->get_logger()->info(
			sprintf(
				'%s Captured items. Amount: %s. Transaction amount: %s. Items: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$amount,
				$capture_amount,
				wp_json_encode( $processed_items )
			),
			$items
		);
	}

	/**
	 * Sets the shipping item for a given order.
	 *
	 * @param WC_Order $order The order instance.
	 * @param array $order_items The reference to the order items array.
	 * @param float $total_captured_amount The total amount captured for the order.
	 * @param array $processed_items The reference to the processed items array.
	 * @param float $amount The reference to the order amount.
	 *
	 * @return $this The current object instance.
	 */
	private function set_shipping_item(
		WC_Order $order,
		array &$order_items,
		$total_captured_amount,
		&$processed_items,
		&$amount
	) {
		$item = $this->get_order_total_model()->get_delivery_fee_item( $order );
		if ( ! $item ) {
			return $this;
		}

		// Calculate delivery fee
		$delivery_fee = $total_captured_amount - $amount;
		if ( $delivery_fee >= 0.01 ) {
			$this->get_logger()->info(
				sprintf(
					'%s Calculated delivery fee: %s',
					NotificationRequestProcessor::LOG_PREFIX,
					$delivery_fee
				)
			);

			// Mark shipping captured
			foreach ( $order_items as $key => $order_item ) {
				if ( 'shipping' === $order_item['type'] ) {
					$order_items[ $key ]['captured_qty'] = 1;
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
	 * Sets the captured items for a given order.
	 *
	 * @param WC_Order $order The order instance.
	 * @param array $items The array of items to set as captured.
	 * @param array $order_items The reference to the order items array.
	 * @param array $processed_items The reference to the processed items array.
	 * @param float $amount The reference to the order amount.
	 *
	 * @return $this The current object instance.
	 */
	private function set_captured_items(
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
					$order_items[ $key ]['captured_qty'] = max( $order_item['captured_qty'], $quantity );
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
	 * Check if the given payment is applicable for certain conditions.
	 *
	 * @param RetrievePaymentResultEntity $payment The payment entity to check.
	 *
	 * @return bool Returns true if the payment is applicable, false otherwise.
	 */
	private function is_applicable( RetrievePaymentResultEntity $payment ) {
		$captured_items = $payment->getCapturedItems();
		$refund_amount  = $payment->getRefundAmount();
		$cancel_amount  = $payment->getCancelAmount();

		return ( ! $refund_amount && ! $cancel_amount ) && $captured_items && count( $captured_items ) > 0;
	}
}
