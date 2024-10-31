<?php

defined( 'ABSPATH' ) || exit;

/**
 * @codeCoverageIgnore
 */
class WC_Payever_Admin_Shipping {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @param $screen_id
	 * @param WC_Order|\WP_Post $order
	 * @return void
	 */
	public function add_meta_boxes( $screen_id, $order ) {
		$order = $this->get_wp_wrapper()->wc_get_order( $order );
		$shop_order_screen_id = $this->get_helper()->is_hpos_enabled() ?
			wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		$shop_subscription_screen_id = $this->get_helper()->is_hpos_enabled() ?
			wc_get_page_screen_id( 'shop_subscription' ) : 'shop_subscription';

		if ( $order &&
			$this->get_helper()->is_payever_method( $order->get_payment_method() ) &&
			in_array( $screen_id, array( $shop_order_screen_id, $shop_subscription_screen_id ) )
		) {
			$provider = $order->get_meta( '_payever_shipping_provider' );
			if ( ! empty( $provider ) ) {
				add_meta_box(
					'payever-shipping-tracking',
					__( 'payever Shipping Tracking', 'payever-woocommerce-gateway' ),
					array(
						$this,
						'meta_box_shipping',
					),
					null,
					'side',
					'high'
				);
			}
		}
	}

	/**
	 * Show metabox.
	 *
	 * @param WC_Order|\WP_Post $order
	 * @return void
	 */
	public function meta_box_shipping( $order ) {
		$order = $this->get_wp_wrapper()->wc_get_order( $order );
		if ( $order ) {
			$this->get_wp_wrapper()->wc_get_template(
				'admin/metabox-shipping.php',
				array(
					'tracking_number'   => $order->get_meta( '_payever_tracking_number' ),
					'tracking_url'      => $order->get_meta( '_payever_tracking_url' ),
					'shipping_provider' => $order->get_meta( '_payever_shipping_provider' ),
					'shipping_date'     => $order->get_meta( '_payever_shipping_date' ),
				),
				'',
				__DIR__ . '/../../templates/'
			);
		}
	}
}
