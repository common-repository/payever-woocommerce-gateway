<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Psr\Log\LoggerInterface;

class WC_Payever_Callback_Handler {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Checkout_Helper_Trait;
	use WC_Payever_Url_Helper_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Order_Totals_Trait;
	use WC_Payever_Api_Payment_Service_Trait;

	/**
	 * @var LockInterface
	 */
	private $locker;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var WC_Payever_Payment_Handler
	 */
	private $payment_handler;

	public function __construct() {
		$this->locker          = $this->get_api_wrapper()->get_locker();
		$this->logger          = $this->get_api_wrapper()->get_logger();
		$this->payment_handler = new WC_Payever_Payment_Handler();

		$this->get_wp_wrapper()->add_action( 'payever_handle_callback', array( $this, 'execute' ) );
		$this->get_wp_wrapper()->add_action( 'woocommerce_before_main_content', array( $this, 'show_errors' ) );
		$this->get_wp_wrapper()->add_action( 'woocommerce_before_cart', array( $this, 'show_errors' ) );
		$this->get_wp_wrapper()->add_filter( 'render_block', array( $this, 'append_errors_block' ), 10, 3 );
	}

	/**
	 * @param $block_content
	 * @param array $block
	 * @param WP_Block|null $instance
	 *
	 * @return string
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function append_errors_block( $block_content, $block, $instance = null ) {
		if ( 'woocommerce/cart' === $block['blockName'] ) {
			$message = ( isset( $_GET['payever_error'] ) ) ? sanitize_text_field( wp_unslash( $_GET['payever_error'] ) ) : ''; // WPCS: input var ok, CSRF ok.

			if ( ! empty( $message ) ) {
				$error_block = wc_get_template_html(
					'block-notices/error.php',
					array(
						'notices' => array(
							array(
								'notice' => $message,
							),
						),
					)
				);

				$block_content = $error_block . $block_content;
			}
		}

		return $block_content;
	}

	public function show_errors() {
		$message = ( isset( $_GET['payever_error'] ) ) ? sanitize_text_field( wp_unslash( $_GET['payever_error'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		if ( ! empty( $message ) && ( is_product() || is_cart() ) ) {
			wc_get_template(
				'notices/error.php',
				array(
					'notices' => array(
						array(
							'notice' => $message,
						),
					),
				)
			);
		}
	}

	/**
	 * Execute callback based on URL
	 *
	 * @param bool|null $dont_die Whether to exit the script if callback type is undefined.
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	public function execute( $dont_die = null ) {
		$url_helper = $this->get_url_helper();
		if ( $url_helper->is_finish_url() ) {
			$this->finish_callback();

			$dont_die || die();
			return;
		}

		if ( $url_helper->is_cancel_url() ) {
			$this->cancel_callback();

			$dont_die || die();
			return;
		}

		if ( $url_helper->is_failure_url() ) {
			$this->error_callback();

			$dont_die || die();
			return;
		}

		if ( $url_helper->is_success_url() || $url_helper->is_pending_url() ) {
			$this->success_callback();

			$dont_die || die();
			return;
		}

		if ( $url_helper->is_notice_url() ) {
			$this->notice_callback();

			$dont_die || die();
			return;
		}

		$this->logger->error( 'Undefined callback type' );

		$dont_die || die();
	}

	/**
	 * Success Callback
	 */
	private function success_callback() {
		$payment_id = isset( $_GET['paymentId'] ) ? sanitize_text_field( wp_unslash( $_GET['paymentId'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$this->logger->info( 'Handling callback type: success. Payment ID: ' . $payment_id );

		if ( empty( $payment_id ) ) {
			throw new Exception( 'Payment ID is invalid.' );
		}

		// Retrieve payment
		$payment_result = $this->get_api_payment_service()->retrieve( $payment_id );
		$order          = $this->wp_wrapper->wc_get_order( (int) $payment_result->getReference() );

		// Handle payment
		$callback_url = $this->payment_handler->handle_payment( $order, $payment_result );
		$this->web_redirect( $callback_url );
	}

	/**
	 * Handle notice callback
	 */
	private function notice_callback() {
		$this->logger->info( 'Handling callback type: notice' );

		try {
			$notification_processor = new WC_Payever_Notification_Processor(
				new WC_Payever_Notification_Handler(),
				$this->locker,
				$this->logger
			);

			$notification_result = $notification_processor
				->processNotification();
			if ( $notification_result->isFailed() ) {
				throw new \InvalidArgumentException( $notification_result->__toString() );
			}

			echo esc_html( $notification_result->__toString() );
		} catch ( Exception $exception ) {
			$this->logger->error( 'Notification callback error: ' . $exception->getMessage() );

			wp_send_json(
				array(
					'result'  => 'error',
					'message' => $exception->getMessage(),
				),
				400
			);
		}
	}

	/**
	 * Error callback handler.
	 */
	private function error_callback() {
		$payment_id = isset( $_GET['paymentId'] ) ? sanitize_text_field( wp_unslash( $_GET['paymentId'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$this->logger->info( 'Handling callback type: error. Payment ID: ' . $payment_id );

		$order_id = isset( WC()->session->order_awaiting_payment )
			? absint( WC()->session->get( 'order_awaiting_payment' ) )
			: absint( WC()->session->get( 'store_api_draft_order', 0 ) );

		if ( $order_id > 0 ) {
			$this->logger->info( sprintf( 'Order %s. Payment %s has been declined.', $order_id, $payment_id ) );

			$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
			$order->update_status(
				'cancelled',
				__( 'Payment has been declined.', 'payever-woocommerce-gateway' )
			);

			// Retrieve payment
			$payment_result = $this->get_api_payment_service()->retrieve( $payment_id );
			$payment_type   = $payment_result->getPaymentType();
			$is_hidden = $this->get_checkout_helper()->add_payever_hidden_method( $payment_type );
			if ( $is_hidden ) {
				$this->logger->info(
					sprintf( 'Payment %s has been hidden', $payment_type )
				);
			}
		}

		$this->get_wp_wrapper()->wc_add_notice(
			__( 'Payment has been declined', 'payever-woocommerce-gateway' ),
			'error'
		);
		$this->web_redirect( $this->get_wp_wrapper()->wc_get_cart_url() );
	}

	/**
	 * Handle callback type cancel
	 */
	private function cancel_callback() {
		$this->logger->info( 'Handling callback type: cancel' );

		$order_id = isset( WC()->session->order_awaiting_payment )
			? absint( WC()->session->get( 'order_awaiting_payment' ) )
			: absint( WC()->session->get( 'store_api_draft_order', 0 ) );

		if ( $order_id > 0 ) {
			$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
			$order->update_status(
				'cancelled',
				__( 'Order has been cancelled by customer.', 'payever-woocommerce-gateway' )
			);

			$this->logger->info( sprintf( 'Order %s was cancelled by customer', $order_id ) );
		}

		$this->get_wp_wrapper()->wc_add_notice(
			__( 'Payment was canceled', 'payever-woocommerce-gateway' ),
			'error'
		);

		$this->web_redirect( $this->get_wp_wrapper()->wc_get_cart_url() );
	}

	/**
	 * Finish callback
	 *
	 * Handles the finish callback type and redirects accordingly.
	 */
	private function finish_callback() {
		$order_id = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : 0; // WPCS: input var ok, CSRF ok.
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		$this->logger->info( 'Handling callback type: finish. Reference: ' . $order_id );
		if ( $this->get_helper()->get_hash( $order_id ) !== $token ) {
			$this->logger->debug( 'Token is invalid' );
			return;
		}

		$order      = $this->get_wp_wrapper()->wc_get_order( $order_id );
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			$this->logger->critical( 'Finish callback: payment_id is missing', array( $order_id, $token ) );

			$this->web_redirect( $this->get_wp_wrapper()->wc_get_cart_url() );
			return;
		}

		$this->web_redirect(
			$this->payment_handler->handle_payment(
				$order,
				$this->get_api_payment_service()->retrieve( $payment_id )
			)
		);
	}

	/**
	 * Html redirect
	 *
	 * @param string $url Url for redirect.
	 */
	private function web_redirect( $url ) {
		$this->logger->debug( sprintf( 'Web redirect to: %s', $url ) );

		$this->get_wp_wrapper()->wc_get_template(
			'checkout/web-redirect.php',
			array(
				'url' => $url,
			),
			'',
			__DIR__ . '/../../templates/'
		);
	}
}
