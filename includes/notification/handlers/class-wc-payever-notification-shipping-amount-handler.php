<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

class WC_Payever_Notification_Shipping_Amount_Handler extends WC_Payever_Notification_Abstract_Handler implements WC_Payever_Notification_Handler_Interface {

	public function __construct() {
		$this->get_wp_wrapper()->add_filter(
			'payever_notification_get_handler',
			array( $this, 'get_handler' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'payever_notification_handler_shipping_amount',
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
			return 'payever_notification_handler_shipping_amount';
		}

		return $handler;
	}

	/**
	 * Handle shipping amount notification.
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
				'%s Handle shipping amount action. Order ID: %s. Payment ID: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$order_id,
				$payment->getId()
			)
		);

		$capture_amount = $payment->getCaptureAmount();

		// Register requested amount per items
		$amount = $capture_amount;
		$this->get_order_total_model()->partial_capture( $amount, $order );

		// Update order status
		$order_status = $this->get_order_helper()->get_order_status( Status::STATUS_PAID );
		if ( $order->get_total() - $payment->getTotalCapturedAmount() <= 0.01 ) {
			$order_status = $this->get_helper()->get_shipping_status();
		}
		$this->get_order_helper()->update_status( $order, $payment, $order_status );

		// Add order note
		$this->add_order_note( $order, $payment, $capture_amount );

		$this->get_logger()->info(
			sprintf(
				'%s Shipped: %s.',
				NotificationRequestProcessor::LOG_PREFIX,
				$capture_amount
			)
		);
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
		$capture_amount = $payment->getCaptureAmount();
		$refund_amount  = $payment->getRefundAmount();
		$cancel_amount  = $payment->getCancelAmount();

		return ( ! $refund_amount && ! $cancel_amount ) &&
			( ! $captured_items || 0 === count( $captured_items ) ) &&
			( $capture_amount && $capture_amount > 0 );
	}
}
