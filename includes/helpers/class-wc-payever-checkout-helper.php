<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\PaymentMethod;

class WC_Payever_Checkout_Helper {
	use WC_Payever_WP_Wrapper_Trait;

	const SESSION_HIDDEN_METHODS_KEY = 'payever_hidden_methods';

	const CHECK_FIELDS = array(
		'country',
		'postcode',
		'state',
		'city',
		'address_1',
		'address_2',
		'first_name',
		'last_name',
	);

	/**
	 * @var WC_Payment_Gateway[]
	 */
	private $gateways;

	/**
	 * @var WC_Payever_Helper
	 */
	private $helper;

	/**
	 * Plugin settings.
	 *
	 * @var array $plugin_settings
	 */
	public $plugin_settings = array();

	public function __construct() {
		$this->gateways        = WC()->payment_gateways->payment_gateways();
		$this->helper          = WC_Payever_Helper::instance();
		$this->plugin_settings = $this->helper->get_payever_plugin_settings();
	}

	public function is_available(
		$gateway_id,
		$cart_amount,
		$currency,
		array $billing_address,
		array $shipping_address
	) {
		$is_available = $this->is_payment_enabled( $gateway_id );
		if ( ! $is_available ) {
			return false;
		}

		$gateway = $this->get_payment_gateway( $gateway_id );

		if ( $this->is_hidden_method( $gateway->settings['method_code'] ) ) {
			return false;
		}

		if ( $this->is_payment_rules_not_valid(
			$gateway_id,
			$billing_address['country'],
			$shipping_address['country'],
			$cart_amount,
			$currency
		) ) {
			return false;
		}

		if ( $this->is_hidden_method_on_different_address(
			$gateway->settings['method_code'],
			$gateway->settings['variant_id'],
			$billing_address,
			$shipping_address
		) ) {
			return false;
		}

		$method = $this->helper->add_payever_prefix( $gateway->settings['method_code'] );
		if ( PaymentMethod::shouldHideOnCurrentDevice( $method ) ) {
			return false;
		}

		return $is_available;
	}

	/**
	 * Hide payever method
	 *
	 * @param string $method
	 * @return bool
	 */
	public function add_payever_hidden_method( $method ) {
		$method         = $this->helper->add_payever_prefix( $method );
		$hidden_methods = $this->get_payever_hidden_methods();
		if ( in_array( $method, $this->get_allowed_to_hide_methods() ) && ! in_array( $method, $hidden_methods ) ) {
			$hidden_methods[] = $method;
			WC()->session->set( self::SESSION_HIDDEN_METHODS_KEY, $hidden_methods );

			return true;
		}

		return false;
	}

	/**
	 * Checks if the payment method is hidden.
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	public function is_hidden_method( $method ) {
		$method         = $this->helper->add_payever_prefix( $method );
		$hidden_methods = $this->get_payever_hidden_methods();

		return in_array( $method, $hidden_methods );
	}

	/**
	 * @param string $method
	 * @param string $variant_id
	 * @param array $billing_address
	 * @param array $shipping_address
	 *
	 * @return bool
	 */
	private function is_hidden_method_on_different_address(
		$method,
		$variant_id,
		array $billing_address,
		array $shipping_address
	) {
		return $this->is_hidding_applicable_on_different_address( $method, $variant_id ) &&
			$this->is_address_different( $billing_address, $shipping_address );
	}

	/**
	 * Check if hiding is applicable for different address based on method and variant ID
	 *
	 * @param string $method
	 * @param int $variant_id
	 *
	 * @return bool
	 */
	public function is_hidding_applicable_on_different_address( $method, $variant_id ) {
		if ( $this->helper->check_variant_for_address_equality() ) {
			return in_array( $variant_id, $this->get_payever_hide_on_different_address_methods( false ) );
		}

		return in_array(
			$this->helper->add_payever_prefix( $method ),
			$this->get_payever_hide_on_different_address_methods( true )
		);
	}

	/**
	 * Checks if the billing and shipping addresses are different based on specified fields
	 *
	 * @param array $billing_address The billing address
	 * @param array $shipping_address The shipping address
	 *
	 * @return bool Returns true if the addresses are different, false otherwise
	 */
	private function is_address_different( $billing_address, $shipping_address ) {
		foreach ( self::CHECK_FIELDS as $field ) {
			$shipping_val = $shipping_address[ $field ];
			$billing_val  = $billing_address[ $field ];

			if ( 'postcode' === $field ) {
				$shipping_val = str_replace( ' ', '', (string) $shipping_val );
				$billing_val  = str_replace( ' ', '', (string) $billing_val );
			}

			if ( $shipping_val && $billing_val && $billing_val !== $shipping_val ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the order payment rules are correct
	 *
	 * @param $gateway_id
	 * @param $billing_country
	 * @param $shipping_country
	 * @param $cart_amount
	 * @param $currency
	 *
	 * @return bool
	 */
	private function is_payment_rules_not_valid(
		$gateway_id,
		$billing_country,
		$shipping_country,
		$cart_amount,
		$currency
	) {
		$gateway = $this->get_payment_gateway( $gateway_id );

		return ! isset( $gateway->settings['countries'] )
				|| ! isset( $gateway->settings['currencies'] )
				|| ! $this->is_payment_available_by_cart_amount( $gateway_id, $cart_amount )
				|| ! $this->is_payment_available_by_options( $currency, $gateway->settings['currencies'] )
				|| ! $this->is_payment_available_by_options( $billing_country, $gateway->settings['countries'] )
				|| ! $this->is_payment_available_by_options( $shipping_country, $gateway->settings['countries'] );
	}

	/**
	 * Checks if payment is enabled.
	 *
	 * @param string $gateway_id
	 * @return bool
	 */
	public function is_payment_enabled( $gateway_id ) {
		return 'yes' === $this->get_payment_gateway( $gateway_id )->enabled &&
			$this->plugin_settings[ WC_Payever_Helper::PAYEVER_ENABLED ];
	}

	/**
	 * Checks if payment is available by cart amount.
	 *
	 * @param string $gateway_id
	 * @param float $cart_amount
	 * @return bool
	 */
	private function is_payment_available_by_cart_amount( $gateway_id, $cart_amount ) {
		$gateway = $this->get_payment_gateway( $gateway_id );

		if (
			WC()->cart
			&& 0 < $cart_amount
			&& 0 < $gateway->max_amount
			&& $gateway->max_amount < $cart_amount
			|| $gateway->min_amount >= 0
				&& $gateway->min_amount > $cart_amount
		) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if a payment is available based on the given options.
	 *
	 * @param mixed $value The payment value to check.
	 * @param array $options The available payment options.
	 *
	 * @return boolean Returns true if the payment is available, false otherwise.
	 */
	private function is_payment_available_by_options( $value, $options ) {
		if ( ! in_array( $value, $options ) && ! in_array( 'any', $options ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns payever hidden methods from session
	 *
	 * @return array
	 */
	private function get_payever_hidden_methods() {
		if ( is_admin() ) {
			return array();
		}

		if ( ! WC()->session ) {
			return array();
		}

		return WC()->session->get( self::SESSION_HIDDEN_METHODS_KEY )
			? WC()->session->get( self::SESSION_HIDDEN_METHODS_KEY )
			: array();
	}

	/**
	 * Returns methods to hide
	 *
	 * @return array
	 */
	private function get_allowed_to_hide_methods() {
		return $this->add_payever_prefix_to_payment_methods( PaymentMethod::getShouldHideOnRejectMethods() );
	}

	/**
	 * Methods we should hide if shipping and billing addresses is different
	 *
	 * @param bool $add_prefix
	 *
	 * @return array
	 */
	private function get_payever_hide_on_different_address_methods( $add_prefix ) {
		$address_equality_methods = $this->helper->get_address_equality_methods();

		return $add_prefix ? $this->add_payever_prefix_to_payment_methods( $address_equality_methods ) :
			$address_equality_methods;
	}

	/**
	 * @param array $payment_methods
	 *
	 * @return array
	 */
	private function add_payever_prefix_to_payment_methods( $payment_methods ) {
		foreach ( $payment_methods as &$payment_method ) {
			$payment_method = $this->helper->add_payever_prefix( $payment_method );
		}

		return $payment_methods;
	}

	/**
	 * Get Payment Gateway by ID.
	 *
	 * @param string $gateway_id
	 *
	 * @return WC_Payment_Gateway
	 */
	private function get_payment_gateway( $gateway_id ) {
		if ( isset( $this->gateways[ $gateway_id ] ) ) {
			return $this->gateways[ $gateway_id ];
		}

		throw new InvalidArgumentException( 'The payment gateway "' . esc_html( $gateway_id ) . '" does not exist.' );
	}
}
