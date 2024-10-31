<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

class WC_Payever_Notification_Refund_Amount_Handler extends WC_Payever_Notification_Abstract_Handler implements WC_Payever_Notification_Handler_Interface {

	public function __construct() {
		$this->get_wp_wrapper()->add_filter(
			'payever_notification_get_handler',
			array( $this, 'get_handler' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'payever_notification_handler_refund_amount',
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
			return 'payever_notification_handler_refund_amount';
		}

		return $handler;
	}

	/**
	 * Handle refund amount notification.
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
				'%s Handle refund amount action. Order ID: %s. Payment ID: %s',
				NotificationRequestProcessor::LOG_PREFIX,
				$order->get_id(),
				$payment->getId()
			)
		);

		if ( Status::STATUS_CANCELLED === $payment->getStatus() ) {
			$this->get_helper()->increase_order_stock( $order );
			$this->get_logger()->info('Order product inventory was increased.');
		}

		$refund_amount = $payment->getRefundAmount();

		// Register requested amount per items
		$amount = $refund_amount;
		$this->get_order_total_model()->partial_refund( $amount, $order );

		// Update order status
		$payment->setStatus( Status::STATUS_REFUNDED );

		if ( $order->get_total() - $payment->getTotalRefundedAmount() <= 0.01 ) {
			$this->get_order_helper()->update_status( $order, $payment, null );
		}

		// Add order note
		$this->add_order_note( $order, $payment, $refund_amount );

		$this->get_logger()->info(
			sprintf(
				'%s Refunded: %s.',
				NotificationRequestProcessor::LOG_PREFIX,
				$amount
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
		$status         = $payment->getStatus();
		$refunded_items = $payment->getRefundedItems();
		$capture_amount = $payment->getCaptureAmount();
		$refund_amount  = $payment->getRefundAmount();
		$cancel_amount  = $payment->getCancelAmount();

		return in_array( $status, array( Status::STATUS_REFUNDED, Status::STATUS_CANCELLED ) ) &&
			( ! $capture_amount && ! $cancel_amount ) &&
			( ! $refunded_items || 0 === count( $refunded_items ) ) && ( $refund_amount && $refund_amount > 0 );
	}
}
