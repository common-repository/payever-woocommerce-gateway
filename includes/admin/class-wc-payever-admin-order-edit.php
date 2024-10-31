<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Admin_Order_Edit' ) ) {
	return;
}

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class WC_Payever_Admin_Order_Edit {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Action_Decider_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Order_Totals_Trait;

	/** @var array */
	private $is_shipping_allowed_for_order_id = array();

	/** @var array */
	private $is_cancel_allowed_for_order_id = array();

	/**
	 * Add actions.
	 *
	 * @param WC_Payever_WP_Wrapper|null $wp_wrapper
	 */
	public function __construct( $wp_wrapper = null ) {
		if ( null !== $wp_wrapper ) {
			$this->set_wp_wrapper( $wp_wrapper );
		}

		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_order_item_headers',
			array( $this, 'payever_order_item_headers' ),
			10,
			1
		);

		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_order_item_values',
			array( $this, 'payever_order_item_values' ),
			10,
			3
		);

		$this->get_wp_wrapper()->add_action(
			'woocommerce_order_item_add_action_buttons',
			array( $this, 'add_buttons' )
		);

		$this->get_wp_wrapper()->add_action(
			'woocommerce_admin_order_totals_after_tax',
			array( $this, 'add_totals' )
		);

		$this->get_wp_wrapper()->add_filter(
			'woocommerce_admin_order_should_render_refunds',
			array(
				$this,
				'should_render_refunds',
			),
			20,
			3
		);
	}

	/**
	 * @param $order
	 *
	 * @return void
	 * @throws Exception
	 */
	public function payever_order_item_headers( $order ) {
		if ( $order && $this->get_helper()->validate_order_payment_method( $order ) ) {
			?>
			<th class="payever-item-head-cancel">
				<?php esc_html_e( 'Cancelled', 'payever-woocommerce-gateway' ); ?>
			</th>
			<th class="payever-item-head-cancel-qty">
				<?php esc_html_e( 'Qty to Cancel', 'payever-woocommerce-gateway' ); ?>
			</th>
			<th class="payever-item-head-capture">
				<?php esc_html_e( 'Captured', 'payever-woocommerce-gateway' ); ?>
			</th>
			<th class="payever-item-head-capture-qty">
				<?php esc_html_e( 'Qty to Capture', 'payever-woocommerce-gateway' ); ?>
			</th>
			<?php
		}
	}

	/**
	 * @param WC_Product|null $product
	 * @param WC_Order_Item|null $item
	 * @param int $item_id
	 *
	 * @return void
	 * @throws Exception
	 */
	public function payever_order_item_values( $product, $item, $item_id ) {
		$is_shipping   = is_a( $item, 'WC_Order_Item_Shipping' );
		$is_paymentfee = is_a( $item, 'WC_Order_Item_Fee' );
		if ( $product || $is_shipping || $is_paymentfee ) {
			$order_id      = $item->get_order_id();
			$item_quantity = $item->get_quantity();

			$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
			if ( ! $this->get_helper()->validate_order_payment_method( $order ) ) {
				return;
			}

			// Get order item
			try {
				$order_item = $this->get_order_total_model()->get_order_item( $order, $item_id );
			} catch ( Exception $exception ) {
				$this->get_api_wrapper()->get_logger()->error( $exception->getMessage() );
				return;
			}

			$item_cost = $this->get_order_item_cost( $order, $item, $is_shipping, $is_paymentfee );
			$disabled  = ! $this->get_order_total_model()->is_allow_order_capture_by_qty( $order );
			$this->generate_payever_order_item_html(
				$order_item,
				$item_quantity,
				$item_cost,
				$item_id,
				$disabled,
				$is_paymentfee
			);
		}
	}

	/**
	 * @param $order_item
	 * @param $item_quantity
	 * @param $item_cost
	 * @param $item_id
	 * @param $disabled
	 * @param $is_paymentfee
	 * @return void
	 */
	private function generate_payever_order_item_html( $order_item, $item_quantity, $item_cost, $item_id, $disabled, $is_paymentfee ) {
		?>
		<!-- Cancel -->
		<td class="payever-partial-item-icon payever-cancel-action" style="width: 1%">
			<?php echo sprintf( '%s (%s)', esc_html( $order_item['cancelled_qty'] ), esc_html( $item_quantity ) ); ?>
		</td>

		<td class="payever-partial-item-qty payever-cancel-action" style="width: 1%">
			<input class="qty-input" type="number" step="1" data-item-id="<?php echo esc_attr( $item_id ); ?>"
				data-item-cost="<?php echo esc_attr( $item_cost ); ?>"
				name="wc-payever-cancel[<?php echo esc_attr( $item_id ); ?>]"
				autocomplete="off"
				min="0"
				<?php if ( $disabled || $is_paymentfee ) : ?>
					disabled="disabled"
				<?php endif; ?>
				value="0"
				max="<<?php echo esc_attr( absint( $item_quantity ) ); ?>"
				style="display: none; width: 50px; margin-top: -9px"
			>
		</td>

		<!-- Capture -->
		<td class="payever-partial-item-icon payever-capture-action" style="width: 1%">
			<?php echo sprintf( '%s (%s)', esc_html( $order_item['captured_qty'] ), esc_html( $item_quantity ) ); ?>
		</td>

		<td class="payever-partial-item-qty payever-capture-action" style="width: 1%">
			<input class="qty-input" type="number" step="1" data-item-id="<?php echo esc_attr( $item_id ); ?>"
				data-item-cost="<?php echo esc_attr( $item_cost ); ?>"
				name="wc-payever-capture[<?php echo esc_attr( $item_id ); ?>]"
				autocomplete="off"
				<?php if ( $disabled || $is_paymentfee ) : ?>
					disabled="disabled"
				<?php endif; ?>
				min="0"
				value="<?php echo esc_attr( (int) $is_paymentfee && ! $order_item['captured_qty'] ); ?>"
				max="<?php echo esc_attr( absint( $order_item['captured_qty'] - $item_quantity ) ); ?>"
				style="display: none; width: 50px; margin-top: -9px"
			>
		</td>
		<?php
	}

	private function get_order_item_cost( $order, $item, $is_shipping, $is_paymentfee ) {
		return $is_shipping || $is_paymentfee
			? $order->get_item_total( $item, true, true )
			: $order->get_item_subtotal( $item, true, true );
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return void
	 * @throws Exception
	 */
	public function add_buttons( $order ) {
		$order_id = $order->get_id();
		if ( ! $order_id || ! $this->get_helper()->validate_order_payment_method( $order ) ) {
			return;
		}

		$totals = $this->get_order_total_model()->get_totals( $order );
		?>
		<?php if ( $this->is_shipping_action_allowed( $order ) ) : ?>
			<button type="button" class="button wc-payever-capture-button" title="<?php esc_html_e( 'Capture', 'payever-woocommerce-gateway' ); ?>">
				<?php esc_html_e( 'Capture', 'payever-woocommerce-gateway' ); ?>
			</button>
			<?php
			wc_get_template(
				'admin/html-order-capture.php',
				array(
					'order'           => $order,
					'order_id'        => $order_id,
					'total_captured'  => $totals['captured'],
					'remaining_total' => $order->get_remaining_refund_amount() - $totals['captured'],
					'providers_list'  => $this->get_shipping_providers(),
					'disabled'        => ! $this->get_order_total_model()->is_allow_order_capture_by_amount( $order ),
				),
				'',
				__DIR__ . '/../../templates/'
			);
			?>
		<?php endif; ?>
		<?php if ( $this->is_cancel_action_allowed( $order ) ) : ?>
			<button type="button" class="button wc-payever-cancel-button" title="<?php esc_html_e( 'Cancel', 'payever-woocommerce-gateway' ); ?>">
				<?php esc_html_e( 'Cancel', 'payever-woocommerce-gateway' ); ?>
			</button>
			<?php
			wc_get_template(
				'admin/html-order-cancel.php',
				array(
					'order'           => $order,
					'order_id'        => $order_id,
					'total_cancelled' => $totals['cancelled'],
				),
				'',
				__DIR__ . '/../../templates/'
			);
			?>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param $order_id
	 *
	 * @return void
	 * @throws Exception
	 */
	public function add_totals( $order_id ) {
		$order = $this->get_wp_wrapper()->wc_get_order( $order_id );
		if ( ! $this->get_helper()->validate_order_payment_method( $order ) ) {
			return;
		}

		// Get totals
		$totals = $this->get_order_total_model()->get_totals( $order );
		$allowed_tags = array(
			'span' => array(),
			'bdi'  => array(),
		);
		if ( $totals['captured'] > 0.0 ) {
			?>
			<tr>
				<td class="label"><?php esc_html_e( 'Total Captured:', 'payever-woocommerce-gateway' ); ?></td>
				<td></td>
				<td class="total">
					<?php echo wp_kses( wc_price( $totals['captured'], array( 'currency' => $order->get_currency() ) ), $allowed_tags );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<?php
		}

		if ( $totals['cancelled'] > 0.0 ) {
			?>
			<tr>
				<td class="label"><?php esc_html_e( 'Total Cancelled:', 'payever-woocommerce-gateway' ); ?></td>
				<td></td>
				<td class="total">
					<?php echo wp_kses( wc_price( $totals['cancelled'], array( 'currency' => $order->get_currency() ) ), $allowed_tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Should render refunds.
	 * Uses `woocommerce_admin_order_should_render_refunds` filter.
	 *
	 * @param bool     $render_refunds
	 * @param mixed    $order_id
	 * @param WC_Order $order
	 *
	 * @return false|mixed
	 * @throws Exception
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function should_render_refunds( $render_refunds, $order_id, $order ) {
		try {
			if ( ! $render_refunds ) {
				throw new \InvalidArgumentException( 'Render refunds is not allowed.' );
			}

			if ( ! $this->get_helper()->is_payever_method( $order->get_payment_method() ) ) {
				return $render_refunds;
			}

			$payment_id = $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID );
			if ( empty( $payment_id ) ) {
				throw new \UnexpectedValueException( 'Payment id should not be empty.' );
			}

			$api            = $this->get_api_wrapper()->get_payments_api_client();
			$action_decider = $this->get_action_decider_wrapper()->get_action_decider( $api );

			if ( ! $action_decider->isRefundAllowed( $payment_id, false ) &&
				! $action_decider->isPartialRefundAllowed( $payment_id, false )
			) {
				throw new \UnexpectedValueException( 'Refund action is not allowed.' );
			}
		} catch ( \Exception $exception ) {
			return false;
		}

		return $render_refunds;
	}

	/**
	 * Get Shipping providers.
	 *
	 * @return array
	 */
	private function get_shipping_providers() {
		$providers = array();
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) ) {
			$providers = WC_Shipment_Tracking_Actions::get_instance()->get_providers();
		}

		return $providers;
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function is_shipping_action_allowed( WC_Order $order ) {
		$order_id = $order->get_id();
		$payment_id = $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID );
		if ( empty( $payment_id ) ) {
			return false;
		}

		if ( ! isset( $this->is_shipping_allowed_for_order_id[ $order_id ] ) ) {
			try {
				$api = $this->get_api_wrapper()
					->get_payments_api_client();

				$action_decider = $this->get_action_decider_wrapper()->get_action_decider( $api );
				$this->is_shipping_allowed_for_order_id[ $order_id ] = $action_decider->isPartialShippingAllowed(
					$payment_id,
					false
				);
			} catch ( \Exception $exception ) {
				$this->get_api_wrapper()->get_logger()->error( $exception->getMessage() );
				$this->is_shipping_allowed_for_order_id[ $order_id ] = false;
			}
		}

		return $this->is_shipping_allowed_for_order_id[ $order_id ];
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function is_cancel_action_allowed( WC_Order $order ) {
		$order_id = $order->get_id();
		$payment_id = $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID );
		if ( empty( $payment_id ) ) {
			return false;
		}

		if ( ! isset( $this->is_cancel_allowed_for_order_id[ $order_id ] ) ) {
			try {
				$api = $this->get_api_wrapper()
					->get_payments_api_client();

				$action_decider = $this->get_action_decider_wrapper()->get_action_decider( $api );
				$this->is_cancel_allowed_for_order_id[ $order_id ] = $action_decider->isPartialCancelAllowed(
					$payment_id,
					false
				);
			} catch ( \Exception $exception ) {
				$this->get_api_wrapper()->get_logger()->error( $exception->getMessage() );
				$this->is_cancel_allowed_for_order_id[ $order_id ] = false;
			}
		}

		return $this->is_cancel_allowed_for_order_id[ $order_id ];
	}
}
