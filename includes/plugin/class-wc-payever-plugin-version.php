<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Plugin_Version' ) ) {
	return;
}

use Payever\Sdk\Plugins\Http\ResponseEntity\PluginVersionResponseEntity;
use Psr\Log\LogLevel;

class WC_Payever_Plugin_Version {

	use WC_Payever_WP_Wrapper_Trait;

	/** @var WC_Payever_API_Wrapper */
	private $api_wrapper;

	/**
	 * @param WC_Payever_API_Wrapper $api_wrapper
	 *
	 * @return $this
	 * @codeCoverageIgnore
	 */
	public function set_api_wrapper( WC_Payever_API_Wrapper $api_wrapper ) {
		$this->api_wrapper = $api_wrapper;

		return $this;
	}

	/**
	 * @return WC_Payever_API_Wrapper
	 * @codeCoverageIgnore
	 */
	private function get_api_wrapper() {
		return null === $this->api_wrapper
			? $this->api_wrapper = new WC_Payever_API_Wrapper()
			: $this->api_wrapper;
	}

	/**
	 * Construct
	 *
	 * @param WC_Payever_WP_Wrapper|null $wp_wrapper
	 * @param WC_Payever_API_Wrapper|null $api_wrapper
	 */
	public function __construct( WC_Payever_WP_Wrapper $wp_wrapper = null, WC_Payever_API_Wrapper $api_wrapper = null ) {
		if ( null !== $wp_wrapper ) {
			$this->set_wp_wrapper( $wp_wrapper );
		}
		if ( null !== $api_wrapper ) {
			$this->set_api_wrapper( $api_wrapper );
		}
		if ( isset( $_REQUEST['page'] ) &&
			'wc-settings' === sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) && // WPCS: input var ok, CSRF ok.
			isset( $_REQUEST['tab'] ) &&
			'payever_settings' === sanitize_text_field( wp_unslash( $_REQUEST['tab'] ) ) // WPCS: input var ok, CSRF ok.
		) {
			$this->get_wp_wrapper()->add_action( 'init', array( $this, 'check_plugin_version' ), 5 );
		}

		$current_version = $this->get_wp_wrapper()->get_option( WC_Payever_Helper::KEY_PLUGIN_VERSION );
		if ( version_compare( WC_PAYEVER_PLUGIN_VERSION, (string) $current_version, '<' ) ) {
			$this->get_wp_wrapper()->add_action( 'admin_notices', array( $this, 'new_plugin_version_notice' ), 15 );
		}
	}

	public function check_plugin_version() {
		try {
			/** @var PluginVersionResponseEntity $latestVersion */
			$pluginsApiClient = $this->get_api_wrapper()->get_plugins_api_client();
			$pluginsApiClient->setHttpClientRequestFailureLogLevelOnce( LogLevel::NOTICE );
			$latestVersionEntity = $pluginsApiClient->getLatestPluginVersion()->getResponseEntity();
			$latestVersion = $latestVersionEntity->getVersion();
			if ( version_compare( $latestVersion, WC_PAYEVER_PLUGIN_VERSION, '>' ) ) {
				$this->get_wp_wrapper()->update_option(
					WC_Payever_Helper::KEY_PLUGIN_VERSION,
					$latestVersion
				);

				return $this->get_wp_wrapper()->add_action(
					'admin_notices',
					array(
						$this,
						'new_plugin_version_notice',
					),
					15
				);
			}
		} catch ( \Exception $exception ) {
			WC_Payever_Api::get_instance()->get_logger()->notice(
				sprintf( 'Plugin version checking failed: %s', $exception->getMessage() )
			);
		}

		return false;
	}

	public function new_plugin_version_notice() {
		echo '<div id="notice" class="notice notice-warning"><p>';

		echo wp_kses(
			sprintf(
				/* translators: %s: update ling */
				__( 'There is a new version of <b>payever - WooCommerce Gateway</b> available. <a href="%s">Update now</a>, please!', 'payever-woocommerce-gateway' ),
				esc_url( admin_url( 'plugins.php' ) )
			),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
				),
				'b' => array(),
			)
		);

		echo '</p></div>';
	}
}
