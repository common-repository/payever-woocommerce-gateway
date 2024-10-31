<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Admin_Settings' ) ) {
	return;
}

if ( ! class_exists( '\WC_Settings_Page' ) ) {
	$relative_filename = str_replace(
		'/',
		DIRECTORY_SEPARATOR,
		'/woocommerce/includes/admin/settings/class-wc-settings-page.php'
	);
	$file              = WP_PLUGIN_DIR . $relative_filename;
	if ( file_exists( $file ) ) {
		include_once $file;
	} else {
		exit;
	}
}

/**
 * payever Admin Global Settings Payment
 *
 * This file is used for creating the payever global configuration and payever
 * administration portal in shop backend.
 *
 * Copyright (c) payever
 *
 * This script is only free to the use for merchants of payever. If
 * you have found this script useful a small recommendation as well as a
 * comment on merchant form would be greatly appreciated.
 *
 * @class       WC_Payever_Admin_Settings
 */
class WC_Payever_Admin_Settings extends WC_Settings_Page {

	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	const PAYEVER_OPTION_PREFIX = 'payever';
	const API2_VERSION_OPTION_VALUE = 2;
	const API3_VERSION_OPTION_VALUE = 3;

	/**
	 * @var string
	 */
	private $element_width = 'width:25em;';

	/**
	 * Setup settings class
	 */
	public function __construct( $wp_wrapper = null ) {

		if ( null !== $wp_wrapper ) {
			$this->set_wp_wrapper( $wp_wrapper );
		}

		$this->id    = 'payever_settings';
		$this->label = 'payever Checkout';

		$this->get_wp_wrapper()->add_filter(
			'woocommerce_settings_tabs_array',
			array(
				$this,
				'add_settings_page',
			),
			50
		);
		$this->get_wp_wrapper()->add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		$this->get_wp_wrapper()->add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_payever_synchronization_button',
			array(
				$this,
				'synchronization_button',
			)
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_payever_set_sandbox_mode',
			array(
				$this,
				'set_sandbox_mode',
			)
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_payever_embedded_support',
			array(
				$this,
				'embedded_support',
			)
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_payever_toggle_subscription',
			array(
				$this,
				'toggle_subscription',
			)
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_payever_fe_synchronization_button',
			array(
				$this,
				'fe_synchronization_button',
			)
		);

		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_field_download_logs_button',
			array(
				$this,
				'download_logs_button',
			)
		);

		// only add this if you need to add sections for your settings tab
		$this->get_wp_wrapper()->add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
	}

	/**
	 * phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent
	 * phpcs:disable Squiz.PHP.EmbeddedPhp
	 * phpcs:disable WordPress.WhiteSpace.PrecisionAlignment
	 */
	public function embedded_support() {
		$this->get_wp_wrapper()->wp_enqueue_script(
			'payever-chat',
			WC_PAYEVER_PLUGIN_URL . '/assets/js/admin/chat.js'
		);
		$this->get_wp_wrapper()->wp_add_inline_script(
			'payever-chat',
			'if (typeof PAYEVER_CONTAINER === "undefined") {
				var PAYEVER_CONTAINER = { translations: {} };
			}
			PAYEVER_CONTAINER.translations["chat_with_us"] = "' . esc_html__( 'Need help? Chat with us!', 'payever-woocommerce-gateway' ) . '";
			PAYEVER_CONTAINER.translations["loading_chat"] = "' . esc_html__( 'Loading chat...', 'payever-woocommerce-gateway' ) . '";'
		);
		?>

		<button class="button" id="pe_chat_btn"><?php esc_html_e( 'Need help? Chat with us!', 'payever-woocommerce-gateway' ); ?></button>
		<p><?php esc_html_e( 'Our free english and german speaking support is there for you from Monday to Friday, 8am-7pm. If you want to report a specific technical problem, please include your WordPress, WooCommerce versions and payever plugin version in your message to us, and attach your plugin logs to it.', 'payever-woocommerce-gateway' ); ?></p>
	<?php }

	/**
	 * Output synchronization_button settings.
	 */
	public function synchronization_button() {
		?>
		<tr class="">
			<th class="titledesc" scope="row"><?php esc_html_e( 'Synchronization', 'payever-woocommerce-gateway' ); ?></th>
			<td class="forminp">
				<div class="payever_synchronize_wrapper">
					<input id="payever_synchronize"
							type="button"
							class="button-primary"
							onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_synchronization' ) ); ?>'"
							value="<?php esc_attr_e( 'Synchronize', 'payever-woocommerce-gateway' ); ?>"
							name="payever_synchronization_button">
					<p class="description">
						<?php esc_attr_e( 'You need to save settings before synchronization', 'payever-woocommerce-gateway' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output fe_synchronization_button settings.
	 */
	public function fe_synchronization_button() {
		?>
		<tr class="">
			<th class="titledesc" scope="row"><?php esc_html_e( 'Express widget synchronization', 'payever-woocommerce-gateway' ); ?></th>
			<td class="forminp">
				<div class="payever_fe_synchronize_wrapper">
					<input id="payever_fe_synchronize"
							type="button"
							class="button-primary"
							onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_fe_synchronization' ) ); ?>'"
							value="<?php esc_attr_e( 'Synchronize express widgets', 'payever-woocommerce-gateway' ); ?>"
							name="payever_fe_synchronization_button">
					<p class="description">
						<?php esc_html_e( 'You have to have entered your payever api keys in the general settings of plugin', 'payever-woocommerce-gateway' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	public function download_logs_button() {
		?>
		<tr valign="top" class="">
			<th class="titledesc" scope="row"><?php esc_html_e( 'Download logs', 'payever-woocommerce-gateway' ); ?></th>
			<td class="forminp">
				<fieldset>
					<label for="payever_download_logs">
						<input type="button" class="button-primary"
								onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_download_logs' ) ); ?>'"
								value="<?php esc_attr_e( 'Download logs', 'payever-woocommerce-gateway' ); ?>"
								name="payever_download_logs" id="payever_download_logs">
						<span class="description">&nbsp;</span>
					</label>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output toggle_subscription settings.
	 */
	public function toggle_subscription() {
		$this->get_wp_wrapper()->wp_enqueue_script(
			'payever-export_products',
			WC_PAYEVER_PLUGIN_URL . '/assets/js/admin/export_products.js'
		);
		$this->get_wp_wrapper()->wp_add_inline_script(
			'payever-export_products',
			'if (typeof PAYEVER_CONTAINER === "undefined") {
				var PAYEVER_CONTAINER = { translations: {} };
			}
			PAYEVER_CONTAINER.translations["preparing_exporting_products"] = "' . esc_html__( 'Preparing exporting products...', 'payever-woocommerce-gateway' ) . '";
			PAYEVER_CONTAINER.translations["something_went_wrong"] = "' . esc_html__( 'Something went wrong', 'payever-woocommerce-gateway' ) . '";
			PAYEVER_CONTAINER.export_products_nonce = "' . esc_html( wp_create_nonce( 'wp_ajax_export_products' ) ) . '";'
		);
		?>
		<tr class="">
			<th class="titledesc" scope="row"><?php esc_attr_e( 'Synchronization', 'payever-woocommerce-gateway' ); ?></th>
			<td class="forminp">
				<div class="payever_subscription_wrapper">
					<input id="payever_toggle_subscription"
							type="button"
							class="button-primary"
							onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_toggle_subscription' ) ); ?>'"
							value="<?php $this->get_helper()->is_products_sync_enabled() ? esc_attr_e( 'Disable', 'payever-woocommerce-gateway' ) : esc_attr_e( 'Enable', 'payever-woocommerce-gateway' ); ?>"
							name="payever_toggle_subscription">
					<input id="payever_export_products"
							type="button"
							class="button-primary"
							value="<?php esc_attr_e( 'Export WooCommerce products', 'payever-woocommerce-gateway' ); ?>"
							name="payever_export_products">
					<div id="export_status_messages"></div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output set_sandbox_mode settings.
	 */
	public static function set_sandbox_mode() {
		$isset_live = get_option( WC_Payever_Helper::PAYEVER_ISSET_LIVE )
			&& ! empty( get_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_SECRET ) )
			&& ! empty( get_option( WC_Payever_Helper::PAYEVER_LIVE_CLIENT_ID ) )
			&& ! empty( get_option( WC_Payever_Helper::PAYEVER_LIVE_BUSINESS_ID ) );

		if ( $isset_live ) :
			?>
			<tr class="">
				<th class="titledesc" scope="row"><?php esc_attr_e( 'Reset live API keys', 'payever-woocommerce-gateway' ); ?></th>
				<td class="forminp">
					<div class="payever_set_live_api">
						<input id="payever_set_live_api"
								type="button"
								class="button-primary"
								onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_set_live_api_keys' ) ); ?>'"
								value="<?php esc_attr_e( 'Reset live API keys', 'payever-woocommerce-gateway' ); ?>"
								name="payever_set_api_keys">
						<p class="description">
							<?php esc_attr_e( 'Reset live API keys', 'payever-woocommerce-gateway' ); ?>
						</p>
					</div>
				</td>
			</tr>
		<?php endif;?>
		<?php if ( ! $isset_live ) :?>
			<tr class="">
				<th class="titledesc"
					scope="row"><?php esc_attr_e( 'Set up sandbox API Keys', 'payever-woocommerce-gateway' ); ?>
				</th>
				<td class="forminp">
					<div class="payever_set_sandbox_api_wrapper">
						<input id="payever_set_sandbox_api"
								type="button"
								class="button-primary"
								onClick="location.href='<?php echo esc_attr( WC()->api_request_url( 'payever_set_sandbox_api_keys' ) ); ?>'"
								value="<?php esc_attr_e( 'Set up sandbox API Keys', 'payever-woocommerce-gateway' ); ?>"
								name="payever_set_api_keys">
						<p class="description">
							<?php esc_attr_e( 'Set up sandbox API Keys', 'payever-woocommerce-gateway' ); ?>
						</p>
					</div>
				</td>
			</tr>
			<?php
		endif;
	}

	/**
	 * @return array|mixed|void
	 * phpcs:enable Generic.WhiteSpace.DisallowSpaceIndent
	 * phpcs:enable Squiz.PHP.EmbeddedPhp
	 * phpcs:enable WordPress.WhiteSpace.PrecisionAlignment
	 */
	public function get_sections() {
		$sections = array(
			''             => __( 'Default setting', 'payever-woocommerce-gateway' ),
			'products_app' => __( 'Products App', 'payever-woocommerce-gateway' ),
			'fe_widget'    => __( 'Express Widget', 'payever-woocommerce-gateway' ),
		);

		return $this->get_wp_wrapper()->apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array
	 *
	 * @param string $current_section Optional. Defaults to empty string.
	 *
	 * @return array Array of settings
	 * @since 1.0.0
	 */
	public function get_settings( $current_section = '' ) {
		$settings = $this->get_default_settings();
		if ( 'products_app' === $current_section ) {
			$settings = $this->get_products_app_settings();
		} elseif ( 'fe_widget' === $current_section ) {
			$settings = $this->get_fe_widget_settings();
		}

		/**
		 * Filter payever Settings
		 *
		 * @param array $settings Array of the plugin settings
		 */
		return $this->get_wp_wrapper()->apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * @return mixed|null
	 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
	 */
	private function get_default_settings() {
		$statuses = wc_get_order_statuses();
		if ( ! $this->get_wp_wrapper()->get_option( self::PAYEVER_OPTION_PREFIX . '_shipped_status' ) ) {
			$this->get_wp_wrapper()->update_option( self::PAYEVER_OPTION_PREFIX . '_shipped_status', WC_Payever_Helper::DEFAULT_SHIPPED_STATUS );
		}

		return $this->get_wp_wrapper()->apply_filters(
			'woocommerce_' . $this->id,
			array(
				array(
					'title' => 'payever Checkout',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_global_settings',
					'desc'  => __( 'payever_plugin_description', 'payever-woocommerce-gateway' ),
					'type'  => 'title',
				),
				array(
					'title' => '',
					'desc'  => '',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
				),
				array(
					'title'    => __( 'Enable / Disable', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Enable payever payment gateway', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_enabled',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
					'default'  => '',
				),
				array(
					'title'   => __( 'Mode', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Choose mode', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_environment',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => $this->get_helper()->get_payever_modes(),
				),
				array(
					'title' => __( 'Client ID *', 'payever-woocommerce-gateway' ),
					'desc'  => __( 'Client ID key', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_client_id',
					'css'   => $this->element_width,
					'type'  => 'text',
				),
				array(
					'title' => __( 'Client Secret *', 'payever-woocommerce-gateway' ),
					'desc'  => __( 'Client secret key', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_client_secrect',
					'css'   => $this->element_width,
					'type'  => 'text',
				),
				array(
					'title'   => __( 'Business UUID *', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Business UUID', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_slug',
					'css'     => $this->element_width,
					'type'    => 'text',
					'default' => '',
				),
				array(
					'title' => __( 'Set up sandbox API Keys', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_set_sandbox_mode',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_set_sandbox_mode',
				),
				array(
					'title' => __( 'Synchronization', 'payever-woocommerce-gateway' ),
					'desc'  => __( 'Synchronization', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_synchronization',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_synchronization_button',
				),
				array(
					'title'    => __( 'Display only active payment methods in settings', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Display only active payment methods in settings', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_display_active_payments',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
					'default'  => 'yes',
				),
				array(
					'title'    => __( 'Display payment name', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Display payment name', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_display_payment_name',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
					'default'  => 'yes',
				),
				array(
					'title'    => __( 'Display payment icon', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Display payment icon', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_display_payment_icon',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
				),
				array(
					'title'    => __( 'Display payment description', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Take description automatically', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_display_payment_description',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
				),
				array(
					'title'   => __( 'Default language in checkout', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Choose default language in checkout', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_languages',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => array(
						''      => 'None',
						'store' => 'Use store locale',
						'en'    => 'English',
						'de'    => 'Deutsch',
						'es'    => 'Español',
						'no'    => 'Norsk',
						'da'    => 'Dansk',
						'sv'    => 'Svenska',
					),
				),
				array(
					'title'   => __( 'Shipped status', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Choose shipped status', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_shipped_status',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => $statuses,
					'default' => WC_Payever_Helper::DEFAULT_SHIPPED_STATUS,
				),
				array(
					'title'    => __( 'Redirect to payever', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Check to get redirected to payever on a new page or leave blank to use an iframe.', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_redirect_to_payever',
					'css'      => 'width:25em;',
					'type'     => WC_Payever_Helper::PAYEVER_ALLOW_IFRAME ? 'checkbox' : 'hidden',
					'default'  => ! WC_Payever_Helper::PAYEVER_ALLOW_IFRAME,
				),
				array(
					'title'   => __( 'Logging level', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_log_level',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => array(
						'debug' => 'Debug',
						'info'  => 'Info',
						'error' => 'Error',
					),
					'default' => 'info',
				),
				array(
					'title'   => __( 'Send logs via APM', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_log_diagnostic',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => array(
						0 => 'No',
						1 => 'Yes',
					),
					'default' => 0,
				),
				array(
					'title'   => __( 'API version', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_api_version',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => array(
						self::API3_VERSION_OPTION_VALUE => 'v3',
						self::API2_VERSION_OPTION_VALUE => 'v2',
					),
					'default' => self::API3_VERSION_OPTION_VALUE,
				),
				array(
					'title' => __( 'Download logs', 'payever-woocommerce-gateway' ),
					'desc'  => '<br/>' . __( 'Download logs', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_download_logs',
					'type'  => 'download_logs_button',
				),
			)
		);
	}

	private function get_fe_widget_settings() {
		return $this->get_wp_wrapper()->apply_filters(
			'woocommerce_fe_widget_settings',
			array(
				array(
					'title' => 'Express Widget',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_global_settings',
					'desc'  => '',
					'type'  => 'title',
				),
				array(
					'title' => '',
					'desc'  => '',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
				),
				array(
					'title'    => __( 'Enable / Disable on product single page', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Enable payever express widget on product single page', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_active_widget_on_single_page',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
					'default'  => '',
				),
				array(
					'title'    => __( 'Enable / Disable on cart', 'payever-woocommerce-gateway' ),
					'desc'     => __( 'Enable payever express widget on cart', 'payever-woocommerce-gateway' ),
					'desc_tip' => true,
					'id'       => self::PAYEVER_OPTION_PREFIX . '_active_widget_on_cart',
					'css'      => $this->element_width,
					'type'     => 'checkbox',
					'default'  => '',
				),
				array(
					'title' => __( 'Synchronize express widgets', 'payever-woocommerce-gateway' ),
					'desc'  => __( 'Express widget synchronization', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_fe_synchronization',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_fe_synchronization_button',
				),
				array(
					'title'   => __( 'Express widget type', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Please choose the express widget type', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_express_widget_type',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => $this->get_helper()->get_active_payever_widget_options(),
					'default' => '',
				),
				array(
					'title'   => __( 'Widget theme', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Only for wallet payments', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_express_widget_theme',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => $this->get_helper()->get_widget_themes(),
					'default' => WC_Payever_Helper::WIDGET_THEME_DARK,
				),
			)
		);
	}

	/**
	 * @return mixed|null
	 */
	private function get_products_app_settings() {
		return $this->get_wp_wrapper()->apply_filters(
			'woocommerce_products_app_settings',
			array(
				array(
					'title' => 'Products App',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_global_settings',
					'desc'  => '',
					'type'  => 'title',
				),
				array(
					'title' => '',
					'desc'  => '',
					'id'    => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_embedded_support',
				),
				array(
					'title' => __( 'Enable', 'payever-woocommerce-gateway' ),
					'desc'  => __( 'Synchronization', 'payever-woocommerce-gateway' ),
					'id'    => self::PAYEVER_OPTION_PREFIX . '_toggle_subscription',
					'type'  => self::PAYEVER_OPTION_PREFIX . '_toggle_subscription',
				),
				array(
					'title'   => __( 'Processing mode', 'payever-woocommerce-gateway' ),
					'desc'    => __( 'Using cron mode is highly recommended, but please make sure you have WooCommerce cron job installed. HTTP mode may decrease site performance dramatically on stock-related requests (mostly in checkout process).', 'payever-woocommerce-gateway' ),
					'id'      => self::PAYEVER_OPTION_PREFIX . '_products_synchronization_mode',
					'css'     => $this->element_width,
					'type'    => 'select',
					'options' => array(
						'instant' => __( 'Instantly on HTTP requests', 'payever-woocommerce-gateway' ),
						'cron'    => __( 'Cron queue processing', 'payever-woocommerce-gateway' ),
					),
				),
			)
		);
	}

	/**
	 * Output the settings
	 * @codeCoverageIgnore
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings
	 * @codeCoverageIgnore
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}
}
