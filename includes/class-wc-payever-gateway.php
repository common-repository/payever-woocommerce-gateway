<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Gateway' ) ) {
	return;
}

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class WC_Payever_Gateway extends WC_Payment_Gateway {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Url_Helper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Checkout_Helper_Trait;
	use WC_Payever_Api_Refund_Service_Trait;

	const CURRENT_PAYEVER_PAYMENT_ID = 'current_payever_payment_id';
	const PAYEVER_PAYMENT_ID = 'payment_id';
	const META_ORDER_ITEMS = '_payever_order_items';
	const META_NOTIFICATION_TIMESTAMP = 'notification_timestamp';
	const META_STOCK_REDUCED = 'payever_order_stock_reduced';
	const META_PAN_ID = 'pan_id';
	const META_SANTANDER_APPLICATION_NUMBER = 'Santander application number';
	const META_TRACKING_NUMBER = '_payever_tracking_number';
	const META_TRACKING_URL = '_payever_tracking_url';
	const META_SHIPPING_PROVIDER = '_payever_shipping_provider';
	const META_SHIPPING_DATE = '_payever_shipping_date';

	/**
	 * @var float
	 */
	public $min_amount = 0;

	/**
	 * @var float
	 */
	public $max_amount = 0;

	/**
	 * Plugin settings.
	 *
	 * @var array $plugin_settings
	 */
	public $plugin_settings = array();

	/**
	 * Supported features
	 *
	 * @var array
	 */
	public $supports = array( 'products', 'refunds' );

	/**
	 * @var bool
	 */
	private $is_redirect_method = false;

	/**
	 * @var bool
	 */
	protected $is_submit_method = false;

	/**
	 * @var string
	 */
	public $accept_fee = 'no';

	/**
	 * @var float
	 */
	public $fee;

	/**
	 * @var float
	 */
	public $variable_fee;

	/**
	 * @var WC_Payever_Payment_Service
	 */
	private $payment_service;

	/**
	 * Construct
	 */
	public function __construct( $current_payment_id ) {
		$this->id              = $current_payment_id;
		$this->plugin_settings = $this->get_helper()->get_payever_plugin_settings();
		$this->init_settings();
		$this->payever_admin_payment_settings();
		$this->assign_payment_configuration_data();

		if ( ! array_key_exists( 'method_code', $this->settings ) ) {
			$this->settings['method_code'] = $this->get_helper()->remove_payever_prefix( $current_payment_id );
		}

		$this->get_wp_wrapper()->add_filter(
			'woocommerce_thankyou_order_received_text',
			array(
				$this,
				'thankyou_order_received_text',
			),
			10,
			2
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_' . strtolower( get_class( $this ) ),
			array( $this, 'handle_callback' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_api_payever_execute_commands',
			array( $this, 'execute_commands' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_receipt_' . $this->id,
			array( $this, 'receipt_page' )
		);
		$this->get_wp_wrapper()->add_action(
			'woocommerce_order_details_after_order_table_items',
			array(
				$this,
				'align_transaction_info',
			)
		);

		$this->get_wp_wrapper()->add_action(
			'woocommerce_email_after_order_table',
			array(
				$this,
				'add_panid_to_email',
			),
			20,
			4
		);

		$this->get_helper()->clear_session_fragments();

		$this->payment_service  = new WC_Payever_Payment_Service();
	}

	/**
	 * /wc-api/execute_commands/?token=:token
	 * @codeCoverageIgnore
	 */
	public function execute_commands() {
		try {
			$token          = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // WPCS: input var ok, CSRF ok.
			$business_uuid  = $this->plugin_settings[ WC_Payever_Helper::PAYEVER_BUSINESS_ID ];
			$is_valid_token = $this->get_api_wrapper()->get_third_party_plugins_api_client()
				->validateToken( $business_uuid, $token );

			if ( $is_valid_token ) {
				WC_Payever_Plugin_Command_Cron::execute_plugin_commands();
				wp_send_json(
					array(
						'message' => 'The commands have been executed successfully',
						'result'  => 'success',
					)
				);
			}

			throw new \BadMethodCallException( 'Invalid token' );
		} catch ( Exception $exception ) {
			$message = sprintf(
				'Exception with message "%s" occurred in %s on line %s',
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			);

			$this->get_api_wrapper()->get_logger()->error( $message );

			wp_send_json(
				array(
					'message' => $exception->getMessage(),
					'result'  => 'error',
				),
				400
			);
		}
	}

	/**
	 * @param string $text
	 * @param WC_Order|null $order
	 *
	 * @return string
	 */
	public function thankyou_order_received_text( $text, $order ) {
		if ( ! $order ) {
			return $text;
		}

		if (
			! $this->get_url_helper()->is_pending_status_page() &&
			$this->get_helper()->validate_order_payment_method( $order ) &&
			$order->has_status( 'on-hold' )
		) {
			return __( 'Thank you, your order has been received. You will receive an update once your request has been processed.', 'payever-woocommerce-gateway' ); //phpcs:ignore
		}

		return $text;
	}

	/**
	 * Handle callback. Handle the plugin urls.
	 *
	 * @return void
	 */
	public function handle_callback( $dont_die = null ) {
		try {
			// Call `WC_Payever_Callback_Handler`
			$this->get_wp_wrapper()->do_action( 'payever_handle_callback' );
		} catch ( Exception $exception ) {
			$this->get_api_wrapper()->get_logger()->error(
				$exception->getMessage(),
				array( 'trace' => $exception->getTraceAsString() )
			);

			$this->get_wp_wrapper()->wp_redirect(
				add_query_arg(
					array(
						'payever_error' => __( 'An error occured while page processing. Please try later.', 'payever-woocommerce-gateway' ),
					),
					$this->get_wp_wrapper()->wc_get_cart_url()
				)
			);
		}

		$dont_die || die;
	}

	/**
	 * Gateway configurations in shop backend
	 *
	 * @return void
	 */
	private function payever_admin_payment_settings() {
		$title = $this->settings['title'] ?? '';
		$currency_min_max = $this->get_helper()->is_santander( $this->id )
			? $this->settings['currencies'][0]
			: $this->get_wp_wrapper()->get_woocommerce_currency();

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable / Disable', 'payever-woocommerce-gateway' ),
				'type'    => 'checkbox',
				'label'   => sprintf( /* translators: %s: plugin status*/__( 'Enable %s', 'payever-woocommerce-gateway' ), esc_html( $title ) ),
				'default' => '',
			),
			'title'           => array(
				'title'       => __( 'Payment Title <sup>*</sup>', 'payever-woocommerce-gateway' ),
				'type'        => 'text',
				'description' => '',
				'default'     => '',
			),
			'description'     => array(
				'title'       => __( 'Description <sup>*</sup>', 'payever-woocommerce-gateway' ),
				'type'        => 'textarea',
				'description' => '',
				'default'     => '',
			),
			'accept_fee'      => array(
				'title'             => __( 'Enable / Disable Fee', 'payever-woocommerce-gateway' ),
				'type'              => 'checkbox',
				'label'             => __( 'Merchant covers fees', 'payever-woocommerce-gateway' ),
				'custom_attributes' => array(
					'onclick' => 'return false;',
				),
				'default'           => 'no',
			),
			'fee'             => array(
				'title'             => __( 'Fixed Fee', 'payever-woocommerce-gateway' ),
				'type'              => 'text',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
				'default'           => '',
			),
			'variable_fee'    => array(
				'title'             => __( 'Variable Fee', 'payever-woocommerce-gateway' ),
				'type'              => 'text',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
				'default'           => '',
			),
			'min_order_total' => array(
				'title'       => __( 'Minimum Order Total', 'payever-woocommerce-gateway' ) . ' (' . $currency_min_max . ') <sup>*</sup>',
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $currency_min_max,
				'default'     => '',
			),
			'max_order_total' => array(
				'title'       => __( 'Maximum Order Total', 'payever-woocommerce-gateway' ) . ' (' . $currency_min_max . ') <sup>*</sup>',
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $currency_min_max,
				'default'     => '',
			),
		);

		if ( array_key_exists( 'is_redirect_method', $this->settings ) && WC_Payever_Helper::PAYEVER_ALLOW_IFRAME ) {
			$this->form_fields['is_redirect_method'] = array(
				'title'   => __( 'Is redirect method', 'payever-woocommerce-gateway' ),
				'type'    => 'checkbox',
				'label'   => sprintf( /* translators: %s: payment title */__( 'Apply submit payment for %s', 'payever-woocommerce-gateway' ), esc_html( $title ) ),
				'default' => '',
			);
		}

		foreach ( $this->form_fields as $key => $field ) {
			if ( ! isset( $this->settings[ $key ] ) ) {
				$this->settings[ $key ] = $field['default'] ?? '';
			}
		}
	}

	/**
	 * Assign the Configuration data's to its member functions
	 *
	 * @return void
	 */
	public function assign_payment_configuration_data() {
		$this->method_title       = $this->settings['title'];
		$this->method_description = $this->settings['description'] ?? '';

		if ( 'yes' === $this->settings['enabled'] ) {
			$this->title       = sanitize_text_field( $this->settings['title'] );
			$this->description = sanitize_text_field( $this->settings['description'] );

			$this->accept_fee   = $this->settings['accept_fee'];
			$this->fee          = floatval( $this->settings['fee'] );
			$this->variable_fee = floatval( $this->settings['variable_fee'] );

			$this->is_redirect_method = array_key_exists( 'is_redirect_method', $this->settings )
				&& 'yes' === $this->settings['is_redirect_method'];

			$this->is_submit_method = array_key_exists( 'is_submit_method', $this->settings )
				&& 'yes' === $this->settings['is_submit_method'];

			$this->min_amount = intval( $this->settings['min_order_total'] );
			$this->max_amount = intval( $this->settings['max_order_total'] );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function is_available() {
		// Check if a page is a checkout page or an ajax request to change the address.
		if ( ! ( is_checkout() || isset( $_GET['rest_route'] ) && WC()->customer ) ) {
			return $this->get_checkout_helper()->is_payment_enabled( $this->id );
		}

		return $this->get_checkout_helper()->is_available(
			$this->id,
			$this->get_order_total(),
			$this->get_wp_wrapper()->get_woocommerce_currency(),
			WC()->customer->get_billing(),
			WC()->customer->get_shipping()
		);
	}

	/**
	 * @inheritdoc
	 */
	protected function get_order_total() {
		$total    = 0;
		$order_id = absint( get_query_var( 'order-pay' ) );

		// Gets order total from "pay for order" page.
		if ( 0 < $order_id ) {
			$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
			$total = (float) $order->get_total();

			// Gets order total from cart/checkout.
		} elseif ( 0 < WC()->cart->get_total( false ) ) {
			$total = (float) WC()->cart->get_total( false );
		}

		return sprintf( '%.2f', $total );
	}

	/**
	 * @inheritdoc
	 */
	public function get_icon( $url_only = null ) {
		if ( 'yes' === $this->plugin_settings[ WC_Payever_Helper::PAYEVER_DISPLAY_ICON ] && $this->settings['icon'] ) {
			$icon_html = $url_only
				? $this->settings['icon']
				: '<img src="' . esc_attr( $this->settings['icon'] ) . '" alt="' . esc_attr( $this->title ) . '" class="payever_icon" title="' . esc_attr( $this->title ) . '" />';

			return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
		}

		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function get_title() {
		$title = $this->title;

		if ( 'yes' === $this->plugin_settings[ WC_Payever_Helper::PAYEVER_DISPLAY_TITLE ] ) {
			$fee_label = $this->get_fee_label();
			$title    .= $fee_label;
		}

		if ( 0 !== (int) $this->plugin_settings[ WC_Payever_Helper::PAYEVER_ENVIRONMENT ] ) {
			$modes  = $this->get_helper()->get_payever_modes();
			$title .= ' ' . strtoupper( $modes[ $this->plugin_settings[ WC_Payever_Helper::PAYEVER_ENVIRONMENT ] ] ) . ' ' . __( 'Mode', 'payever-woocommerce-gateway' );
		}

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	/**
	 * Returns fee label
	 *
	 * @return string
	 */
	private function get_fee_label() {
		$fee_label = '';

		if ( 'no' === $this->accept_fee ) {
			$fixed_fee    = $this->fee;
			$variable_fee = $this->variable_fee;

			if ( 0 < $fixed_fee && 0 < $variable_fee ) {
				$fixed_fee = wp_strip_all_tags( wc_price( $fixed_fee ) );
				$fee_label = "({$variable_fee}% + {$fixed_fee})";
			} elseif ( $fixed_fee <= 0.001 && 0 < $variable_fee ) {
				$fee_label = "(+ {$variable_fee}%)";
			} elseif ( 0 < $fixed_fee && $variable_fee <= 0.001 ) {
				$fixed_fee = wp_strip_all_tags( wc_price( $fixed_fee ) );
				$fee_label = "(+ {$fixed_fee})";
			}
		}

		return $fee_label;
	}

	/**
	 * @inheritdoc
	 */
	public function get_description() {
		$description = $this->description;
		if ( ( 'yes' === $this->plugin_settings[ WC_Payever_Helper::PAYEVER_DISPLAY_DESCRIPTION ] ) && ! empty( $description ) ) {
			return apply_filters( 'woocommerce_gateway_description', $description, $this->id );
		}

		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function payment_fields() {
		if ( ( 'yes' === $this->plugin_settings[ WC_Payever_Helper::PAYEVER_DISPLAY_DESCRIPTION ] ) && ! empty( $this->description ) ) {
			echo esc_html( $this->description );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function process_payment( $order_id ) {
		$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
		$redirect_mode = $this->is_redirect_method ?:
			( 'yes' === $this->plugin_settings[ WC_Payever_Helper::PAYEVER_REDIRECT_MODE ] || ! WC_Payever_Helper::PAYEVER_ALLOW_IFRAME );

		try {
			$redirect_url = $redirect_mode ? $this->payment_service->get_payment_url( $order ) :
				$order->get_checkout_payment_url( true );

			if ( ! $redirect_url ) {
				throw new Exception( 'Redirect URL could not be retrieved.' );
			}

			return array(
				'result'   => 'success',
				'redirect' => $redirect_url,
			);
		} catch ( Exception $exception ) {
			$this->get_wp_wrapper()->wc_add_notice( $exception->getMessage(), 'error' );

			return array(
				'result'   => 'failure',
				'redirect' => $this->get_wp_wrapper()->wc_get_endpoint_url( 'checkout' ),
			);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = $this->get_wp_wrapper()->wc_get_order( $order_id );
		$refund = $order->get_refunds();

		/** @var WC_Order_Refund $refund */
		$refund = reset( $refund );

		try {
			if ( $refund->get_refunded_payment() || $refund->get_amount() !== $amount ) {
				throw new \BadMethodCallException(
					__(
						'Unable to determine refund entity or refund has already been processed.',
						'payever-woocommerce-gateway'
					)
				);
			}

			$refund_service = $this->get_api_refund_service();
			$refund_items   = $this->prepare_refund_items( $refund );
			if ( $refund_service->is_cancel_allowed( $order, $amount ) ) {
				$this->get_api_refund_service()->cancel_items( $order, $refund_items, $amount );
				$refund->set_refunded_payment( true );

				return true;
			}

			if ( $refund_service->is_refund_allowed( $order, $amount ) ) {
				$this->get_api_refund_service()->refund_items( $order, $refund_items, $amount );
				$refund->set_refunded_payment( true );

				return true;
			}

			return new WP_Error(
				'refund_failed',
				__( 'Sorry, but refund is not available now', 'payever-woocommerce-gateway' )
			);
		} catch ( Exception $exception ) {
			return new WP_Error( 'refund_failed', $exception->getMessage() );
		}
	}

	/**
	 * @param WC_Order_Refund $refund
	 *
	 * @return array<string, array{item_id: int, qty: int}> $items
	 */
	private function prepare_refund_items( WC_Order_Refund $refund ) {
		$refund_items = array();
		$items        = $refund->get_items( array( 'line_item', 'shipping', 'fee' ) );
		foreach ( $items as $item ) {
			$refund_items[] = array(
				'item_id' => $item->get_meta( '_refunded_item_id' ),
				'qty'     => abs( $item->get_quantity() ),
			);
		}

		return $refund_items;
	}

	/**
	 * WooCommerce receipt page
	 * calls from hook "woocommerce_receipt_{gateway_id}"
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		if ( ! isset( WC()->session->payever_receipt_page ) ) {
			$order       = $this->get_wp_wrapper()->wc_get_order( $order_id );
			$payment_url = $this->payment_service->get_payment_url( $order );

			if ( $payment_url ) {
				$this->get_wp_wrapper()->wc_get_template(
					'checkout/iframe.php',
					array(
						'payment_url' => $payment_url,
					),
					'',
					__DIR__ . '/../templates/'
				);

				WC()->session->set( 'payever_receipt_page', true );
			}
		}
	}

	/**
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param string $plain_text
	 * @param object|bool $email
	 *
	 * @return bool
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function add_panid_to_email( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( is_object( $email ) && $this->get_helper()->is_payever_method( $order->get_payment_method() ) ) {
			if ( 'customer_processing_order' === $email->id ||
				 'customer_on_hold_order' === $email->id || //phpcs:ignore
				 'customer_invoice' === $email->id //phpcs:ignore
			) {
				$this->get_wp_wrapper()->wc_get_template(
					'admin/email-panid-details.php',
					array(
						'order' => $order,
					),
					'',
					__DIR__ . '/../templates/'
				);
			}

			return true;
		}

		return false;
	}

	/**
	 * Calls from the hook "woocommerce_order_items_table"
	 * To align the customer notes in the order success page
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	public function align_transaction_info( WC_Order $order ) {
		$payever_payments = array_keys( get_option( WC_Payever_Helper::PAYEVER_ACTIVE_PAYMENTS ) );
		$payment_method   = $order->get_payment_method();
		$customer_note    = $order->get_customer_note();
		if ( in_array( $payment_method, $payever_payments ) && $customer_note ) {
			$order->set_customer_note( wpautop( $customer_note ) );
		}
	}
}
