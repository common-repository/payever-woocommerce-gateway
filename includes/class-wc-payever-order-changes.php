<?php

defined( 'ABSPATH' ) || exit;

class WC_Payever_Order_Changes {
	use WC_Payever_Helper_Trait;
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Order_Totals_Trait;
	use WC_Payever_Api_Shipping_Goods_Service_Trait;
	use WC_Payever_Api_Refund_Service_Trait;

	public function __construct() {
		$this->get_wp_wrapper()->add_action(
			'woocommerce_order_status_changed',
			array(
				$this,
				'order_status_changed_transaction',
			),
			0,
			3
		);
	}

	/**
	 * Order status handler
	 *
	 * @param int $order_id
	 * @param string $old_status
	 * @param string $new_status
	 *
	 * @return void
	 * @throws Exception
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function order_status_changed_transaction( $order_id, $old_status, $new_status ) {
		$type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		if ( 'notice' === $type ) {
			return;
		}

		try {
			$this->execute_payment_action( $new_status, $order_id );
		} catch ( Exception $exception ) {
			$this->get_helper()->add_error_metabox(
				sprintf(
					/* translators: %s: error message */
					esc_html__(
						'Unable to initiate the payment action. Error: %1$s.',
						'payever-woocommerce-gateway'
					),
					esc_html( $exception->getMessage() )
				)
			);
		}
	}

	/**
	 * Executing payment action
	 *
	 * @param string $status_action
	 * @param int $order_id
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function execute_payment_action( $status_action, $order_id ) {
		$order      = $this->get_wp_wrapper()->wc_get_order( $order_id );
		$payment_id = $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID );
		if ( ! $this->get_helper()->validate_order_payment_method( $order ) || empty( $payment_id ) ) {
			return false;
		}

		$totals = $this->get_order_total_model()->get_totals( $order );

		if ( 'cancelled' === $status_action &&
			$this->get_api_refund_service()->is_cancel_allowed( $order, $totals['available_cancel'] )
		) {
			// Cancel by amount
			$this->get_api_refund_service()->cancel( $order, $totals['available_cancel'] );

			return true;
		}

		if ( $status_action === $this->get_helper()->get_transition_shipping_status() &&
			$this->get_api_shipping_goods_service()->is_capture_allowed( $order, $totals['available_capture'] )
		) {
			// Capture by items or by amount
			$this->capture( $order, $totals );

			return true;
		}

		if ( 'refunded' === $status_action &&
			$this->get_api_refund_service()->is_refund_allowed( $order, $totals['available_refund'] )
		) {
			// Refund by amount
			$this->get_api_refund_service()->refund(
				$order,
				$totals['available_refund']
			);

			return true;
		}

		return false;
	}

	/**
	 * Capture items and/or amount for an order.
	 *
	 * @param WC_Order $order The order object.
	 * @param array $totals The totals array containing information about the available capture amount.
	 *
	 * @return void
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	private function capture( WC_Order $order, array $totals ) {
		$ship_info = $this->get_gzd_shipments_by_order( $order );

		// Capture by items
		if ( $this->get_order_total_model()->is_allow_order_capture_by_qty( $order ) &&
			! $this->get_helper()->is_ivy( $order->get_payment_method() )
		) {
			$items = $this->collect_items_for_capture( $order );
			$this->get_api_shipping_goods_service()->capture_items(
				$order,
				$items,
				__( 'Captured items by order status changing', 'payever-woocommerce-gateway' ),
				$ship_info ? $ship_info['tracking_number'] : null,
				$ship_info ? $ship_info['tracking_url'] : null,
				$ship_info ? $ship_info['shipping_provider'] : $order->get_shipping_method(),
				$ship_info ? $ship_info['shipping_date'] : null,
			);

			return;
		}

		// Capture by amount
		$this->get_api_shipping_goods_service()->capture(
			$order,
			$totals['available_capture'],
			sprintf(
				/* translators: %s: captured amount */
				__( 'Captured %s by order status changing', 'payever-woocommerce-gateway' ),
				$this->get_wp_wrapper()->wc_price( $totals['available_capture'] )
			),
			$ship_info ? $ship_info['tracking_number'] : null,
			$ship_info ? $ship_info['tracking_url'] : null,
			$ship_info ? $ship_info['shipping_provider'] : $order->get_shipping_method(),
			$ship_info ? $ship_info['shipping_date'] : null,
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array{item_id: int, qty: int}
	 */
	private function collect_items_for_capture( WC_Order $order ) {
		$result = array();
		$items = $this->get_order_total_model()->get_order_items( $order );
		foreach ( $items as $item ) {
			$result[] = array(
				'item_id' => $item['item_id'],
				'qty'     => $item['qty'] - $item['cancelled_qty'] - $item['captured_qty'],
			);
		}

		return $result;
	}

	/**
	 * Get shipping details provided by `Germanized Shipments for WooCommerce`
	 *
	 * @param WC_Order $order
	 *
	 * @return null|array{tracking_number:string, tracking_url:string, shipping_provider:string, shipping_date:string}
	 * @codeCoverageIgnore
	 */
	private function get_gzd_shipments_by_order( WC_Order $order ) {
		if ( ! function_exists( 'wc_gzd_get_shipments_by_order' ) ) {
			return null;
		}

		$shipments = wc_gzd_get_shipments_by_order( $order );
		foreach ( $shipments as $shipment ) {
			/** @var \Vendidero\Germanized\Shipments\Shipment $shipment */
			if ( ! $shipment->is_shipped() ) {
				continue;
			}

			$has_tracking = $shipment->has_tracking();
			return array(
				'tracking_number'   => $has_tracking ? $shipment->get_tracking_id() : null,
				'tracking_url'      => $has_tracking ? $shipment->get_tracking_url() : null,
				'shipping_provider' => $shipment->get_shipping_provider_title(),
				'shipping_date'     => $shipment->get_date_sent()->date_i18n(),
			);
		}

		return null;
	}
}
