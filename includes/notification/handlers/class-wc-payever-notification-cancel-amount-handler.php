<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

class WC_Payever_Notification_Cancel_Amount_Handler extends WC_Payever_Notification_Abstract_Handler implements WC_Payever_Notification_Handler_Interface {

	public function __construct() {
		$this->get_wp_wrapper()->add_filter(
			'payever_notification_get_handler',
			array( $this, 'get_handler' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'payever_notification_handler_cancel_amount',
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
			return 'payever_notification_handler_cancel_amount';
		}

		return $handler;
	}

	/**
	 * Handle cancel amount notification.
	 *
	 * @param WC_Order $order
	 * @param NotificationResultEntity|RetrievePaymentResultEntity $payment
	 * @throws Exception
	 */
	public function handle(
		WC_Order $order,
		RetrievePaymentResultEntity $payment
	) {
		$this->get_logger()->info(
			sprintf(
				'%s Handle cancel amount action. Order ID: %s. Payment ID: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$order->get_id(),
				$payment->getId()
			)
		);

		$cancel_amount   = $payment->getCancelAmount();
		$total_cancelled = $payment->getTotalCanceledAmount();

		// Register requested amount per items
		$amount = $cancel_amount;
		$this->get_order_total_model()->partial_cancel( $amount, $order );

		// Update order status
		if ( $order->get_total() - $total_cancelled <= 0.01 ) {
			$this->get_order_helper()->update_status( $order, $payment, null );
		}

		// Add success note
		$this->add_order_note( $order, $payment, $cancel_amount );

		$this->get_logger()->info(
			sprintf(
				'%s Cancelled: %s.',
				NotificationRequestProcessor::LOG_PREFIX,
				$cancel_amount
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
		$refunded_items = $payment->getRefundedItems();
		$capture_amount = $payment->getCaptureAmount();
		$refund_amount = $payment->getRefundAmount();
		$cancel_amount = $payment->getCancelAmount();

		return ( ! $captured_items || 0 === count( $captured_items ) ) &&
			( ! $refunded_items || 0 === count( $refunded_items ) ) &&
			! $capture_amount && ! $refund_amount &&
			( $cancel_amount && $cancel_amount > 0 );
	}
}
