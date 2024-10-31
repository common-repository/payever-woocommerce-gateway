<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Payments Blocks integration
 */
final class WC_Payever_Payments_Blocks extends AbstractPaymentMethodType {
	use WC_Payever_Helper_Trait;
	use WC_Payever_Checkout_Helper_Trait;

	/**
	 * The gateway instance.
	 *
	 * @var WC_Payever_Gateway[]
	 */
	private $gateways = array();

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'payever_gateway';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$gateways = WC()->payment_gateways->payment_gateways();

		$data = array();
		foreach ( $gateways as $gateway ) {
			if ( ! $gateway instanceof WC_Payever_Gateway ) {
				continue;
			}
			if ( $this->get_helper()->is_payever_method( $gateway->id ) ) {
				$data[] = $gateway;
			}
		}

		$this->gateways = $data;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 * @codeCoverageIgnore
	 * @return boolean
	 */
	public function is_active() {
		return true;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$path = trailingslashit( WP_PLUGIN_DIR ) . 'payever-woocommerce-gateway';
		$url  = plugin_dir_url( $path . '/assets/js/frontend/.' ) . 'checkout-blocks.js';

		wp_register_script(
			'wc-payever-payment-blocks',
			$url,
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			null,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'wc-payever-payment-blocks',
				'payever-woocommerce-gateway',
				$path . 'languages/'
			);
		}

		// Localize the script with new data
		wp_localize_script(
			'wc-payever-payment-blocks',
			'WC_Payever_Payments_Blocks',
			array(
				'nonce'    => wp_create_nonce( 'payever_get_availability' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		return array( 'wc-payever-payment-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$data = array();
		foreach ( $this->gateways as $gateway ) {
			$data[] = array(
				'id'              => $gateway->id,
				'title'           => $gateway->get_title(),
				'description'     => $gateway->get_description(),
				'icon'            => $gateway->get_icon( true ),
				'supports'        => array_filter( $gateway->supports, array( $gateway, 'supports' ) ),
				'is_enabled'      => $this->get_checkout_helper()->is_payment_enabled(
					$gateway->id
				),
				'is_hidden'       => $this->get_checkout_helper()->is_hidden_method(
					$gateway->settings['method_code']
				),
				'is_diff_address' => $this->get_checkout_helper()->is_hidding_applicable_on_different_address(
					$gateway->settings['method_code'],
					$gateway->settings['variant_id']
				),
				'currencies'      => $gateway->settings['currencies'],
				'countries'       => $gateway->settings['countries'],
				'method_code'     => $gateway->settings['method_code'],
				'variant_id'      => $gateway->settings['variant_id'],
				'max_amount'      => $gateway->max_amount,
				'min_amount'      => $gateway->min_amount,
			);
		}

		return $data;
	}

	/**
	 * @return WC_Payever_Gateway[]
	 */
	public function get_gateways() {
		return $this->gateways;
	}
}
