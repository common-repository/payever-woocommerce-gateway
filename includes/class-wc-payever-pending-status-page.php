<?php

defined( 'ABSPATH' ) || exit;

class WC_Payever_Pending_Status_Page {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Url_Helper_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Api_Payment_Service_Trait;

	public function __construct() {
		$this->get_wp_wrapper()->add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		$this->get_wp_wrapper()->add_action( 'the_post', array( $this, 'handle_order_received_page' ) );
		$this->get_wp_wrapper()->add_action( 'woocommerce_after_template_part', array( $this, 'after_order_received_template' ), 10, 4 );
		$this->get_wp_wrapper()->add_filter(
			'woocommerce_endpoint_order-received_title',
			array( $this, 'thankyou_order_received_title' ),
			3,
			10
		);
		$this->get_wp_wrapper()->add_filter(
			'woocommerce_thankyou_order_received_text',
			array(
				$this,
				'thankyou_order_received_text',
			),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action( 'wp_ajax_payever_get_status_url', array( $this, 'handle_status_update' ) );
		$this->get_wp_wrapper()->add_action( 'wp_ajax_nopriv_payever_get_status_url', array( $this, 'handle_status_update' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->get_url_helper()->is_pending_status_page() ) {
			return;
		}

		wp_register_style(
			'payever_pending_preloader',
			WC_PAYEVER_PLUGIN_URL . '/assets/css/payever_pending_preloader.css',
			array()
		);
		wp_enqueue_style( 'payever_pending_preloader' );

		wp_register_script(
			'payever_pending_preloader',
			WC_PAYEVER_PLUGIN_URL . '/assets/js/frontend/pending_preloader.js',
			array(
				'jquery',
			)
		);

		// Localize the script with new data
		wp_localize_script(
			'payever_pending_preloader',
			'Payever_Pending_Preloader',
			array(
				'nonce'      => wp_create_nonce( 'payever_get_status_url' ),
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'order_key'  => sanitize_text_field( wp_unslash( $_GET['key'] ) ), // WPCS: input var ok, CSRF ok.
				'payment_id' => sanitize_text_field( wp_unslash( $_GET[ WC_Payever_Url_Helper::PARAM_PAYMENT_ID ] ) ), // WPCS: input var ok, CSRF ok.
			)
		);

		wp_enqueue_script( 'payever_pending_preloader' );
	}

	/**
	 * Handle the order received page for pending status.
	 *
	 * @return void
	 */
	public function handle_order_received_page() {
		if ( ! $this->get_url_helper()->is_pending_status_page() ) {
			return;
		}

		WC()->session->__unset( 'payever_receipt_page' );
	}

	/**
	 * Callback function to override the template file for the order received page in case of pending status.
	 *
	 * @param string $template_name The name of the template that was requested.
	 * @param string $template_path The path of the template that was requested.
	 * @param string $located The located file path/name of the template found.
	 * @param array $args The arguments passed to the template.
	 *
	 * @return void
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function after_order_received_template( $template_name, $template_path, $located, $args ) {
		if ( ! $this->get_url_helper()->is_pending_status_page() ) {
			return;
		}

		// It could be `checkout/order-received.php`, but have problems with WC 5.9
		if ( strpos( $located, 'checkout/thankyou.php' ) !== false ) {
			$this->get_wp_wrapper()->wc_get_template(
				'checkout/pending-confirm-page.php',
				array(),
				'',
				__DIR__ . '/../templates/'
			);
		}
	}

	/**
	 * Override title.
	 *
	 * @param $title
	 * @param $endpoint
	 * @param $action
	 *
	 * @return string|null
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function thankyou_order_received_title( $title, $endpoint, $action ) {
		if ( $this->get_url_helper()->is_pending_status_page() ) {
			return __( 'Thank you, your order has been received.', 'payever-woocommerce-gateway' );
		}

		return $title;
	}

	/**
	 * @param string $text
	 * @param WC_Order|null $order
	 *
	 * @return string
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function thankyou_order_received_text( $text, $order ) {
		if ( $this->get_url_helper()->show_pending_message() ) {
			return sprintf(
				'%s <br/> %s',
				__( 'We will send the order once your loan application has been processed and we have received confirmation from Consumer Bank.', 'payever-woocommerce-gateway' ),
				__( 'You will receive a response to your loan application via email or SMS and an order confirmation from us when your order is on its way.', 'payever-woocommerce-gateway' )
			);
		}

		return $text;
	}

	/**
	 * Handle status update.
	 *
	 * @return void
	 * @throws Exception
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	public function handle_status_update() {
		check_ajax_referer( 'payever_get_status_url', 'nonce' );

		try {
			$payment_id = isset( $_GET[ WC_Payever_Url_Helper::PARAM_PAYMENT_ID ] ) ?
				sanitize_text_field( wp_unslash( $_GET[ WC_Payever_Url_Helper::PARAM_PAYMENT_ID ] ) ) : ''; // WPCS: input var ok, CSRF ok.
			if ( empty( $payment_id ) ) {
				throw new InvalidArgumentException( 'Payment ID is missing.' );
			}

			$order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : ''; // WPCS: input var ok, CSRF ok.
			if ( empty( $order_key ) ) {
				throw new InvalidArgumentException( 'Invalid order key.' );
			}

			// Get payment result
			$payment_result = $this->get_api_payment_service()->retrieve( $payment_id );

			// Get order by payment reference
			$order = $this->get_order_helper()->get_order_by_payment_id( $payment_id );
			if ( ! $order ) {
				$order = $this->get_wp_wrapper()->wc_get_order( (int) $payment_result->getReference() );
			}

			if ( empty( $order ) ) {
				throw new Exception( 'Order does not exist. Payment ID: ' . $payment_id );
			}

			if ( ! $order->key_is_valid( $order_key ) ) {
				throw new InvalidArgumentException( 'Invalid order key.' );
			}

			$redirect_url = null;
			if ( $this->get_api_payment_service()->is_in_process( $payment_result ) ) {
				wp_send_json_success(
					array( 'url' => $redirect_url ),
					200
				);

				return;
			}

			if ( $this->get_api_payment_service()->is_successful( $payment_result ) ) {
				// We could redirect to success_url, but we will handle it immediately
				$redirect_url = ( new WC_Payever_Payment_Handler() )->handle_payment( $order, $payment_result );
			}

			if ( $this->get_api_payment_service()->is_failed( $payment_result ) ) {
				$redirect_url = $this->get_url_helper()->get_failure_url( $order );
			}

			if ( $this->get_api_payment_service()->is_cancelled( $payment_result ) ) {
				$redirect_url = $this->get_url_helper()->get_cancel_url( $order );
			}

			wp_send_json_success(
				array( 'url' => $redirect_url ),
				200
			);
		} catch ( Exception $exception ) {
			wp_send_json_error(
				array( 'message' => $exception->getMessage() ),
				400
			);
		}
	}
}
