<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Payever\Sdk\Payments\Enum\PaymentMethod;
use Payever\Sdk\Payments\Http\RequestEntity\PaymentItemEntity;

/**
 * WC_Payever_Helper Class.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class WC_Payever_Helper {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Order_Totals_Trait;

	const SCALE = 2;

	const PLUGIN_CODE = 'payever';
	const SHOP_SYSTEM = 'woocommerce';

	const LOCALE_STORE_VALUE = 'store';

	const PAYEVER_PREFIX             = 'payever_';
	const SANTANDER_PREFIX           = 'santander_';

	const DEFAULT_SHIPPED_STATUS     = 'wc-completed';

	const SANDBOX_URL_CONFIG_KEY               = 'payeverSandboxUrl';
	const LIVE_URL_CONFIG_KEY                  = 'payeverLiveUrl';
	const SANDBOX_THIRD_PARTY_PLODUCTS_URL_KEY = 'payeverSandboxThirdPartyProductsUrl';
	const LIVE_THIRD_PARTY_PLODUCTS_URL_KEY    = 'payeverLiveThirdPartyProductsUrl';
	const KEY_PLUGIN_COMMAND_TIMESTAMP         = 'payeverCommandTimestamp';
	const KEY_PLUGIN_VERSION                   = 'payeverPluginVersion';
	const KEY_API_VERSION                      = 'payeverApiVersion';

	const PAYEVER_ENABLED                            = 'payever_enabled';
	const PAYEVER_ENVIRONMENT                        = 'payever_environment';
	const PAYEVER_CLIENT_SECRET                      = 'payever_client_secrect';
	const PAYEVER_CLIENT_ID                          = 'payever_client_id';
	const PAYEVER_BUSINESS_ID                        = 'payever_slug';
	const PAYEVER_ACTIVE_FE_ON_SINGLE_PAGE           = 'payever_active_widget_on_single_page';
	const PAYEVER_ACTIVE_FE_ON_CART                  = 'payever_active_widget_on_cart';
	const PAYEVER_FE_DEFAULT_SHIPPING_METHOD         = 'payever_fe_default_shipping_method';
	const PAYEVER_WIDGET_ID                          = 'payever_widget_id';
	const PAYEVER_WIDGET_THEME                       = 'payever_express_widget_theme';
	const PAYEVER_CHECKOUT_ID                        = 'payever_checkout_id';
	const PAYEVER_ACTIVE_EXPRESS_WIDGET              = 'payever_express_widget_type';
	const PAYEVER_DISPLAY_TITLE                      = 'payever_display_payment_name';
	const PAYEVER_DISPLAY_ICON                       = 'payever_display_payment_icon';
	const PAYEVER_DISPLAY_DESCRIPTION                = 'payever_display_payment_description';
	const PAYEVER_SHIPPED_STATUS                     = 'payever_shipped_status';
	const PAYEVER_LANGUAGES                          = 'payever_languages';
	const PAYEVER_REDIRECT_MODE                      = 'payever_redirect_to_payever';
	const PAYEVER_LOG_LEVEL                          = 'payever_log_level';
	const PAYEVER_LOG_DIAGNOSTIC                     = 'payever_log_diagnostic';
	const PAYEVER_API_VERSION                        = 'payever_api_version';
	const PAYEVER_APM_SECRET_SANDBOX                 = 'payever_apm_secret_sandbox';
	const PAYEVER_APM_SECRET_LIVE                    = 'payever_apm_secret_live';
	const PAYEVER_ACTIVE_PAYMENTS                    = 'woocommerce_payever_active_payments';
	const PAYEVER_ACTIVE_WIDGETS                     = 'woocommerce_payever_payment_widgets';
	const PAYEVER_ADDRESS_EQUALITY_METHODS           = 'payever_address_equality_payments';
	const PAYEVER_CHECK_VARIANT_FOR_ADDRESS_EQUALITY = 'payever_check_Variant_for_address_equality';
	const PAYEVER_SHIPPING_NOT_ALLOWED_METHODS       = 'payever_shipping_not_allowed_payments';
	const PAYEVER_ISSET_LIVE                         = 'payever_isset_live';
	const PAYEVER_LIVE_CLIENT_SECRET                 = 'payever_live_client_secrect';
	const PAYEVER_LIVE_CLIENT_ID                     = 'payever_live_client_id';
	const PAYEVER_LIVE_BUSINESS_ID                   = 'payever_live_slug';
	const PAYEVER_OAUTH_TOKEN                        = 'payever_oauth_token';
	const PAYEVER_MIGRATION                          = 'payever_migration';
	const PAYEVER_LAST_MIGRATION_FAILED              = 'payever_last_migration_failed';
	const PAYEVER_PRODUCTS_SYNC_ENABLED              = 'payever_products_synchronization_enabled';
	const PAYEVER_PRODUCTS_SYNC_ENTITY               = 'payever_products_synchronization_entity';
	const PAYEVER_PRODUCTS_SYNC_TOKEN                = 'payever_products_synchronization_token';
	const PAYEVER_PRODUCTS_SYNC_MODE                 = 'payever_products_synchronization_mode';
	const PAYEVER_FE_CALLBACK                        = 'payever_finance_express_%s';
	const PAYEVER_FE_REFERENCE_PLACEHOLDER           = '?reference=--PAYMENT-ID--';

	const PAYEVER_ALLOW_IFRAME = true;

	const WIDGET_THEME_DARK  = 'dark';
	const WIDGET_THEME_LIGHT = 'light';

	private static $instance;

	/** Singleton. */
	private function __construct() {}

	/**
	 *
	 * @return WC_Payever_Helper
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Fetch the global configuration values from the database
	 *
	 * @return array
	 */
	public function get_payever_plugin_settings() {
		$settings = array(
			self::PAYEVER_ENABLED             => 'yes' === $this->get_wp_wrapper()->get_option( self::PAYEVER_ENABLED ),
			self::PAYEVER_ENVIRONMENT         => $this->get_wp_wrapper()->get_option( self::PAYEVER_ENVIRONMENT ),
			self::PAYEVER_CLIENT_SECRET       => $this->get_wp_wrapper()->get_option( self::PAYEVER_CLIENT_SECRET ),
			self::PAYEVER_CLIENT_ID           => $this->get_wp_wrapper()->get_option( self::PAYEVER_CLIENT_ID ),
			self::PAYEVER_BUSINESS_ID         => $this->get_wp_wrapper()->get_option( self::PAYEVER_BUSINESS_ID ),
			self::PAYEVER_DISPLAY_TITLE       => $this->get_wp_wrapper()->get_option( self::PAYEVER_DISPLAY_TITLE ),
			self::PAYEVER_DISPLAY_ICON        => $this->get_wp_wrapper()->get_option( self::PAYEVER_DISPLAY_ICON ),
			self::PAYEVER_DISPLAY_DESCRIPTION => $this->get_wp_wrapper()->get_option( self::PAYEVER_DISPLAY_DESCRIPTION ),
			self::PAYEVER_SHIPPED_STATUS      => $this->get_wp_wrapper()->get_option( self::PAYEVER_SHIPPED_STATUS ),
			self::PAYEVER_LANGUAGES           => $this->get_wp_wrapper()->get_option( self::PAYEVER_LANGUAGES ),
			self::PAYEVER_REDIRECT_MODE       => $this->get_wp_wrapper()->get_option( self::PAYEVER_REDIRECT_MODE ),
			self::PAYEVER_LOG_LEVEL           => $this->get_wp_wrapper()->get_option( self::PAYEVER_LOG_LEVEL ),
			self::PAYEVER_LOG_DIAGNOSTIC      => $this->get_wp_wrapper()->get_option( self::PAYEVER_LOG_DIAGNOSTIC ),
			self::PAYEVER_API_VERSION         => (int) $this->get_wp_wrapper()->get_option( self::PAYEVER_API_VERSION ),
		);

		if ( ! self::PAYEVER_ALLOW_IFRAME ) {
			$settings[ self::PAYEVER_REDIRECT_MODE ] = true;
		}

		return $settings;
	}

	/**
	 * Fetch the widget configuration values from the database
	 *
	 * @return array
	 */
	public function get_payever_widget_settings() {
		$widget_id    = $this->get_wp_wrapper()->get_option( self::PAYEVER_WIDGET_ID );
		$widget_theme = $this->get_wp_wrapper()->get_option( self::PAYEVER_WIDGET_THEME );
		$checkout_id  = $this->get_wp_wrapper()->get_option( self::PAYEVER_CHECKOUT_ID );
		$business_id  = $this->get_wp_wrapper()->get_option( self::PAYEVER_BUSINESS_ID );

		$current_widget_option = $this->get_wp_wrapper()->get_option( self::PAYEVER_ACTIVE_EXPRESS_WIDGET );

		if ( $current_widget_option ) {
			$woo_payment_widgets_json = $this->get_wp_wrapper()->get_option( self::PAYEVER_ACTIVE_WIDGETS );

			if ( $woo_payment_widgets_json ) {
				$woo_payment_widgets = json_decode( $woo_payment_widgets_json, true );
				$widget_id           = $current_widget_option;
				$checkout_id         = $woo_payment_widgets[ $current_widget_option ]['checkout_id'];
				$business_id         = $woo_payment_widgets[ $current_widget_option ]['business_id'];
			}
		}

		return array(
			self::PAYEVER_ACTIVE_FE_ON_SINGLE_PAGE   => 'yes' === $this->get_wp_wrapper()->get_option( self::PAYEVER_ACTIVE_FE_ON_SINGLE_PAGE ),
			self::PAYEVER_ACTIVE_FE_ON_CART          => 'yes' === $this->get_wp_wrapper()->get_option( self::PAYEVER_ACTIVE_FE_ON_CART ),
			self::PAYEVER_FE_DEFAULT_SHIPPING_METHOD => $this->get_wp_wrapper()->get_option( self::PAYEVER_FE_DEFAULT_SHIPPING_METHOD ),
			self::PAYEVER_ENVIRONMENT                => $this->get_wp_wrapper()->get_option( self::PAYEVER_ENVIRONMENT ),
			self::PAYEVER_CHECKOUT_ID                => $checkout_id,
			self::PAYEVER_WIDGET_ID                  => $widget_id,
			self::PAYEVER_WIDGET_THEME               => $widget_theme,
			self::PAYEVER_BUSINESS_ID                => $business_id,
		);
	}

	/**
	 * Get payment method settings.
	 *
	 * @param string $gateway_id
	 *
	 * @return array|null
	 */
	public function get_payever_payment_settings( $gateway_id ) {
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return null;
		}

		/** @var WC_Payment_Gateway $gateway */
		$gateway = $gateways[ $gateway_id ];

		return $gateway->settings;
	}

	/**
	 * Returns active payever widget options
	 *
	 * @return array
	 */
	public function get_active_payever_widget_options() {
		$payever_widget_options   = array( '' => __( '--- Choose the widget ---', 'payever-woocommerce-gateway' ) );
		$woo_payment_widgets_json = $this->get_wp_wrapper()->get_option( self::PAYEVER_ACTIVE_WIDGETS );

		if ( $woo_payment_widgets_json ) {
			$woo_payment_widgets = json_decode( $woo_payment_widgets_json, true );
			foreach ( $woo_payment_widgets as $widget_id => $woo_payment_widget ) {
				if ( empty( $woo_payment_widget['payments'] ) ) {
					$woo_payment_widget['payments'] = array();
				}

				$payment_methods = array_unique( $woo_payment_widget['payments'] ?: array() );
				foreach ( $payment_methods as &$payment_method ) {
					$payment_method = sprintf( /* translators: %s: description_offer */ __( '%s.description_offer', 'payever-woocommerce-gateway' ), $payment_method );
				}

				$payever_widget_options[ $widget_id ] = sprintf(
					/* translators: %s: widget type */
					esc_html__( '%1$s - %2$s', 'payever-woocommerce-gateway' ),
					esc_html( $woo_payment_widget['type'] ),
					esc_html( implode( ', ', $payment_methods ) ),
				);
			}
		}

		return $payever_widget_options;
	}

	/**
	 * @return array
	 */
	public function get_widget_themes() {
		return array(
			self::WIDGET_THEME_LIGHT => __( 'Light', 'payever-woocommerce-gateway' ),
			self::WIDGET_THEME_DARK  => __( 'Dark', 'payever-woocommerce-gateway' ),
		);
	}

	/**
	 * Get Transition Shipped Status.
	 *
	 * @return string
	 */
	public function get_transition_shipping_status() {
		return str_replace( 'wc-', '', $this->get_shipping_status() );
	}

	/**
	 * Shipped Order Status
	 *
	 * @return bool|mixed|string
	 */
	public function get_shipping_status() {
		return $this->get_wp_wrapper()->get_option( WC_Payever_Helper::PAYEVER_SHIPPED_STATUS ) ?:
			WC_Payever_Helper::DEFAULT_SHIPPED_STATUS;
	}

	/**
	 * @param string $payment_option
	 *
	 * @return string
	 */
	public function remove_payever_prefix( $payment_option ) {
		return str_replace( self::PAYEVER_PREFIX, '', $payment_option );
	}

	/**
	 * @param string $payment_option
	 *
	 * @return string
	 */
	public function add_payever_prefix( $payment_option ) {
		return self::PAYEVER_PREFIX . $payment_option;
	}

	/**
	 * Checks santanders
	 *
	 * @param string $payment_method
	 *
	 * @return bool
	 */
	public function is_santander( $payment_method ) {
		if ( false !== strpos( $payment_method, self::SANTANDER_PREFIX ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if payment methods is ivy
	 *
	 * @param string $payment_method
	 *
	 * @return bool
	 */
	public function is_ivy( $payment_method ) {
		if ( false !== strpos( $payment_method, PaymentMethod::METHOD_IVY ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if payment methods is openbank
	 *
	 * @param $payment_method
	 *
	 * @return bool
	 */
	public function is_openbank( $payment_method ) {
		if ( false !== strpos( $payment_method, PaymentMethod::METHOD_OPENBANK ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param $payment_method
	 *
	 * @return bool
	 */
	public function is_payever_method( $payment_method ) {
		if ( false !== strpos( $payment_method, self::PAYEVER_PREFIX ) ) {
			return true;
		}

		return false;
	}

	public function validate_order_payment_method( WC_Order $order ) {
		return $this->is_payever_method( $order->get_payment_method() );
	}

	/**
	 * @return bool
	 */
	public function check_variant_for_address_equality() {
		return (bool) $this->get_wp_wrapper()->get_option( self::PAYEVER_CHECK_VARIANT_FOR_ADDRESS_EQUALITY );
	}

	/**
	 * @return array
	 */
	public function get_address_equality_methods() {
		$result = $this->get_wp_wrapper()->get_option( WC_Payever_Helper::PAYEVER_ADDRESS_EQUALITY_METHODS );
		if ( ! empty( $result ) ) {
			return (array) $result;
		}

		return PaymentMethod::getShouldHideOnDifferentAddressMethods();
	}

	/**
	 * Clears the sessions on each page fragments
	 *
	 * @return void
	 */
	public function clear_session_fragments() {
		if ( isset( WC()->session->payever_receipt_page ) ) {
			unset( WC()->session->payever_receipt_page );
		}
	}

	/**
	 * Returns payever modes
	 *
	 * @return array
	 */
	public function get_payever_modes() {
		return array(
			'0' => __( 'Live', 'payever-woocommerce-gateway' ),
			'1' => __( 'Sandbox', 'payever-woocommerce-gateway' ),
		);
	}

	/**
	 * @return bool
	 */
	public function is_products_sync_enabled() {
		return (bool) $this->get_wp_wrapper()->get_option( self::PAYEVER_PRODUCTS_SYNC_ENABLED );
	}

	/**
	 * @return string
	 */
	public function get_product_sync_token() {
		return $this->get_wp_wrapper()->get_option( self::PAYEVER_PRODUCTS_SYNC_TOKEN );
	}

	/**
	 * @return bool
	 */
	public function is_products_sync_cron_mode() {
		return 'cron' === $this->get_wp_wrapper()->get_option( self::PAYEVER_PRODUCTS_SYNC_MODE );
	}

	/**
	 * Gets product id by sku
	 *
	 * @param string $sku
	 *
	 * @return int The found product variation ID, or 0 on failure.
	 */
	public function get_product_variation_id_by_sku( $sku ) {
		/** @var WC_Product_Variation $product */
		$product = wc_get_product(
			wc_get_product_id_by_sku( $sku )
		);

		if ( ! $product ) {
			return 0;
		}

		return (int) $product->get_id();
	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 */
	public function get_request_headers() {
		$headers = array();

		foreach ( $_SERVER as $name => $value ) {
			$header_title = sanitize_text_field( wp_unslash( $name ) );

			if ( 0 !== strpos( $header_title, 'HTTP_' ) ) {
				continue;
			}
			$headers[ str_replace(
				' ',
				'-',
				ucwords(
					strtolower(
						str_replace( '_', ' ', substr( $header_title, 5 ) )
					)
				)
			) ] = sanitize_text_field( wp_unslash( $value ) ); // WPCS: input var ok, CSRF ok.
		}

		return $headers;
	}

	/**
	 * @param string|int $key
	 *
	 * @return false|string
	 * @throws Exception
	 */
	public function get_hash( $key ) {
		$client_config = WC_Payever_Api::get_instance()->get_plugins_api_client()->getConfiguration();

		return hash_hmac( 'sha256', $client_config->getClientId() . $key, $client_config->getClientSecret() );
	}

	/**
	 * Returns callback url for express widget
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_widget_callback_url( $type ) {
		return WC()->api_request_url( '' ) . sprintf( self::PAYEVER_FE_CALLBACK, $type );
	}

	/**
	 * Returns notice url for express widget
	 *
	 * @return string
	 */
	public function get_widget_notice_url() {
		$notice_url = WC()->api_request_url( '' ) . sprintf( self::PAYEVER_FE_CALLBACK, 'notice' );

		return $notice_url . self::PAYEVER_FE_REFERENCE_PLACEHOLDER;
	}

	/**
	 * @return array
	 */
	public function get_available_shipping_methods() {
		$zones = WC_Shipping_Zones::get_zones();

		$shipping_methods = array_column( $zones, 'shipping_methods' );

		return reset( $shipping_methods );
	}

	/**
	 * Checks if High-Performance Order Storage is enabled.
	 *
	 * @see https://woocommerce.com/document/high-performance-order-storage/
	 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
	 * @return bool
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 */
	public function is_hpos_enabled() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		if ( ! method_exists( OrderUtil::class, 'custom_orders_table_usage_is_enabled' ) ) {
			return false;
		}

		return OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Check if both values are the same.
	 *
	 * @param float $value1
	 * @param float $value2
	 *
	 * @return bool
	 */
	public function are_same( $value1, $value2 ) {
		if ( function_exists( 'bccomp' ) ) {
			return 0 === bccomp( (string) $value1, (string) $value2, self::SCALE );
		}

		return round( $value1, self::SCALE ) === round( $value2, self::SCALE );
	}

	/**
	 * Get Payment ID.
	 *
	 * @param WC_Order $order
	 *
	 * @return string|null
	 */
	public function get_payment_id( WC_Order $order ) {
		$payment_id = $order->get_meta( WC_Payever_Gateway::CURRENT_PAYEVER_PAYMENT_ID );
		if ( empty( $payment_id ) ) {
			$payment_id = $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID );
		}

		if ( empty( $payment_id ) ) {
			return null;
		}

		return $payment_id;
	}

	/**
	 * Prepare payment items and additional data.
	 *
	 * @param WC_Order $order
	 * @param array<string, array{item_id: int, qty: int}> $items
	 * @return array{items: PaymentItemEntity[], delivery_fee: float, payment_fee: float, amount: float}
	 * @throws Exception
	 */
	public function get_payment_items_data( WC_Order $order, array $items ) {
		$result = array(
			'items'        => array(),
			'delivery_fee' => 0.0,
			'payment_fee'  => 0.0,
			'amount'       => 0.0,
		);
		foreach ( $items as $item ) {
			$item_id  = $item['item_id'];
			$quantity = $item['qty'];

			/** @var WC_Order_Item $item */
			$item       = $order->get_item( $item_id );
			$order_item = $this->get_order_total_model()->get_order_item( $order, $item_id );
			if ( is_a( $item, 'WC_Order_Item_Shipping' ) ) {
				$result['delivery_fee'] = $order_item['unit_price'];
				$result['amount'] += $order_item['unit_price'];
				continue;
			}
			if ( is_a( $item, 'WC_Order_Item_Fee' ) ) {
				$result['payment_fee'] += $order_item['unit_price'];
				$result['amount'] += $order_item['unit_price'];
				continue;
			}

			$payment_entity = new PaymentItemEntity();
			$payment_entity->setIdentifier( strval( $item->get_product_id() ) )
				->setName( $item->get_name() )
				->setPrice( round( $order_item['unit_price'], 2 ) )
				->setQuantity( $quantity );

			$result['items'][] = $payment_entity;
			$result['amount'] += $order_item['unit_price'] * $quantity;
		}

		return $result;
	}

	/**
	 * @param PaymentItemEntity[] $items
	 *
	 * @return string
	 */
	public function format_payment_items( array $items, $separator = "\n" ) {
		$result = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof PaymentItemEntity ) {
				throw new InvalidArgumentException( 'Item must be an instance of PaymentItemEntity' );
			}

			$result[] = sprintf(
				'%s x %s',
				$item->getName(),
				$item->getQuantity()
			);
		}

		return join( $separator, $result );
	}


	/**
	 * @return false|mixed|string
	 */
	public function get_checkout_language() {
		$language = empty( $this->plugin_settings[ WC_Payever_Helper::PAYEVER_LANGUAGES ] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ), 0, 2 ) // WPCS: input var ok, CSRF ok.
			: $this->plugin_settings[ WC_Payever_Helper::PAYEVER_LANGUAGES ];

		if ( WC_Payever_Helper::LOCALE_STORE_VALUE === $language ) {
			if ( function_exists( 'get_locale' ) ) {
				$locale = get_locale();
			}

			if ( function_exists( 'get_user_locale' ) ) {
				$locale = get_user_locale();
			}

			if ( function_exists( 'weglot_get_service' ) ) {
				$locale = weglot_get_service( 'Request_Url_Service_Weglot' )->get_current_language();
				if ( is_object( $locale ) ) {
					$locale = $locale->getInternalCode();
				}
			}

			if ( isset( $locale ) ) {
				$language = explode( '_', $locale )[0];
			}
		}

		return $language;
	}

	/**
	 * @param WC_Order $order
	 */
	public function increase_order_stock( WC_Order $order ) {
		$is_reduced = $order->get_meta( WC_Payever_Gateway::META_STOCK_REDUCED );
		if ( $is_reduced ) {
			$this->get_wp_wrapper()->wc_maybe_increase_stock_levels( $order->get_id() );
		}
	}

	/**
	 * @param WC_Order $order
	 */
	public function reduce_order_stock( WC_Order $order ) {
		$reloaded_order = $this->get_wp_wrapper()->wc_get_order( $order->get_id() );
		$is_reduced = $reloaded_order->get_meta( WC_Payever_Gateway::META_STOCK_REDUCED );

		if ( empty( $is_reduced ) ) {
			$this->get_wp_wrapper()->wc_reduce_stock_levels( $order->get_id() );
			$order->update_meta_data( WC_Payever_Gateway::META_STOCK_REDUCED, '1' );
			$order->save_meta_data();
		}
	}

	/**
	 * Add error message in the metabox.
	 *
	 * @param $message
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	public function add_error_metabox( $message ) {
		WC_Admin_Meta_Boxes::add_error( $message );
	}
}
