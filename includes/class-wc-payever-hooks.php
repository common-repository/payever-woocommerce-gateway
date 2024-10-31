<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\NumberUtil;

/**
 * WC_Payever_Hooks Class.
 */
class WC_Payever_Hooks {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->get_wp_wrapper()->add_filter( 'pewc_round', array( $this, 'round' ), 10, 2 );
		$this->get_wp_wrapper()->add_filter( 'pewc_format', array( $this, 'format' ), 10, 1 );
		$this->get_wp_wrapper()->add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			array( $this, 'handle_payment_id_query_var' ),
			10,
			2
		);
		$this->get_wp_wrapper()->add_filter(
			'woocommerce_order_received_verify_known_shoppers',
			array( $this, 'verify_known_shoppers' ),
			10,
			1
		);
	}

	/**
	 * Round.
	 *
	 * @param int|float $value
	 * @param int|null $precision
	 * @return float
	 */
	public function round( $value, $precision = null ) {
		if ( is_null( $precision ) ) {
			$precision = wc_get_price_decimals();
		}

		if ( class_exists( NumberUtil::class ) ) {
			return NumberUtil::round( $value, $precision );
		}

		if ( defined( 'WC_DISCOUNT_ROUNDING_MODE' ) &&
			PHP_ROUND_HALF_DOWN === WC_DISCOUNT_ROUNDING_MODE &&
			function_exists( 'wc_legacy_round_half_down' ) // @since 3.2.6
		) {
			return wc_legacy_round_half_down( $value, $precision );
		}

		return round( $value, $precision );
	}

	/**
	 * Format.
	 *
	 * @param int|float $value
	 * @return string
	 */
	public function format( $value ) {
		return str_replace( ',', '', sprintf( '%0.2f', $value ) );
	}

	/**
	 * Handle a custom `payment_id` query var to get orders with the `payment_id` meta.
	 * @see https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function handle_payment_id_query_var( $query, $query_vars ) {
		if ( ! empty( $query_vars[ WC_Payever_Gateway::PAYEVER_PAYMENT_ID ] ) ) {
			$query['meta_query'][] = array(
				'key'   => WC_Payever_Gateway::PAYEVER_PAYMENT_ID,
				'value' => esc_attr( $query_vars[ WC_Payever_Gateway::PAYEVER_PAYMENT_ID ] ),
			);
		}

		return $query;
	}

	/**
	 * Suppress "Please log in to your account to view this order" for payever orders.
	 *
	 * @param bool $is_verify
	 *
	 * @return bool
	 * @SuppressWarnings(PHPMD.ShortVariable)
	 */
	public function verify_known_shoppers( $is_verify ) {
		global $wp;

		$order_id  = $wp->query_vars['order-received'] ?? null;
		$order_key = empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ); // WPCS: input var ok, CSRF ok.

		if ( absint( $order_id ) > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ( ! $order instanceof WC_Order ) || ! hash_equals( $order->get_order_key(), $order_key ) ) {
				return $is_verify;
			}

			if ( $this->get_helper()->is_payever_method( $order->get_payment_method() ) ) {
				return false;
			}
		}

		return $is_verify;
	}
}

new WC_Payever_Hooks();
