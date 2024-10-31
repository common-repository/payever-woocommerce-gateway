<?php

defined( 'ABSPATH' ) || exit;

class WC_Payever_Url_Helper {
	use WC_Payever_Helper_Trait;

	const TYPE_SUCCESS = 'success';
	const TYPE_FINISH = 'finish';
	const TYPE_ERROR = 'error';
	const TYPE_CANCEL = 'cancel';
	const TYPE_NOTICE = 'notice';
	const TYPE_PENDING = 'processing';
	const PENDING_CONFIRM = 'pending_page';
	const PARAM_TYPE = 'type';
	const PARAM_PAYMENT_ID = 'paymentId';
	const SKIP_MESSAGE = 'skip_message';

	/**
	 * Get the URL for the pending page after a successful order checkout.
	 *
	 * @param WC_Order $order The order object.
	 * @param string $payment_id Payment ID.
	 * @param null|boolean $skip_message Skip message on pending page
	 *
	 * @return string The URL for the pending page with the confirmation flag set.
	 */
	public function get_pending_page_url( WC_Order $order, $payment_id, $skip_message = null ) {
		return add_query_arg(
			array(
				self::PENDING_CONFIRM  => 'true',
				self::PARAM_PAYMENT_ID => $payment_id,
				self::SKIP_MESSAGE => $skip_message,
			),
			$order->get_checkout_order_received_url()
		);
	}

	/**
	 * Get the URL for the success page after a callback is generated for a successful order.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for the success page after generating the callback.
	 */
	public function get_success_url( WC_Order $order ) {
		return $this->generate_callback_url( $order, self::TYPE_SUCCESS );
	}

	/**
	 * Get the URL for the failure page after a failed order checkout.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for the failure page with the error callback URL generated.
	 */
	public function get_failure_url( WC_Order $order ) {
		return $this->generate_callback_url( $order, self::TYPE_ERROR );
	}

	/**
	 * Get the URL for canceling the order.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for canceling the order.
	 */
	public function get_cancel_url( WC_Order $order ) {
		return $this->generate_callback_url( $order, self::TYPE_CANCEL );
	}

	/**
	 * Get the URL for the notice page after a successful order callback.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for the notice page with the callback type set.
	 */
	public function get_notice_url( WC_Order $order ) {
		return $this->generate_callback_url( $order, self::TYPE_NOTICE );
	}

	/**
	 * Get the URL for pending status based on order.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for pending status.
	 */
	public function get_pending_url( WC_Order $order ) {
		$this->generate_callback_url( $order, self::TYPE_PENDING );
	}

	/**
	 * Get the URL for finishing the order process.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string The URL for finishing the order process.
	 */
	public function get_finish_url( WC_Order $order ) {
		$order_id = $order->get_id();

		return $this->generate_callback_url(
			$order,
			self::TYPE_FINISH,
			array(
				'reference' => $order_id,
				'token'     => $this->get_helper()->get_hash( $order_id ),
			)
		);
	}

	/**
	 * Check if the current callback type is a success URL.
	 *
	 * @return bool Returns true if the current callback type is a success URL, otherwise false.
	 */
	public function is_success_url() {
		return self::TYPE_SUCCESS === $this->get_current_callback_type();
	}

	/**
	 * Check if the current callback type is an error.
	 *
	 * @return bool Returns true if the current callback type is an error, false otherwise.
	 */
	public function is_failure_url() {
		return self::TYPE_ERROR === $this->get_current_callback_type();
	}

	/**
	 * Check if the current callback type is a cancel url.
	 *
	 * @return bool Returns true if the current callback type is a cancel url, else returns false.
	 */
	public function is_cancel_url() {
		return self::TYPE_CANCEL === $this->get_current_callback_type();
	}

	/**
	 * Checks if the current callback type is a notice URL.
	 *
	 * @return bool Returns true if the current callback type is a notice URL, otherwise returns false.
	 */
	public function is_notice_url() {
		return self::TYPE_NOTICE === $this->get_current_callback_type();
	}

	/**
	 * Check if the current callback type is 'pending'.
	 *
	 * @return bool True if the current callback type is 'pending', false otherwise.
	 */
	public function is_pending_url() {
		return self::TYPE_PENDING === $this->get_current_callback_type();
	}

	/**
	 * Check if the current callback type is "finish".
	 *
	 * @return bool Returns true if the current callback type is "finish", false otherwise.
	 */
	public function is_finish_url() {
		return self::TYPE_FINISH === $this->get_current_callback_type();
	}

	/**
	 * Check if the current page is the pending status page.
	 *
	 * @return bool True if the current page is the pending status page, false otherwise.
	 */
	public function is_pending_status_page() {
		return is_order_received_page() && isset( $_GET[ WC_Payever_Url_Helper::PENDING_CONFIRM ] );
	}

	public function show_pending_message() {
		return $this->is_pending_status_page() && !isset( $_GET[ WC_Payever_Url_Helper::SKIP_MESSAGE ] );
	}

	/**
	 * Get the current callback type from the URL parameters.
	 *
	 * @return string The current callback type or an empty string if not set.
	 */
	private function get_current_callback_type() {
		return isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // WPCS: input var ok, CSRF ok.
	}

	/**
	 * Generate callback url.
	 *
	 * @param WC_Order $order
	 * @param string $type
	 * @param array $params
	 * @return string
	 */
	private function generate_callback_url( WC_Order $order, $type, $params = array() ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		$params[ self::PARAM_TYPE ] = $type;
		if ( self::TYPE_CANCEL !== $type && self::TYPE_FINISH !== $type ) {
			$params[ self::PARAM_PAYMENT_ID ] = $payment_id ?: '--PAYMENT-ID--';
		}

		return add_query_arg(
			$params,
			WC()->api_request_url( strtolower( WC_Payever_Gateway::class ) )
		);
	}
}
