<?php

defined( 'ABSPATH' ) || exit;

class WC_Payever_Ajax {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Order_Totals_Trait;
	use WC_Payever_Api_Shipping_Goods_Service_Trait;
	use WC_Payever_Api_Refund_Service_Trait;
	use WC_Payever_Api_Wrapper_Trait;

	/**
	 * Add actions
	 */
	public function __construct() {
		$this->get_wp_wrapper()->add_action( 'wp_ajax_payever_capture_item', array( $this, 'capture_item' ) );
		$this->get_wp_wrapper()->add_action( 'wp_ajax_payever_cancel_item', array( $this, 'cancel_item' ) );
	}

	/**
	 * Payment capture action
	 *
	 * @return void
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	public function capture_item() {
		// @todo Add CSRF protection
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_ajax_payever_capture_item' ) ) {
			$this->get_api_wrapper()->get_logger()->error( __( 'Invalid wp_ajax_payever_capture_item nonce.', 'payever-woocommerce-gateway' ) );
			exit;
		}

		$order_id          = isset( $_POST['order_id'] ) ? absint( wc_clean( wp_unslash( $_POST['order_id'] ) ) ) : 0; // WPCS: input var ok, CSRF ok.
		$amount            = ! empty( $_POST['amount'] ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : false; // WPCS: input var ok, CSRF ok.
		$items             = isset( $_POST['items'] ) ? (array) wc_clean( wp_unslash( $_POST['items'] ) ) : array(); // WPCS: input var ok, CSRF ok.
		$comment           = isset( $_POST['comment'] ) ? wc_clean( wp_unslash( $_POST['comment'] ) ) : null; // WPCS: input var ok, CSRF ok.
		$tracking_number   = isset( $_POST['tracking_number'] ) ? wc_clean( wp_unslash( $_POST['tracking_number'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$tracking_url      = isset( $_POST['tracking_url'] ) ? wc_clean( wp_unslash( $_POST['tracking_url'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$shipping_provider = isset( $_POST['shipping_provider'] ) ? wc_clean( wp_unslash( $_POST['shipping_provider'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$shipping_date     = isset( $_POST['shipping_date'] ) ? wc_clean( wp_unslash( $_POST['shipping_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
		if ( empty( $items ) && ! empty( $amount ) ) {
			// Capture by amount
			$this->capture_by_amount(
				$order,
				$amount,
				$comment,
				$tracking_number,
				$tracking_url,
				$shipping_provider,
				$shipping_date
			);

			return;
		}

		// Capture by qty
		$this->capture_by_items(
			$order,
			$items,
			$comment,
			$tracking_number,
			$tracking_url,
			$shipping_provider,
			$shipping_date
		);
	}

	/**
	 * Capture by amount.
	 *
	 * @return void
	 */
	private function capture_by_amount(
		WC_Order $order,
		$amount,
		$comment,
		$tracking_number,
		$tracking_url,
		$shipping_provider,
		$shipping_date
	) {
		try {
			if ( ! $this->get_order_total_model()->is_allow_order_capture_by_amount( $order ) ) {
				throw new \UnexpectedValueException( 'This capture method not allowed.' );
			}

			$this->get_api_shipping_goods_service()->capture(
				$order,
				$amount,
				$comment,
				$tracking_number,
				$tracking_url,
				$shipping_provider,
				$shipping_date
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'error' => $e->getMessage(),
				)
			);
			return;
		}
	}

	/**
	 * Capture by items.
	 *
	 * @return void
	 */
	private function capture_by_items(
		WC_Order $order,
		array $items,
		$comment,
		$tracking_number,
		$tracking_url,
		$shipping_provider,
		$shipping_date
	) {
		try {
			if ( ! $this->get_order_total_model()->is_allow_order_capture_by_qty( $order ) ) {
				throw new \UnexpectedValueException( 'This capture method not allowed.' );
			}

			$this->validate_items_before_capture( $order, $items );
			$this->get_api_shipping_goods_service()->capture_items(
				$order,
				$items,
				$comment,
				$tracking_number,
				$tracking_url,
				$shipping_provider,
				$shipping_date
			);

			wp_send_json_success();
		} catch ( Exception $exception ) {
			wp_send_json_error( array( 'error' => $exception->getMessage() ) );
		}
	}

	/**
	 * Cancel action.
	 *
	 * @return void
	 */
	public function cancel_item() {
		// @todo Add CSRF protection
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_ajax_payever_cancel_item' ) ) {
			$this->get_api_wrapper()->get_logger()->error( __( 'Invalid wp_ajax_payever_cancel_item nonce.', 'payever-woocommerce-gateway' ) );
			exit;
		}

		$order_id   = isset( $_POST['order_id'] ) ? absint( wc_clean( wp_unslash( $_POST['order_id'] ) ) ) : 0; // WPCS: input var ok, CSRF ok.
		$items      = isset( $_POST['items'] ) ? (array) wc_clean( wp_unslash( $_POST['items'] ) ) : array(); // WPCS: input var ok, CSRF ok.
		$order      = $this->get_wp_wrapper()->wc_get_order( $order_id );

		try {
			$this->validate_items_before_cancel( $order, $items );
			$this->get_api_refund_service()->cancel_items(
				$order,
				$items
			);

			wp_send_json_success();
		} catch ( Exception $exception ) {
			wp_send_json_error( array( 'error' => $exception->getMessage() ) );
		}
	}

	/**
	 * Validate items qty and amount before capture.
	 *
	 * @param WC_Order $order
	 * @param array $items
	 * @throws InvalidArgumentException
	 * @throws UnexpectedValueException
	 */
	private function validate_items_before_capture( WC_Order $order, array $items ) {
		if ( 0 === count( $items ) ) {
			throw new \InvalidArgumentException(
				'Amount doesn\'t match, for cancel please use qty inputs inside each item.'
			);
		}

		$amount = 0;

		// Validate available quantities
		foreach ( $items as $item ) {
			$item_id    = $item['item_id'];
			$quantity   = $item['qty'];
			$order_item = $this->get_order_total_model()->get_order_item( $order, $item_id );
			if ( $quantity + $order_item['captured_qty'] > $order_item['qty'] ) {
				throw new \InvalidArgumentException( 'Qty is more than left qty.' );
			}

			// Calculate amount
			$amount += $quantity * $order_item['unit_price'];
		}

		// Validate available amount
		$totals = $this->get_order_total_model()->get_totals( $order );
		if ( $amount > $totals['available_capture'] ) {
			throw new \UnexpectedValueException( 'Capture amount is higher than order remaining amount.' );
		}
	}

	/**
	 * Validate items qty and amount before cancel.
	 *
	 * @param WC_Order $order
	 * @param array $items
	 * @throws InvalidArgumentException
	 * @throws UnexpectedValueException
	 */
	private function validate_items_before_cancel( WC_Order $order, array $items ) {
		if ( 0 === count( $items ) ) {
			throw new \InvalidArgumentException(
				'Amount doesn\'t match, for cancel please use qty inputs inside each item.'
			);
		}

		$amount = 0;

		// Validate available quantities
		foreach ( $items as $item ) {
			$item_id    = $item['item_id'];
			$quantity   = $item['qty'];
			$order_item = $this->get_order_total_model()->get_order_item( $order, $item_id );
			if ( $quantity + $order_item['cancelled_qty'] > $order_item['qty'] ) {
				throw new \InvalidArgumentException( 'Qty is more than left qty.' );
			}

			// Calculate amount
			$amount += $quantity * $order_item['unit_price'];
		}

		// Validate available amount
		$totals = $this->get_order_total_model()->get_totals( $order );
		if ( $amount > $totals['available_cancel'] ) {
			throw new \UnexpectedValueException( 'Cancel amount is higher than order remaining amount.' );
		}
	}
}
