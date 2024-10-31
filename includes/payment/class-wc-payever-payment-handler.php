<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Enum\PaymentMethod;
use Psr\Log\LoggerInterface;

class WC_Payever_Payment_Handler {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Checkout_Helper_Trait;
	use WC_Payever_Url_Helper_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Order_Totals_Trait;
	use WC_Payever_Api_Payment_Service_Trait;

	const LOCK_WAIT_SECONDS = 30;

	/**
	 * @var LockInterface
	 */
	private $locker;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct() {
		$this->locker = $this->get_api_wrapper()->get_locker();
		$this->logger = $this->get_api_wrapper()->get_logger();

		$this->get_wp_wrapper()->add_action(
			'payever_update_order',
			array( $this, 'update_order' ),
			10,
			2
		);
	}

	/**
	 * Retrieve payment and update order.
	 * @param WC_Order $order Order
	 * @param RetrievePaymentResultEntity $payment_result.
	 *
	 * @return string Url to redirect.
	 */
	public function handle_payment( WC_Order $order, RetrievePaymentResultEntity $payment_result ) {
		$payment_id = $payment_result->getId();

		// Redirect to the pending page
		if ( $this->get_api_payment_service()->is_new( $payment_result ) ||
			$this->get_api_payment_service()->is_in_process( $payment_result )
		) {
			$skip_message = $payment_result->getPaymentType() === PaymentMethod::METHOD_IVY ? true : null;
			return $this->get_url_helper()->get_pending_page_url( $order, $payment_id, $skip_message );
		}

		// Hide payment if the payment hasn't been successful
		if ( ! $this->get_api_payment_service()->is_successful( $payment_result ) ) {
			$payment_method = $payment_result->getPaymentType();
			$this->get_checkout_helper()->add_payever_hidden_method( $payment_method );

			$error = $this->get_helper()->is_santander( $payment_method )
				? __( 'Unfortunately, the application was not successful. Please choose another payment option to pay for your order.', 'payever-woocommerce-gateway' )
				: __( 'The payment was not successful. Please try again or choose another payment option.', 'payever-woocommerce-gateway' );

			$this->get_helper()->increase_order_stock( $order );

			$this->get_wp_wrapper()->wc_clear_notices();
			$this->get_wp_wrapper()->wc_add_notice( $error, 'error' );

			return $this->get_wp_wrapper()->wc_get_cart_url();
		}

		$this->locker->acquireLock( $payment_id, self::LOCK_WAIT_SECONDS );

		// Update order status and data
		$this->update_order( $order, $payment_result );

		// Update captured items qty
		if ( $this->get_api_payment_service()->is_paid( $payment_result ) ) {
			$this->allocate_totals( $order );
		}

		$this->locker->releaseLock( $payment_id );

		return $order->get_checkout_order_received_url();
	}

	/**
	 * Update order information.
	 *
	 * @param WC_Order $order
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function update_order( WC_Order $order, RetrievePaymentResultEntity $payment_result ) {
		$payment_id      = $payment_result->getId();
		$payment_method  = $payment_result->getPaymentType();
		$payment_details = $payment_result->getPaymentDetails();
		$pan_id          = $payment_details->getUsageText();

		// Update Payment ID
		$order->set_transaction_id( $payment_id );
		$order->update_meta_data(
			WC_Payever_Gateway::PAYEVER_PAYMENT_ID,
			$payment_id
		);

		// Add Santander Application Number
		if ( $this->get_helper()->is_santander( $payment_method ) ) {
			$order->update_meta_data(
				WC_Payever_Gateway::META_SANTANDER_APPLICATION_NUMBER,
				$payment_details->getApplicationNumber()
			);
		}

		// Add PAN ID
		if ( ! empty( $pan_id ) ) {
			$order->update_meta_data(
				WC_Payever_Gateway::META_PAN_ID,
				$pan_id
			);
		}

		$order_status = null;

		if ( $this->get_api_payment_service()->is_paid( $payment_result ) ) {
			$order_status = $this->get_helper()->get_shipping_status();
		}

		// Update order status
		$this->get_order_helper()->update_status( $order, $payment_result, $order_status );

		// Add order items meta if missing
		if ( ! $order->meta_exists( WC_Payever_Gateway::META_ORDER_ITEMS ) ) {
			$this->get_order_total_model()->get_order_items( $order );
		}

		// Woocommerce restocks products only on 'cancelled' status
		if ( $order->has_status( 'failed' ) ) {
			$this->get_wp_wrapper()->wc_maybe_increase_stock_levels( $order->get_id() );
		}

		if ( $this->get_api_payment_service()->is_successful( $payment_result ) ) {
			$this->get_helper()->reduce_order_stock( $order );
		}

		// Save meta data
		$order->save_meta_data();
	}

	/**
	 * Update captured items qty.
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function allocate_totals( WC_Order $order ) {
		$order_items = $this->get_order_total_model()->get_order_items( $order );
		foreach ( $order_items as $key => $order_item ) {
			if ( $order_item['captured_qty'] > 0 ) {
				// Don't process if it was allocated before
				return;
			}

			$order_items[ $key ]['captured_qty'] = $order_item['qty'];
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();
	}
}
