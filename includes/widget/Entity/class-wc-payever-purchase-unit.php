<?php
/**
 * The purchase unit object.
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Widget_Purchase_Unit' ) ) {
	return;
}

/**
 * Class WC_Payever_Widget_Purchase_Unit
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WC_Payever_Widget_Purchase_Unit {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	const CART_REFERENCE_PREFIX = 'cart_';
	const PROD_REFERENCE_PREFIX = 'prod_';

	/**
	 * The widget id.
	 *
	 * @var string
	 */
	private $widget_id;

	/**
	 * The widget theme.
	 *
	 * @var string
	 */
	private $widget_theme;

	/**
	 * The checkout id.
	 *
	 * @var string
	 */
	private $checkout_id;

	/**
	 * The business id.
	 *
	 * @var string
	 */
	private $business_id;

	/**
	 * The widget type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The Items.
	 *
	 * @var WC_Payever_Widget_Cart[]
	 */
	private $cart;

	/**
	 * The shipping.
	 *
	 * @var WC_Payever_Widget_Shipping_Option|null
	 */
	private $shipping_option;

	/**
	 * The reference.
	 *
	 * @var string
	 */
	private $reference;

	/**
	 * The amount.
	 *
	 * @var float
	 */
	private $amount;

	/**
	 * The cancel url.
	 *
	 * @var string
	 */
	private $cancel_url;

	/**
	 * The success url.
	 *
	 * @var string
	 */
	private $success_url;

	/**
	 * The pending url.
	 *
	 * @var string
	 */
	private $pending_url;

	/**
	 * The failure url.
	 *
	 * @var string
	 */
	private $failure_url;

	/**
	 * The notice url.
	 *
	 * @var string
	 */
	private $notice_url;
	/**
	 * @var string
	 */
	private $quote_callback_url;

	/**
	 * @param float $amount
	 * @param string $reference
	 * @param WC_Payever_Widget_Cart[] $cart
	 * @param WC_Payever_Widget_Shipping_Option|null $shipping_option
	 * @param string $type
	 * @param WC_Cart|null $wc_cart
	 */
	public function __construct(
		$amount,
		$reference,
		$cart = array(),
		$shipping_option = null,
		$type = 'button',
		$wc_cart = null
	) {
		$this->amount          = $amount;
		$this->reference       = $reference;
		$this->shipping_option = $shipping_option;
		$this->type            = $type;

		$this->cart = array_map(
			static function ( WC_Payever_Widget_Cart $cart_item ) {
				return $cart_item->toArray();
			},
			$cart
		);

		$widget_settings = $this->get_helper()->get_payever_widget_settings();

		$this->business_id  = $widget_settings[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ];
		$this->checkout_id  = $widget_settings[ WC_Payever_Helper::PAYEVER_CHECKOUT_ID ];
		$this->widget_id    = $widget_settings[ WC_Payever_Helper::PAYEVER_WIDGET_ID ];
		$this->widget_theme = $widget_settings[ WC_Payever_Helper::PAYEVER_WIDGET_THEME ];

		$this->success_url        = $this->get_helper()->get_widget_callback_url( 'success' );
		$this->pending_url        = $this->get_helper()->get_widget_callback_url( 'success' );
		$this->failure_url        = $this->get_helper()->get_widget_callback_url( 'failure' );
		$this->cancel_url         = $this->get_helper()->get_widget_callback_url( 'cancel' );
		$this->notice_url         = $this->get_helper()->get_widget_notice_url();

		// Save cart in session get the url
		$this->quote_callback_url = $this->get_widget_callback_url( $wc_cart );
	}

	/**
	 * Returns the amount.
	 *
	 * @return float
	 */
	public function amount() {
		return $this->amount;
	}

	/**
	 * Returns the reference.
	 *
	 * @return string
	 */
	public function reference() {
		return $this->reference;
	}

	/**
	 * Returns the cart items.
	 *
	 * @return array
	 */
	public function cart() {
		return $this->cart;
	}

	/**
	 * Returns the widget id.
	 *
	 * @return string
	 */
	public function widget_id() {
		return $this->widget_id;
	}

	/**
	 * Returns the widget theme.
	 *
	 * @return string
	 */
	public function widget_theme() {
		return $this->widget_theme;
	}

	/**
	 * Returns the checkout id.
	 *
	 * @return string
	 */
	public function checkout_id() {
		return $this->checkout_id;
	}

	/**
	 * Returns the business id.
	 *
	 * @return string
	 */
	public function business_id() {
		return $this->business_id;
	}

	/**
	 * Returns the type.
	 *
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * Returns the cancel_url.
	 *
	 * @return string
	 */
	public function cancel_url() {
		return $this->cancel_url;
	}

	/**
	 * Returns the success_url.
	 *
	 * @return string
	 */
	public function success_url() {
		return $this->success_url;
	}

	/**
	 * Returns the pending_url.
	 *
	 * @return string
	 */
	public function pending_url() {
		return $this->pending_url;
	}

	/**
	 * Returns the failure_url.
	 *
	 * @return string
	 */
	public function failure_url() {
		return $this->failure_url;
	}

	/**
	 * Returns the notice_url.
	 *
	 * @return string
	 */
	public function notice_url() {
		return $this->notice_url;
	}

	public function quote_callback_url() {
		return $this->quote_callback_url;
	}

	/**
	 * Returns the Quote callback url.
	 *
	 * @param WC_Cart $cart
	 * @return string
	 */
	private function get_widget_callback_url( WC_Cart $cart ) {
		delete_expired_transients();

		// Clone the cart
		if ( method_exists( $cart, '__clone' ) ) {
			$cart = clone $cart;
		}

		$cart_hash = $cart->get_cart_hash();
		$this->get_wp_wrapper()->set_transient(
			'payever_cart_' . $cart_hash,
			$cart,
			30 * MINUTE_IN_SECONDS
		);

		return add_query_arg(
			array(
				'reference' => $this->reference(),
				'hash'      => $cart_hash,
			),
			$this->get_helper()->get_widget_callback_url( 'quotecallback' )
		);
	}

	/**
	 * Prepares array of widget parameters
	 *
	 * @return array
	 */
	private function to_array_params() {
		$purchase_unit = array(
			'data-widgetid'         => $this->widget_id(),
			'data-theme'            => $this->widget_theme(),
			'data-checkoutid'       => $this->checkout_id(),
			'data-business'         => $this->business_id(),
			'data-type'             => $this->type(),
			'data-reference'        => $this->reference(),
			'data-amount'           => $this->amount(),
			'data-cancelurl'        => $this->cancel_url(),
			'data-failureurl'       => $this->failure_url(),
			'data-pendingurl'       => $this->success_url(),
			'data-successurl'       => $this->success_url(),
			'data-noticeurl'        => $this->notice_url(),
			'data-quotecallbackurl' => $this->quote_callback_url(),
		);

		if ( $this->cart() && ! empty( $this->cart() ) ) {
			$purchase_unit['data-cart'] = wp_json_encode( $this->cart() );
		}

		return $purchase_unit;
	}

	/**
	 * Prepares html parameters for widget block
	 *
	 * @return string
	 */
	public function to_html_params( $is_product_page = null ) {
		$purchase_params = $this->to_array_params();

		if ( $is_product_page ) {
			unset( $purchase_params['data-reference'] );
			unset( $purchase_params['data-amount'] );
			unset( $purchase_params['data-cart'] );
		}

		return $purchase_params;
	}
}
