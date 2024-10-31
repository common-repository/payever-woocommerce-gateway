<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Notification_Handler' ) ) {
	return;
}

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\Sdk\Payments\Notification\NotificationHandlerInterface;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;
use Payever\Sdk\Payments\Notification\NotificationResult;
use Payever\Sdk\Payments\Notification\MessageEntity\NotificationResultEntity;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @codeCoverageIgnore
 */
class WC_Payever_Notification_Handler implements NotificationHandlerInterface {
	use WC_Payever_Logger_Trait;
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Payment_Action_Wrapper_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Helper_Trait;

	/**
	 * @param NotificationRequestEntity $notification
	 * @param NotificationResult        $notificationResult
	 * @return void
	 */
	public function handleNotification(
		NotificationRequestEntity $notification,
		NotificationResult $notificationResult
	) {
		$payment        = $notification->getPayment();
		$payment_id     = $payment->getId();
		$payment_status = $payment->getStatus();
		$this->get_logger()->debug(
			'Processing notification',
			$notification->toArray()
		);

		$shipped_status = $this->get_helper()->get_shipping_status();
		$order_id       = (int) $payment->getReference();
		$order_status   = null;

		try {
			$order = $this->get_order_helper()->get_order_by_payment_id( $payment_id );
			if ( ! $order ) {
				$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
			}

			if ( ! $order ) {
				throw new \BadMethodCallException( 'Order not found. Payment ID: ' . $payment_id );
			}

			$order_id     = $order->get_id();
			$order_status = $order->get_status();

			if ( ! $this->validate( $order, $notification, $notificationResult, $payment_status ) ) {
				throw new \BadMethodCallException( 'Notification is not valid' );
			}

			$created_at = $notification->getCreatedAt()->getTimestamp();
			if ( $order->has_status( $shipped_status ) ) {
				$order->update_meta_data( WC_Payever_Gateway::META_NOTIFICATION_TIMESTAMP, $created_at );
				$order->save_meta_data();

				throw new \BadMethodCallException( 'Order has been shipped so status can not be updated' );
			}

			// User may not finish creating the order and restart the payment process.
			if ( $this->should_reject_if_expired_status( $order, $payment ) ) {
				throw new \BadMethodCallException(
					'Notification expire processing is skipped; reason: order already processed'
				);
			}

			// Handle capture/refund/cancel notification
			$this->process( $order, $payment );

			if ( $notification->getAction() ) {
				$this->get_payment_action_wrapper()->add_action(
					$order_id,
					$notification->getAction()->getUniqueIdentifier(),
					$notification->getAction()->getType(),
					$notification->getAction()->getAmount()
				);
			}

			$notificationResult
				->addMessage( __( 'Order status has been updated', 'payever-woocommerce-gateway' ) )
				->setOrderId( $order_id )
				->setPreviousOrderStatus( $order_status )
				->setCurrentOrderStatus( $this->wp_wrapper->wc_get_order( $order )->get_status() );

			$order->update_meta_data( WC_Payever_Gateway::META_NOTIFICATION_TIMESTAMP, $created_at );
			$order->save_meta_data();
		} catch ( \Exception $e ) {
			$notificationResult
				->addMessage( $e->getMessage() )
				->setOrderId( $order_id )
				->setCurrentOrderStatus( $order_status );
		}
	}

	/**
	 * @param WC_Order $order
	 * @param NotificationResultEntity|RetrievePaymentResultEntity $payment
	 * @return void
	 */
	private function process( WC_Order $order, RetrievePaymentResultEntity $payment ) {
		if (
			! empty( $payment->getCapturedItems() ) ||
			! empty( $payment->getRefundedItems() ) ||
			! empty( $payment->getCaptureAmount() ) ||
			! empty( $payment->getRefundAmount() ) ||
			! empty( $payment->getCancelAmount() )
		) {
			$this->get_logger()->info(
				sprintf(
					'%s Handle payment action. Order ID: %s. Payment ID: %s',
					NotificationRequestProcessor::LOG_PREFIX,
					$order->get_id(),
					$payment->getId()
				)
			);

			$handler = apply_filters( 'payever_notification_get_handler', null, $payment );
			if ( $handler ) {
				$this->get_logger()->debug(
					sprintf(
						'%s Handle payment action. Found handler "%s" to handle payment ID: %s',
						NotificationRequestProcessor::LOG_PREFIX,
						$handler,
						$payment->getId()
					)
				);

				$this->get_wp_wrapper()->do_action( $handler, $order, $payment );

				return;
			}
		}

		// Updating order data
		// @todo use hooks action
		( new WC_Payever_Payment_Handler() )->update_order( $order, $payment );
	}

	/**
	 * @param WC_Order $order
	 * @param NotificationRequestEntity $notification
	 * @param $notification_result
	 * @param $payment_status
	 *
	 * @return bool
	 */
	private function validate(
		WC_Order $order,
		NotificationRequestEntity $notification,
		$notification_result,
		$payment_status
	) {
		$order_id = $order->get_id();

		try {
			if ( Status::STATUS_NEW === $payment_status ) {
				throw new \BadMethodCallException(
					'Notification processing is skipped; reason: stalled new status'
				);
			}

			$created_at = $notification->getCreatedAt()->getTimestamp();
			if ( $this->should_reject_notification( $order, $created_at ) ) {
				throw new \BadMethodCallException(
					'Notification rejected: newer notification already processed'
				);
			}

			if ( ! $notification->getAction() ) {
				return true;
			}

			// Check if action was executed on plugin side.
			$payment_action = $this->get_payment_action_wrapper()->get_item(
				$order_id,
				$notification->getAction()->getUniqueIdentifier(),
				$notification->getAction()->getSource()
			);
			if ( $payment_action ) {
				throw new \BadMethodCallException( 'Notification rejected: notification already processed' );
			}

			return true;
		} catch ( \Exception $e ) {
			$notification_result
				->addMessage( $e->getMessage() )
				->setOrderId( $order_id )
				->setCurrentOrderStatus( $order->get_status() );

			return false;
		}
	}

	/**
	 * @param WC_Order $order
	 * @param RetrievePaymentResultEntity $payment
	 *
	 * @return bool
	 */
	private function should_reject_if_expired_status( WC_Order $order, RetrievePaymentResultEntity $payment ) {
		return in_array( $payment->getStatus(), array( Status::STATUS_DECLINED, Status::STATUS_FAILED ) )
			&& in_array( $payment->getSpecificStatus(), array( 'ORDER_EXPIRED', 'CHECKOUT_EXPIRED' ) )
			&& ! $order->has_status( 'pending' );
	}

	/**
	 * @param WC_Order $order
	 * @param int $notification_timestamp
	 *
	 * @return bool
	 */
	private function should_reject_notification( WC_Order $order, $notification_timestamp ) {
		$last_timestamp = (int) $order->get_meta( WC_Payever_Gateway::META_NOTIFICATION_TIMESTAMP );

		return $last_timestamp > $notification_timestamp;
	}
}
