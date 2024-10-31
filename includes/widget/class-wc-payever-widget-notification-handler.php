<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Payever\Sdk\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\Sdk\Payments\Notification\NotificationHandlerInterface;
use Payever\Sdk\Payments\Notification\NotificationResult;
use Psr\Log\LoggerInterface;

class WC_Payever_Widget_Notification_Handler implements NotificationHandlerInterface {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Api_Payment_Service_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Helper_Trait;

	/**
	 * @var LockInterface
	 */
	private $locker;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var WC_Payever_Widget_Success_Handler
	 */
	private $success_handler;

	/**
	 * @var WC_Payever_Notification_Handler
	 */
	private $notification_handler;

	public function __construct() {
		$this->locker               = $this->get_api_wrapper()->get_locker();
		$this->logger               = $this->get_api_wrapper()->get_logger();
		$this->success_handler      = new WC_Payever_Widget_Success_Handler();
		$this->notification_handler = new WC_Payever_Notification_Handler();
	}

	/**
	 * @inheritdoc
	 */
	public function handleNotification(
		NotificationRequestEntity $notification,
		NotificationResult $notificationResult
	) {
		$payment = $notification->getPayment();
		$payment_id = $payment->getId();
		$payment_result = $this->get_api_payment_service()->retrieve( $payment_id );
		$this->logger->debug(
			sprintf( 'Payment retrieved. %s', $payment_id ),
			$payment_result->toArray()
		);

		$payment_status = $payment->getStatus();
		$order = $this->get_order_helper()->get_order_by_payment_id( $payment_id );

		if ( ! $order && ! $this->get_api_payment_service()->is_successful( $payment ) ) {
			$notificationResult->addMessage(
				sprintf(
					'Skip handling payever payment status %s',
					$payment_status
				)
			);

			return;
		}

		if ( ! $order ) {
			$order = $this->success_handler->handle( $payment );
			if ( ! $order ) {
				$message = sprintf(
					'FE Notification handler: Order created is failed. Transaction id: %s.',
					$payment_id
				);

				$this->logger->critical( $message );
				$notificationResult->addError( $message );

				return;
			}

			$this->logger->info(
				sprintf( 'FE Notification handler: Order #%s created.', $order->get_id() )
			);
		}

		// Substitute reference to connect transaction with order properly
		$payment->setReference( $order->get_id() );
		$this->notification_handler->handleNotification( $notification, $notificationResult );
		$notificationResult->addMessage(
			sprintf( 'FE Notification handler: Order #%s has been processed.', $order->get_id() )
		);
	}
}
