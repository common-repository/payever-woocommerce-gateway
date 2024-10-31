<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

interface WC_Payever_Notification_Handler_Interface {
	/**
	 * Determines the appropriate handler for a payment notification.
	 *
	 * @param string $handler The current handler
	 * @param RetrievePaymentResultEntity $payment The payment result entity
	 *
	 * @return string The handler to be used for the payment notification
	 */
	public function get_handler( $handler, RetrievePaymentResultEntity $payment );

	/**
	 * Handle notification.
	 *
	 * @param WC_Order $order
	 * @param NotificationResultEntity|RetrievePaymentResultEntity $payment
	 * @throws Exception
	 */
	public function handle(
		WC_Order $order,
		RetrievePaymentResultEntity $payment
	);
}
