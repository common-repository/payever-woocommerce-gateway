<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Synchronization' ) ) {
	return;
}

use Payever\Sdk\Payments\Converter\PaymentOptionConverter;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @codeCoverageIgnore
 */
class WC_Payever_Synchronization {

	private static $plugin_settings;

	private static $currency_rates = array();

	public static function init() {
		add_action( 'woocommerce_api_payever_synchronization', array( __CLASS__, 'synchronization' ) );
		add_action( 'woocommerce_api_payever_set_sandbox_api_keys', array( __CLASS__, 'set_sandbox_api_keys' ) );
		add_action( 'woocommerce_api_payever_set_live_api_keys', array( __CLASS__, 'set_live_api_keys' ) );
		add_action( 'woocommerce_api_payever_toggle_subscription', array( __CLASS__, 'toggle_subscription' ) );
		add_action(
			'woocommerce_api_payever_synchronization_incoming',
			array(
				__CLASS__,
				'payever_synchronization_incoming',
			)
		);
		add_action( 'woocommerce_api_payever_fe_synchronization', array( __CLASS__, 'fe_synchronization' ) );
		add_action( 'wp_ajax_export_products', array( __CLASS__, 'export_products' ) );
		add_action( 'wp_ajax_nopriv_export_products', array( __CLASS__, 'export_products' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'payever_migration_process' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'synchronization_admin_notices' ) );
		add_action( 'admin_notices', array( __CLASS__, 'check_migration' ) );

		self::$plugin_settings = WC_Payever_Helper::instance()->get_payever_plugin_settings();
	}

	/**
	 * Synchronization function
	 */
	public static function synchronization() {
		try {
			self::synchronize_payment_options();
			set_transient(
				'payever_success_msg',
				__(
					'Your settings have been synchronized with your payever account.',
					'payever-woocommerce-gateway'
				)
			);
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			if ( 401 === $e->getCode() ) {
				$message = __( 'Could not synch - please check if the credentials you entered are correct and match the mode (live/sandbox)', 'payever-woocommerce-gateway' );
			}
			set_transient( 'payever_error_msg', $message );
		}

		self::update_settings_page();
	}

	/**
	 * Synchronization function
	 */
	public static function fe_synchronization() {
		try {
			self::update_payment_widgets();
			set_transient(
				'payever_success_msg',
				__(
					'The list of express widgets has been updated.',
					'payever-woocommerce-gateway'
				)
			);
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			if ( 401 === $e->getCode() ) {
				$message = __( 'Could not synch - please check if the credentials you entered are correct and match the mode (live/sandbox)', 'payever-woocommerce-gateway' );
			}

			set_transient( 'payever_error_msg', $message );
		}

		self::update_fe_widget_page();
	}

	/**
	 * Synchronize function
	 *
	 * @throws Exception
	 */
	public static function update_settings_page() {
		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=payever_settings' ) );
	}

	/**
	 * Refreshes the express widget page
	 *
	 * @throws Exception
	 */
	public static function update_fe_widget_page() {
		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=payever_settings&section=fe_widget' ) );
	}

	/**
	 * Sets sandbox api keys
	 */
	public static function set_sandbox_api_keys() {
		try {
			$environment = (int) get_option( WC_Payever_Helper::PAYEVER_ENVIRONMENT );
			if ( 0 === $environment ) {
				update_option( WC_Payever_Helper::PAYEVER_ENABLED, 'no' );
				update_option( WC_Payever_Helper::PAYEVER_ISSET_LIVE, 1 );
				update_option(
					WC_Payever_Helper::PAYEVER_LIVE_CLIENT_SECRET,
					self::$plugin_settings[ WC_Payever_Helper::PAYEVER_CLIENT_SECRET ]
				);
				update_option(
					WC_Payever_Helper::PAYEVER_LIVE_CLIENT_ID,
					self::$plugin_settings[ WC_Payever_Helper::PAYEVER_CLIENT_ID ]
				);
				update_option(
					WC_Payever_Helper::PAYEVER_LIVE_BUSINESS_ID,
					self::$plugin_settings[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ]
				);
			}

			self::save_sandbox_api_keys();
			self::synchronize_payment_options();
			set_transient(
				'payever_success_msg',
				__(
					'You set up sandbox API Keys and your settings have been synchronized with your payever account.',
					'payever-woocommerce-gateway'
				)
			);
		} catch ( Exception $e ) {
			set_transient( 'payever_error_msg', $e->getMessage() );
		}

		self::update_settings_page();
	}

	/**
	 * @return void
	 */
	private static function save_sandbox_api_keys() {
		$sandbox_api = array(
			WC_Payever_Helper::PAYEVER_ENVIRONMENT   => 1,
			WC_Payever_Helper::PAYEVER_CLIENT_SECRET => '2fjpkglmyeckg008oowckco4gscc4og4s0kogskk48k8o8wgsc',
			WC_Payever_Helper::PAYEVER_CLIENT_ID     => '2746_6abnuat5q10kswsk4ckk4ssokw4kgk8wow08sg0c8csggk4o00',
			WC_Payever_Helper::PAYEVER_BUSINESS_ID   => '815c5953-6881-11e7-9835-52540073a0b6',
		);

		self::$plugin_settings[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ] = $sandbox_api[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ];
		foreach ( $sandbox_api as $key => $value ) {
			update_option( $key, $value );
		}
	}

	/**
	 * Sets live api keys
	 */
	public static function set_live_api_keys() {
		try {
			update_option( WC_Payever_Helper::PAYEVER_ENABLED, 'yes' );
			update_option( WC_Payever_Helper::PAYEVER_ENVIRONMENT, 0 );
			update_option(
				WC_Payever_Helper::PAYEVER_CLIENT_SECRET,
				get_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_SECRET )
			);
			update_option( WC_Payever_Helper::PAYEVER_CLIENT_ID, get_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_ID ) );
			update_option(
				WC_Payever_Helper::PAYEVER_BUSINESS_ID,
				get_option( WC_Payever_Helper::PAYEVER_LIVE_BUSINESS_ID )
			);

			delete_option( WC_Payever_Helper::PAYEVER_ISSET_LIVE );
			delete_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_SECRET );
			delete_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_ID );
			delete_option( WC_Payever_Helper::PAYEVER_LIVE_BUSINESS_ID );

			self::synchronize_payment_options();

			set_transient(
				'payever_success_msg',
				__(
					'You reseted live API keys and your settings have been synchronized with your payever account.',
					'payever-woocommerce-gateway'
				)
			);
		} catch ( Exception $e ) {
			set_transient(
				'payever_error_msg',
				__(
					'You reseted live API keys and your settings have been synchronized with your payever account.',
					'payever-woocommerce-gateway'
				)
			);
		}

		self::update_settings_page();
	}

	/**
	 * Synchronization admin notices
	 *
	 * phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent
	 * phpcs:disable Squiz.PHP.EmbeddedPhp
	 */
	public static function synchronization_admin_notices() {
		$error_msg = get_transient( 'payever_error_msg' );
		if ( $error_msg ) :
			?>
			<div class="error">
				<p><?php echo esc_html( sanitize_text_field( $error_msg ) ); ?></p>
			</div>
			<?php
			delete_transient( 'payever_error_msg' );
		endif;

		$success_msg = get_transient( 'payever_success_msg' );
		if ( $success_msg ) :
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( sanitize_text_field( $success_msg ) ); ?></p>
			</div>
			<?php
			delete_transient( 'payever_success_msg' );
		endif;
	}

	/**
	 * Checks migration status and displays error
	 */
	public static function check_migration() {
		if ( get_option( WC_Payever_Helper::PAYEVER_LAST_MIGRATION_FAILED ) ) { ?>
			<div class="error">
				<p>
					<?php
						printf(
							/* translators: %s: error message  */
							esc_html( '%1$s' ),
							esc_html( get_option( WC_Payever_Helper::PAYEVER_LAST_MIGRATION_FAILED ) )
						)
					?>
				</p>
			</div>
		<?php }
	}

	/**
	 * @throws Exception
	 *
	 * phpcs:enable Generic.WhiteSpace.DisallowSpaceIndent
	 * phpcs:enable Squiz.PHP.EmbeddedPhp
	 */
	private static function synchronize_payment_options() {
		WC_Payever_Api::reload();

		$params = explode( '_', get_locale() );
		$params = array(
			'_locale'   => $params[0],
			'_currency' => get_woocommerce_currency(),
		);

		$payment_options = WC_Payever_Api::get_instance()
										->get_payments_api_client()
										->listPaymentOptionsWithVariantsRequest( $params );

		$responseResult           = $payment_options->getResponseEntity()->getResult();
		$prepared_payment_options = self::prepare_payment_options( $responseResult );

		$active_payments               = array();
		$address_equality_payments     = array();
		$shipping_not_allowed_payments = array();
		foreach ( $prepared_payment_options as $code => $payever_payment ) {
			update_option( 'woocommerce_payever_' . $code . '_settings', $payever_payment );
			$active_payments[ WC_Payever_Helper::instance()->add_payever_prefix( $code ) ] = $payever_payment['title'];

			if ( $payever_payment['address_equality'] ) {
				$address_equality_payments[] = $payever_payment['variant_id'];
			}

			if ( ! $payever_payment['allowed_shipping'] ) {
				$shipping_not_allowed_payments[] = $payever_payment['variant_id'];
			}
		}

		update_option( WC_Payever_Helper::PAYEVER_ACTIVE_PAYMENTS, $active_payments );
		update_option( WC_Payever_Helper::PAYEVER_ADDRESS_EQUALITY_METHODS, $address_equality_payments );
		update_option( WC_Payever_Helper::PAYEVER_CHECK_VARIANT_FOR_ADDRESS_EQUALITY, true );
		update_option( WC_Payever_Helper::PAYEVER_SHIPPING_NOT_ALLOWED_METHODS, $shipping_not_allowed_payments );
		delete_option( WC_Payever_Helper::PAYEVER_LAST_MIGRATION_FAILED );

		WC_Payever_Api::get_instance()->get_plugins_api_client()->registerPlugin();
	}

	/**
	 * Updates active finance express widgets for current business
	 *
	 * @throws Exception
	 */
	private static function update_payment_widgets() {
		$woo_payment_widgets = array();
		$api_payment_widgets = WC_Payever_Api::get_instance()
										->get_payment_widgets_api_client()
										->getWidgets()
										->getResponseEntity()
										->getResult();

		foreach ( $api_payment_widgets as $api_payment_widget ) {
			if ( $api_payment_widget->getIsVisible() ) {
				$woo_payment_widgets[ $api_payment_widget->getId() ] = array(
					'business_id' => $api_payment_widget->getBusinessId(),
					'checkout_id' => $api_payment_widget->getCheckoutId(),
					'type'        => $api_payment_widget->getType(),
				);

				foreach ( $api_payment_widget->getPayments() as $api_payment_payments ) {
					if ( $api_payment_payments->getEnabled() ) {
						$woo_payment_widgets[ $api_payment_widget->getId() ]['payments'][] = $api_payment_payments->getPaymentMethod();
					}
				}
			}
		}

		update_option( 'woocommerce_payever_payment_widgets', wp_json_encode( $woo_payment_widgets ) );
	}

	/**
	 * @param $paymentOptions
	 *
	 * @return array
	 * @throws Exception
	 */
	private static function prepare_payment_options( $paymentOptions ) {
		$prepared_payment_options = array();
		$converted_options        = PaymentOptionConverter::convertPaymentOptionVariants( $paymentOptions );

		$wl_supported_methods = self::get_wl_supported_payment_methods();

		foreach ( $converted_options as $payment_option ) {
			if ( $wl_supported_methods && ! in_array( $payment_option->getPaymentMethod(), $wl_supported_methods ) ) {
				continue;
			}

			$origin_payment_method = $payment_option->getPaymentMethod();
			$payment_method        = isset( $prepared_payment_options[ $origin_payment_method ] ) ?
				sprintf( '%1$s-%2$s', $origin_payment_method, $payment_option->getVariantId() ) :
				$origin_payment_method;

			$variantName = ! empty( $payment_option->getVariantName() ) ? ' - ' . $payment_option->getVariantName() : '';

			$current_option                              = get_option( 'woocommerce_payever_' . $payment_method . '_settings' );
			$prepared_payment_options[ $payment_method ] = self::get_payment_options( $payment_option, $variantName, $origin_payment_method, $current_option );

			if ( WC_Payever_Helper::instance()->is_santander( $payment_method ) ) {
				$current_santander_currency                                     = $payment_option->getOptions()->getCurrencies()[0];
				$prepared_payment_options[ $payment_method ]['min_order_total'] = ceil(
					self::convert_to_currency(
						$payment_option->getMin(),
						$current_santander_currency
					)
				);
				$prepared_payment_options[ $payment_method ]['max_order_total'] = ceil(
					self::convert_to_currency(
						$payment_option->getMax(),
						$current_santander_currency
					)
				);
			}
		}

		return $prepared_payment_options;
	}

	/**
	 * @param $payment_option
	 * @param $variantName
	 * @param $origin_payment_method
	 * @param $current_option
	 * @return array
	 * @throws Exception
	 */
	private static function get_payment_options( $payment_option, $variantName, $origin_payment_method, $current_option ) {
		/* translators: %s: Payment option name */
		$options = array(
			'enabled'          => ( $payment_option->getStatus() ) ? 'yes' : 'no',
			'title'            => sprintf(
				/* translators: %s: Payment option title */
				esc_html__( '%1$s %2$s', 'payever-woocommerce-gateway' ),
				esc_html( $payment_option->getName() ),
				esc_html( $variantName )
			),
			'method_code'      => $origin_payment_method,
			'variant_id'       => $payment_option->getVariantId(),
			'variant_name'     => $payment_option->getVariantName(),
			'description'      => sprintf(
				/* translators: %s: description */
				esc_html( '%1$s' ),
				esc_html( trim( wp_strip_all_tags( $payment_option->getDescriptionOffer() ) ) )
			),
			'icon'             => self::get_image_url( $origin_payment_method, $payment_option->getThumbnail1() ),
			'accept_fee'       => ( $payment_option->getAcceptFee() ) ? 'yes' : 'no',
			'variable_fee'     => $payment_option->getVariableFee(),
			'fee'              => $payment_option->getFixedFee(),
			'address_equality' => $payment_option->getShippingAddressEquality(),
			'allowed_shipping' => $payment_option->getShippingAddressAllowed(),
			'currencies'       => $payment_option->getOptions()->getCurrencies(),
			'countries'        => $payment_option->getOptions()->getCountries(),
			'min_order_total'  => ceil( $payment_option->getMin() ),
			'max_order_total'  => ceil( $payment_option->getMax() ),
		);

		if ( $payment_option->isRedirectMethod() || 'sofort' === $origin_payment_method ) {
			$options['is_redirect_method'] = 'yes';
		}

		if ( ! is_array( $current_option ) ) {
			return $options;
		}
		$options['title']       = sprintf(
			/* translators: %s: title */
			esc_html( '%1$s' ),
			esc_html( trim( $current_option['title'] ) )
		);
		$options['description'] = sprintf(
			/* translators: %s: description */
			esc_html( '%1$s' ),
			esc_html( trim( $current_option['description'] ) )
		);

		return $options;
	}

	/**
	 * @return array
	 */
	private static function get_wl_supported_payment_methods() {
		$wl_plugin = self::get_white_label_plugin();

		return $wl_plugin ? $wl_plugin->getSupportedMethods() : array();
	}

	/**
	 * @return \Payever\Sdk\Core\Http\ResponseEntity
	 */
	private static function get_white_label_plugin() {
		try {
			$wl_plugin_api_client = WC_Payever_Api::get_instance()->get_white_label_plugin_api_client();
			return $wl_plugin_api_client
				->getWhiteLabelPlugin( WC_Payever_Helper::PLUGIN_CODE, WC_Payever_Helper::SHOP_SYSTEM )
				->getResponseEntity();
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	/**
	 * @param mixed $upgrader_object
	 * @param array $options
	 */
	public static function payever_migration_process( $upgrader_object, $options ) {
		$migration = (bool) ( get_option( WC_Payever_Helper::PAYEVER_MIGRATION ) );
		if ( $migration ) {
			return;
		}

		$our_plugin = plugin_basename( __FILE__ );
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin === $our_plugin ) {
					self::migration( $upgrader_object );
				}
			}
		}
	}

	/**
	 * @return void
	 */
	public static function migration() {
		$migration = (bool) ( get_option( WC_Payever_Helper::PAYEVER_MIGRATION ) );

		if ( $migration
			|| empty( self::$plugin_settings[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ] )
			|| empty( self::$plugin_settings[ WC_Payever_Helper::PAYEVER_CLIENT_ID ] )
			|| empty( self::$plugin_settings[ WC_Payever_Helper::PAYEVER_CLIENT_SECRET ] )
		) {
			return;
		}

		try {
			$paymentOptions = WC_Payever_Api::get_instance()->get_payments_api_client()->listPaymentOptionsWithVariantsRequest();
			if ( $paymentOptions->isSuccessful() ) {
				$response_result          = $paymentOptions->getResponseEntity()->getResult();
				$prepared_payment_options = self::prepare_payment_options( $response_result );
				$active_payments          = array();
				foreach ( $prepared_payment_options as $payment_method => $payment_option ) {
					$active_payments[ WC_Payever_Helper::instance()->add_payever_prefix( $payment_method ) ] = $payment_option['title'];
					update_option( 'woocommerce_payever_' . $payment_method . '_settings', $payment_option );
				}
				update_option( WC_Payever_Helper::PAYEVER_ACTIVE_PAYMENTS, $active_payments );
				update_option( WC_Payever_Helper::PAYEVER_MIGRATION, 1 );
				delete_option( WC_Payever_Helper::PAYEVER_LAST_MIGRATION_FAILED );
			}
		} catch ( Exception $e ) {
			$error_message = sprintf(
				/* translators: %s: sync link */
				__(
					'ATTENTION! Database migration failed for payever payments plugin. You need to <a href="%1$s">synchronize</a> settings manually, otherwhise payever payment methods won\'t be shown in checkout. %2$s',
					'payever-woocommerce-gateway'
				),
				admin_url( 'admin.php?page=wc-settings&tab=payever_settings' ),
				$e->getMessage()
			);

			update_option( WC_Payever_Helper::PAYEVER_LAST_MIGRATION_FAILED, $error_message );
			WC_Payever_Api::get_instance()->get_logger()->error( $error_message );
		}
	}

	/**
	 * Saves and returns image url
	 *
	 * @param $payever_payment
	 * @param $thumbnail
	 *
	 * @return string
	 * @throws Exception
	 */
	private static function get_image_url( $payever_payment, $thumbnail ) {
		$upload_dir      = wp_upload_dir();
		$payever_dirname = $upload_dir['basedir'] . '/payever';
		if ( ! file_exists( $payever_dirname ) ) {
			wp_mkdir_p( $payever_dirname );
		}

		$filename  = WC_Payever_Helper::instance()->add_payever_prefix( $payever_payment ) . '.png';
		$save_path = $payever_dirname . '/' . $filename;
		$saved_url = $upload_dir['baseurl'] . '/payever/' . $filename;

		try {
			WC_Payever_Api::get_instance()->get_plugins_api_client()->getHttpClient()->download( $thumbnail, $save_path );
		} catch ( \Exception $e ) {
			WC_Payever_Api::get_instance()->get_logger()->error( $e );
		}

		if ( ! file_exists( $save_path ) ) {
			$saved_url = WC_PAYEVER_PLUGIN_URL . '/assets/images/' . $filename;
		}

		return $saved_url;
	}

	/**
	 * Contains workaround for php5.6 to avoid error
	 * "PHP Fatal error:  Access to undeclared static property: WC_Payever_Synchronization::$currency_rates"
	 *
	 * @return array
	 * @throws Exception
	 */
	private static function get_currency_rates() {
		if ( empty( self::$currency_rates ) ) {
			$response = WC_Payever_Api::get_instance()
				->get_payments_api_client()
				->getCurrenciesRequest()
				->getResponseEntity()
				->getResult();

			foreach ( $response as $currency ) {
				self::$currency_rates[ $currency->getCode() ] = $currency->getRate();
			}
		}

		return self::$currency_rates;
	}

	private static function convert_to_currency( $amount, $currency ) {
		$wc_currency = get_woocommerce_currency();
		if ( $wc_currency === $currency ) {
			return $amount;
		}

		$currency_rates = self::get_currency_rates();
		if ( empty( $currency_rates[ $wc_currency ] ) ) {
			return $amount;
		}

		return $currency_rates[ $currency ] / $currency_rates[ $wc_currency ] * $amount;
	}

	/**
	 * toggle_subscription function
	 */
	public static function toggle_subscription() {
		static $subscription_manager;
		if ( null === $subscription_manager ) {
			$subscription_manager = new WC_Payever_Subscription_Manager();
		}
		$is_enabled = $subscription_manager->toggle_subscription();

		set_transient(
			'payever_success_msg',
			sprintf(
				/* translators: %s: sync status */
				__( 'Synchronization has been %s', 'payever-woocommerce-gateway' ),
				( $is_enabled ? 'enabled' : 'disabled' )
			)
		);

		wp_redirect( admin_url( 'admin.php?page=wc-settings&tab=payever_settings&section=products_app' ) );

		PHP_SAPI !== 'cli' && die();
	}

	/**
	 * @return void
	 */
	public static function export_products() {
		$api_wrapper = new WC_Payever_API_Wrapper();
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_ajax_export_products' ) ) {
			$api_wrapper->get_logger()->error( __( 'Invalid wp_ajax_export_products nonce.', 'payever-woocommerce-gateway' ) );
			exit;
		}
		$export_manager = new WC_Payever_Export_Manager();
		$current_page   = isset( $_POST['page'] ) ? (int) wc_clean( wp_unslash( $_POST['page'] ) ) : 0;
		$aggregate      = isset( $_POST['aggregate'] ) ? (int) wc_clean( wp_unslash( $_POST['aggregate'] ) ) : 0;
		$result         = $export_manager->export( $current_page, $aggregate );
		$next_page      = $export_manager->get_next_page();
		$aggregate      = $export_manager->get_aggregate();
		$finished       = ! $next_page;
		$errors         = $export_manager->get_errors();
		$pages          = $export_manager->get_total_pages();
		$message        = implode( ', ', $errors );
		$status         = 'error';
		if ( $result ) {
			$status  = $finished ? 'success' : 'in_process';
			/* translators: %s: products number */
			$message = $finished
				? sprintf( /* translators: %d: number */__( '%d products have been sent to payever', 'payever-woocommerce-gateway' ), $aggregate )
				: sprintf( /* translators: %d: number */__( 'Exporting packet %1$d of %2$d ...', 'payever-woocommerce-gateway' ), $current_page, $pages );
		}
		wp_send_json(
			array(
				'status'    => $status,
				'message'   => $message,
				'next_page' => $next_page,
				'aggregate' => $aggregate,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function payever_synchronization_incoming() {
		$action       = self::get_subscription_param( WC_Payever_Subscription_Manager::PARAM_ACTION );
		$external_id  = self::get_subscription_param( WC_Payever_Subscription_Manager::PARAM_EXTERNAL_ID );
		$payload      = wp_kses_post( sanitize_text_field( ( new WC_Payever_WP_Wrapper() )->get_contents( 'php://input' ) ) );  // WPCS: input var ok, CSRF ok.
		$sync_manager = new WC_Payever_Import_Manager();
		$result       = $sync_manager->import( $action, $external_id, $payload );
		$errors       = $sync_manager->get_errors();
		$result       = $result && ! $errors;
		$status_code  = $result ? 200 : 400;

		wp_send_json(
			array(
				'status'  => $result ? 'success' : 'error',
				'message' => $errors ? implode( ', ', $errors ) : __( 'Action processed.', 'payever-woocommerce-gateway' ),
			),
			$status_code
		);
	}

	public static function uninstall() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}options WHERE `option_name` LIKE %s", '%payever_%' ) );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}woocommerce_payever_synchronization_queue" );

		wp_cache_flush();
	}

	public static function get_subscription_param( $key, $default_value = '' ) {
		return ! empty( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default_value; // WPCS: input var ok, CSRF ok.
	}
}
