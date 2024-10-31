<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Psr\Log\LoggerInterface;

/**
 * Class WC_Payever_Finance_Express_Api
 */
class WC_Payever_Finance_Express_Api {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Api_Payment_Service_Trait;
	use WC_Payever_Url_Helper_Trait;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var LockInterface
	 */
	private $locker;

	/**
	 * @var WC_Payever_Widget_Quote_Helper
	 */
	private $quote_helper;

	/**
	 * @var WC_Payever_Widget_Success_Handler
	 */
	private $success_handler;

	/**
	 * @var WC_Payever_Notification_Processor
	 */
	private $notification_processor;

	/**
	 * WC_Payever_Finance_Express_Api constructor.
	 */
	public function __construct() {
		$this->logger          = $this->get_api_wrapper()->get_logger();
		$this->locker          = $this->get_api_wrapper()->get_locker();
		$this->quote_helper    = new WC_Payever_Widget_Quote_Helper();
		$this->success_handler = new WC_Payever_Widget_Success_Handler();

		if ( $this->locker && $this->logger ) {
			$this->notification_processor = new WC_Payever_Notification_Processor(
				new WC_Payever_Widget_Notification_Handler(),
				$this->locker,
				$this->logger
			);
		}

		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_finance_express_success',
			array( $this, 'success' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_finance_express_cancel',
			array( $this, 'cancel' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_finance_express_failure',
			array( $this, 'failure' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_finance_express_notice',
			array( $this, 'notice' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_finance_express_quotecallback',
			array( $this, 'quote' )
		);
	}

	/**
	 * Quote callback for express widget
	 * SUCCESS URL: domain/wc-api/payever_finance_express_quotecallback?amount=32.00&token=something
	 */
	public function quote( $dont_die = null ) {
		try {
			$payload = wp_kses_post( sanitize_text_field( $this->get_wp_wrapper()->get_contents( 'php://input' ) ) ); // WPCS: input var ok, CSRF ok.
			$this->logger->debug( '[QuoteCallback]: Payload: ' . $payload );

			$payload = json_decode( $payload, true );
			if ( ! isset( $payload['shipping'] ) ) {
				throw new InvalidArgumentException( '[QuoteCallback]: Shipping was not set.' );
			}

			$reference = sanitize_text_field( wp_unslash( $_GET['reference'] ) ); // WPCS: input var ok, CSRF ok.
			$cart_hash = sanitize_text_field( wp_unslash( $_GET['hash'] ) ); // WPCS: input var ok, CSRF ok.

			/** @var WC_Cart $cart */
			$cart = $this->get_wp_wrapper()->get_transient( 'payever_cart_' . $cart_hash );
			if ( ! $cart instanceof WC_Cart ) {
				throw new InvalidArgumentException( '[QuoteCallback]: Cart error.' );
			}

			if ( false !== strpos( $reference, WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX ) ) {
				$cart->empty_cart( false );

				$product_id = str_replace(
					WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX,
					'',
					$reference
				);
				// @todo Add variation_id
				$cart->add_to_cart( $product_id, 1 );
				$cart->calculate_totals();
			}

			$response = array(
				'shippingMethods' => $this->quote_helper->estimate(
					$cart,
					$payload['shipping']['shippingAddress']
				),
			);

			$this->logger->debug(
				'[QuoteCallback]: Response: ' . wp_json_encode( $response ),
			);

			$this->get_wp_wrapper()->wp_send_json( $response );
		} catch ( InvalidArgumentException $exception ) {
			$this->logger->critical( $exception );
			$this->get_wp_wrapper()->wp_send_json( array() );
		}

		$dont_die || die();
	}

	/**
	 * Success and pending callback function
	 * SUCCESS URL: domain/wc-api/payever_finance_express_success?reference=--PAYMENT-ID--
	 *
	 * @throws Exception
	 */
	public function success( $dont_die = null ) {
		$payment_id = ( isset( $_GET['reference'] ) ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$this->logger->info( sprintf( 'Hit finance-express/success for payment %s', esc_attr( $payment_id ) ) );

		$payment = $this->get_api_payment_service()->retrieve( $payment_id );
		$this->logger->debug(
			sprintf( 'Payment retrieved. %s', esc_html( $payment_id ) ),
			$payment->toArray()
		);

		$order = $this->success_handler->handle( $payment );
		if ( ! $order ) {
			$this->get_api_wrapper()->get_locker()->releaseLock( $payment_id );
			$this->get_wp_wrapper()->wp_redirect(
				add_query_arg(
					array(
						'payever_error' => __( 'The payment was not successful. Please try again or choose another payment option.', 'payever-woocommerce-gateway' ),
					),
					$this->get_rejecting_url( $payment->getReference() )
				)
			);
			$dont_die || die();
		}

		// Redirect to "Pending page"
		if ( $this->get_api_payment_service()->is_new( $payment ) ||
			$this->get_api_payment_service()->is_in_process( $payment )
		) {
			$this->get_wp_wrapper()->wp_redirect( $this->get_url_helper()->get_pending_page_url( $order, $payment_id ) );
			$dont_die || die();

			return;
		}

		$this->get_wp_wrapper()->wp_redirect( $order->get_checkout_order_received_url() );
		$dont_die || die();
	}

	/**
	 * Cancel callback function
	 * CANCEL URL: domain/wc-api/payever_finance_express_cancel
	 *
	 * @throws Exception
	 */
	public function cancel( $dont_die = null ) {
		$payment_id = ( isset( $_GET['reference'] ) ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$this->logger->info( sprintf( 'Hit finance-express/cancel for payment %s', esc_attr( $payment_id ) ) );

		$this->handle_failure( $payment_id, __( 'Payment has been cancelled.', 'payever-woocommerce-gateway' ) );
		$dont_die || die();
	}

	/**
	 * Failure callback function
	 * FAILURE URL: domain/wc-api/payever_finance_express_failure?reference=--PAYMENT-ID--
	 *
	 * @throws Exception
	 */
	public function failure( $dont_die = null ) {
		$payment_id = ( isset( $_GET['reference'] ) ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$this->logger->info( sprintf( 'Hit finance-express/failure for payment %s', esc_attr( $payment_id ) ) );

		$this->handle_failure( $payment_id, __( 'Payment has been refused.', 'payever-woocommerce-gateway' ) );
		$dont_die || die();
	}

	/**
	 * Notice callback function
	 * NOTICE URL: domain/wc-api/payever_finance_express_notice?reference=--PAYMENT-ID--
	 *
	 * @throws Exception
	 */
	public function notice() {
		$payment_id = ( isset( $_GET['reference'] ) ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$raw_data   = wp_kses_post( sanitize_text_field( $this->get_wp_wrapper()->get_contents( 'php://input' ) ) ); // WPCS: input var ok, CSRF ok.
		$this->logger->info( sprintf( 'Hit finance-express/notice for payment %s', esc_attr( $payment_id ) ) );
		$this->logger->info( sprintf( 'Payload: %s', esc_attr( $raw_data ) ) );

		$result = false;
		try {
			$notification_result = $this->notification_processor
				->processNotification();
			$result = ! $notification_result->isFailed();
			$data = array( 'message' => $notification_result->__toString() );
		} catch ( Exception $exception ) {
			$this->logger->error( 'Notification callback error: ' . $exception->getMessage() );
			$data = array( 'message' => $exception->getMessage() );
		}

		$data['status'] = $result;
		$this->get_wp_wrapper()->wp_send_json(
			$data,
			$result ? 200 : 400
		);
	}

	/**
	 * Returns rejecting url
	 *
	 * @param $reference
	 *
	 * @return string
	 */
	private function get_rejecting_url( $reference ) {
		if ( strpos( $reference, WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX ) !== false ) {
			/** @var WC_Product $product */
			$product = $this->get_wp_wrapper()->wc_get_product(
				str_replace( WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX, '', $reference )
			);

			return $product->get_permalink();
		}

		return esc_url( $this->get_wp_wrapper()->wc_get_cart_url() );
	}

	/**
	 * Handles failure of payment
	 *
	 * @param string $payment_id The ID of the payment
	 * @param string $message The error message
	 *
	 * @return void
	 */
	private function handle_failure( $payment_id, $message ) {
		$payment   = $this->get_api_payment_service()->retrieve( $payment_id );
		$reference = $payment->getReference();
		if ( strpos( $reference, WC_Payever_Widget_Purchase_Unit::CART_REFERENCE_PREFIX ) !== false &&
			WC()->cart->is_empty()
		) {
			$items = $payment->getItems();
			foreach ( $items as $item ) {
				// @todo Add variable product to cart
				WC()->cart->add_to_cart( $item['identifier'], $item['quantity'] );
			}
		}

		$this->get_wp_wrapper()->wp_redirect(
			add_query_arg(
				array(
					'payever_error' => $message,
				),
				$this->get_rejecting_url( $payment->getReference() )
			)
		);
	}
}
