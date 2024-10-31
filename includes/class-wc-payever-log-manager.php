<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Log_Manager' ) ) {
	return;
}

class WC_Payever_Log_Manager {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;

	const ZIP_FILE_NAME_PATTERN = 'woocommerce-logs-%s-%s.zip';

	/**
	 * @var string
	 */
	private $wc_logs_dir;

	/**
	 * @param WC_Payever_WP_Wrapper|null $wp_wrapper
	 */
	public function __construct( $wp_wrapper = null ) {
		if ( null !== $wp_wrapper ) {
			$this->set_wp_wrapper( $wp_wrapper );
		}

		// Function `wc_get_log_file_path` is deprecated since 8.6.0
		$this->wc_logs_dir = function_exists( 'wc_get_log_file_path' ) ?
			dirname( wc_get_log_file_path( 'payever' ) ) : WP_CONTENT_DIR . '/uploads/wc-logs';

		$permission_closure = function () {
			$access_token = null;
			$headers = WC_Payever_Helper::instance()->get_request_headers();
			if ( isset( $headers['Authorization'] ) ) {
				$access_token = trim( str_replace( 'Bearer', '', $headers['Authorization'] ) );
			}

			// Lookup Token parameter as failback
			if ( empty( $access_token ) && ! empty( $_GET['token'] ) ) {
				$access_token = sanitize_text_field( wp_unslash( $_GET['token'] ) ); // WPCS: input var ok, CSRF ok.
			}

			if ( empty( $access_token ) ) {
				return false;
			}

			return WC_Payever_Api::get_instance()
				->get_third_party_plugins_api_client()
				->validateToken(
					get_option( WC_Payever_Helper::PAYEVER_BUSINESS_ID ),
					$access_token
				);
		};
		// @codingStandardsIgnoreStart
		// Add rest api init
		// @see /wp-json/payever/v1/logs
		$this->get_wp_wrapper()->add_action( 'rest_api_init', function () use ( $permission_closure ) {
			register_rest_route( 'payever/v1', '/logs', array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'api_get_logs' ),
				'permission_callback' => $permission_closure
			) );
		} );

		// @see /wp-json/payever/v1/logs/shop
		$this->get_wp_wrapper()->add_action( 'rest_api_init', function () use ( $permission_closure ) {
			register_rest_route( 'payever/v1/logs', '/shop', array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'process_request' ),
				'permission_callback' => $permission_closure,
				'args' => array()
			) );
		} );
		// @codingStandardsIgnoreEnd

		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_download_logs',
			array( $this, 'api_download_logs' )
		);
	}

	/**
	 * Retrieves the content of the "payever.log" file located in the WooCommerce logs directory.
	 *
	 * @return array An array containing the logs from the "payever.log" file.
	 */
	public function api_get_logs() {
		$logs = array();

		$log_file = $this->wc_logs_dir . '/payever.log';
		if ( file_exists( $log_file ) ) {
			$logs = explode( "\n", $this->get_wp_wrapper()->get_contents( $log_file ) );
		}

		return $logs;
	}

	/**
	 * Get Logs in API response.
	 *
	 * @see /wc-api/payever_download_logs/
	 * @return void
	 */
	public function api_download_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			exit( 'Access denied.' );
		}

		if ( ! extension_loaded( 'zip' ) ) {
			throw new \Exception(
				esc_html__( 'zip extension is required to perform this operation.', 'payever-woocommerce-gateway' )
			);
		}

		$this->render_log_file( true );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return void
	 */
	public function process_request( $request ) {
		$attributes = $request->get_attributes();
		$this->render_log_file( ...$attributes['args'] );
	}

	/**
	 * @param $system_log_flag
	 * @return void
	 * @throws Exception
	 */
	private function render_log_file( $system_log_flag ) {
		$business_uuid = get_option( WC_Payever_Helper::PAYEVER_BUSINESS_ID );

		$log_files = array(
			$this->wc_logs_dir . DIRECTORY_SEPARATOR . 'payever.log',
		);

		if ( $system_log_flag ) {
			$log_files = glob( $this->wc_logs_dir . DIRECTORY_SEPARATOR . '*.log' ) ?: array();
		}

		// Generate the zip
		$zip = new ZipArchive();
		$zip_file_name = sprintf( self::ZIP_FILE_NAME_PATTERN, $business_uuid, date_i18n( 'Y-m-d-H-i-s' ) );
		$zip_file_path = $this->wc_logs_dir . DIRECTORY_SEPARATOR . $zip_file_name;

		if ( file_exists( $zip_file_path ) ) {
			wp_delete_file( $zip_file_path );
		}

		if ( ! $zip->open( $zip_file_path, ZipArchive::CREATE ) ) {
			throw new Exception( 'Could not open archive for creating zip.' );
		}

		foreach ( $log_files as $file ) {
			$zip->addFile( $file, basename( $file ) );
		}
		$zip->close();

		// Force browser to download the zip
		header( 'Content-type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $zip_file_name );
		header( 'Content-length: ' . filesize( $zip_file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		WC_Download_Handler::readfile_chunked( $zip_file_path );

		// Remove the generated zip
		wp_delete_file( $zip_file_path );
		exit();
	}
}
