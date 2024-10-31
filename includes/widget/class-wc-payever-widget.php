<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Widget' ) ) {
	return;
}

/**
 * Class WC_Payever_Widget
 */
class WC_Payever_Widget {
	use WC_Payever_Helper_Trait;
	use WC_Payever_WP_Wrapper_Trait;

	const LIVE_WIDGET_JS = 'https://widgets.payever.org/finance-express/widget.min.js';
	const STAGE_WIDGET_JS = 'https://widgets.staging.devpayever.com/finance-express/widget.min.js';

	/**
	 * Widget settings.
	 *
	 * @var array $widget_settings
	 */
	private $widget_settings = array();

	public function __construct() {
		$this->set_widget_settings( $this->get_helper()->get_payever_widget_settings() );
		$this->render_wrapper();
	}

	/**
	 * Registers the necessary action hooks to render the HTML depending on the settings.
	 *
	 * @return bool
	 */
	public function render_wrapper() {
		$this->render_button_wrapper_registrar();

		return true;
	}

	/**
	 * Registers the hooks where to render the express widget HTML code according to the settings.
	 *
	 * @return bool
	 * @throws Exception When a setting was not found.
	 * @codeCoverageIgnore
	 */
	private function render_button_wrapper_registrar() {
		if ( isset( $this->widget_settings[ WC_Payever_Helper::PAYEVER_ACTIVE_FE_ON_SINGLE_PAGE ] ) &&
			$this->widget_settings[ WC_Payever_Helper::PAYEVER_ACTIVE_FE_ON_SINGLE_PAGE ]
		) {
			add_action(
				$this->single_product_add_to_cart_renderer_hook(),
				function () {
					$product = $this->get_wp_wrapper()->wc_get_product();

					if (
						is_a( $product, WC_Product::class )
						&& ! $this->product_supports_payment( $product )
					) {

						return;
					}

					$this->button_renderer_for_product();
				},
				1
			);
		}

		if ( isset( $this->widget_settings[ WC_Payever_Helper::PAYEVER_ACTIVE_FE_ON_CART ] ) ) {
			$enabled_on_cart = $this->widget_settings[ WC_Payever_Helper::PAYEVER_ACTIVE_FE_ON_CART ];
			add_action(
				$this->proceed_to_checkout_button_renderer_hook(),
				function () use ( $enabled_on_cart ) {
					if ( ! is_cart() || ! $enabled_on_cart || $this->is_cart_price_total_zero() ) {
						return;
					}

					$this->button_renderer_for_cart();
				},
				50
			);
		}

		return true;
	}

	/**
	 * Renders the express widget HTML code
	 * @codeCoverageIgnore
	 */
	public function button_renderer_for_product() {
		$this->render_widget_code_for_single_page();
	}

	/**
	 * Converts purchase unit to the express widget HTML code
	 *
	 * @param WC_Payever_Widget_Purchase_Unit $purchase_unit
	 * @param mixed $is_variable_product
	 */
	private function render_widget_code( WC_Payever_Widget_Purchase_Unit $purchase_unit, $is_variable_product = null ) {
		$this->get_wp_wrapper()->wp_enqueue_script(
			'payever-widget',
			WC_PAYEVER_PLUGIN_URL . '/assets/js/frontend/payever_widget.js'
		);
		$this->get_wp_wrapper()->wp_add_inline_script(
			'payever-widget',
			'if (typeof PAYEVER_CONTAINER === "undefined") {
					var PAYEVER_CONTAINER = { translations: {} };
				}
				PAYEVER_CONTAINER["widget_src"] = ' . wp_json_encode( esc_url_raw( $this->get_widget_js_url() ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';
				'
		);

		if ( $is_variable_product ) {
			$product_name = 'All products';
			$purchase_cart = $purchase_unit->cart();
			if ( count( $purchase_cart ) ) {
				$purchase_item = array_shift( $purchase_cart );
				$product_name = $purchase_item['name'];
			}

			$this->get_wp_wrapper()->wp_add_inline_script(
				'payever-widget',
				'if (typeof PAYEVER_CONTAINER === "undefined") {
						var PAYEVER_CONTAINER = { translations: {} };
					}
					PAYEVER_CONTAINER["widget_is_variable_product"] = true;' . '
					PAYEVER_CONTAINER["widget_product_name"] = "' . esc_attr( $product_name ) . '";
					'
			);
		}
		?>
		<div class="payever-widget-wrapper">
			<div class="payever-widget-finexp"
				<?php foreach ( $purchase_unit->to_html_params( $is_variable_product ) as $attr_name => $attr_value ) : ?>
					<?php echo esc_attr( $attr_name ); ?>="<?php echo esc_attr( $attr_value ); ?>"
				<?php endforeach; ?>
			></div>
		</div>
		<?php
	}

	/**
	 * Renders the HTML for the payever express widget for cart page
	 *
	 * @return void
	 */
	public function button_renderer_for_cart() {
		if ( ! is_cart() || ! WC()->cart ) {
			return;
		}

		$cart_hash   = WC()->cart->get_cart_hash();
		$reference   = WC_Payever_Widget_Purchase_Unit::CART_REFERENCE_PREFIX . $cart_hash;
		$widget_cart = array();
		$cart_items  = WC()->cart->get_cart();
		foreach ( $cart_items as $cart_item ) {
			$product       = $cart_item['data'];
			$product_id    = $cart_item['variation_id'] ?? $cart_item['product_id'];
			$quantity      = $cart_item['quantity'];
			$thumb         = $this->get_wp_wrapper()->wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' );
			$thumbnail_url = $thumb ?
				array_shift( $thumb ) : $this->get_wp_wrapper()->wc_placeholder_img_src( 'thumbnail' );
			$price         = ( is_a( $product, WC_Product::class ) ) ?
				$this->get_wp_wrapper()->wc_get_price_including_tax( $product ) : 0;
			$price_amount  = ( is_a( $product, WC_Product::class ) ) ?
				$this->get_wp_wrapper()->wc_get_price_including_tax( $product, array( 'qty' => $quantity ) ) : 0;

			$widget_cart[] = new WC_Payever_Widget_Cart(
				strval( $product->get_name() ),
				strval( $product->get_short_description() ),
				strval( $product_id ),
				floatval( $price ),
				floatval( $price_amount ),
				intval( $quantity ),
				strval( $thumbnail_url )
			);
		}

		$amount          = (float) WC()->cart->get_total( 'raw' );
		$shipping_option = $this->get_widget_shipping_option();
		if ( $shipping_option ) {
			$amount -= floatval( $shipping_option->price() );
		}

		$purchase_unit = new WC_Payever_Widget_Purchase_Unit(
			$amount,
			$reference,
			$widget_cart,
			$shipping_option,
			'button',
			WC()->cart
		);

		$this->render_widget_code( $purchase_unit );
	}

	/**
	 * Prepares payment unit for single page
	 *
	 * @return void
	 */
	private function render_widget_code_for_single_page() {
		$product = $this->get_wp_wrapper()->wc_get_product();
		$amount  = ( is_a( $product, WC_Product::class ) ) ?
			$this->get_wp_wrapper()->wc_get_price_including_tax( $product ) : 0;

		$product_id    = $product->get_id();
		$reference     = WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX . $product_id;
		$thumb         = $this->get_wp_wrapper()->wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' );
		$thumbnail_url = $thumb ? array_shift( $thumb ) : wc_placeholder_img_src( 'thumbnail' );

		$widget_cart = array();
		$widget_cart[] = new WC_Payever_Widget_Cart(
			strval( $product->get_name() ),
			strval( $product->get_short_description() ),
			strval( $product_id ),
			floatval( $amount ),
			floatval( $amount ),
			1,
			strval( $thumbnail_url )
		);

		$purchase_unit = new WC_Payever_Widget_Purchase_Unit(
			$amount,
			$reference,
			$widget_cart,
			$this->get_widget_shipping_option(),
			'button',
			new WC_Cart()
		);

		$this->render_widget_code( $purchase_unit, $product->is_type( 'variable' ) );
	}

	/**
	 * Checks if payever express widget can be rendered for the given product.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return bool
	 */
	private function product_supports_payment( WC_Product $product ) {
		$in_stock = $product->is_in_stock();

		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations( 'objects' );
			$in_stock   = $this->has_in_stock_variation( $variations );
		}

		return apply_filters(
			'woocommerce_payever_payments_product_supports_payment_request_button',
			! $product->is_type( array( 'external', 'grouped' ) ) && $in_stock,
			$product
		);
	}

	/**
	 * Checks if variations contain any in stock variation.
	 *
	 * @param WC_Product_Variation[] $variations The list of variations.
	 *
	 * @return bool True if any in stock variation, false otherwise.
	 */
	private function has_in_stock_variation( $variations ) {
		foreach ( $variations as $variation ) {
			if ( $variation->is_in_stock() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the action name that payever express widget will use for rendering on the single product page after add to cart button.
	 *
	 * @return string
	 * @codeCoverageIgnore
	 */
	private function single_product_add_to_cart_renderer_hook() {
		return (string) apply_filters( 'woocommerce_payever_payments_single_product_add_to_cart_renderer_hook', 'woocommerce_after_add_to_cart_button' );
	}

	/**
	 * Returns action name that payever express widget will use for rendering on the shopping cart page.
	 *
	 * @return string
	 */
	private function proceed_to_checkout_button_renderer_hook() {
		return (string) apply_filters(
			'woocommerce_payever_payments_proceed_to_checkout_button_renderer_hook',
			'woocommerce_proceed_to_checkout'
		);
	}

	/**
	 * Checks if cart total is zero
	 *
	 * @return bool
	 * @codeCoverageIgnore
	 */
	private function is_cart_price_total_zero() {
		return WC()->cart && WC()->cart->get_total( 'numeric' ) <= 0.001;
	}

	/**
	 * Returns widget js file url
	 *
	 * @return string
	 */
	private function get_widget_js_url() {
		return ( $this->widget_settings[ WC_Payever_Helper::PAYEVER_ENVIRONMENT ] )
			? self::STAGE_WIDGET_JS
			: self::LIVE_WIDGET_JS;
	}

	/**
	 * Returns shipping method by code
	 *
	 * @param $chosen_shipping_method
	 *
	 * @return mixed|null
	 */
	private function get_shipping_method_by_code( $chosen_shipping_method ) {
		$shipping_methods = $this->get_helper()->get_available_shipping_methods();
		if ( $chosen_shipping_method ) {
			foreach ( $shipping_methods as $shipping_method ) {
				$shipping_method_code = $shipping_method->id . ':' . $shipping_method->get_instance_id();
				if ( $chosen_shipping_method === $shipping_method_code ) {
					return $shipping_method;
				}
			}
		}

		return null;
	}

	/**
	 * Builds WC_Payever_Widget_Shipping_Option object
	 *
	 * @return WC_Payever_Widget_Shipping_Option|null
	 */
	private function get_widget_shipping_option() {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping_method = $chosen_shipping_methods
			? array_shift( $chosen_shipping_methods )
			: $this->widget_settings[ WC_Payever_Helper::PAYEVER_FE_DEFAULT_SHIPPING_METHOD ];

		$shipping_method = $this->get_shipping_method_by_code( $chosen_shipping_method );
		if ( ! $shipping_method ) {
			return null;
		}

		return new WC_Payever_Widget_Shipping_Option(
			strval( $shipping_method->get_method_title() ),
			floatval( $shipping_method->cost ),
			strval( $chosen_shipping_method )
		);
	}

	/**
	 * @param array $widget_settings
	 */
	public function set_widget_settings( $widget_settings ) {
		$this->widget_settings = $widget_settings;
	}
}
