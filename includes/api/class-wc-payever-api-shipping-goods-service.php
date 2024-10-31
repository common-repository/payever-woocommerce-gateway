<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\PaymentsApiClient;
use Payever\Sdk\Payments\Action\ActionDecider;
use Payever\Sdk\Payments\Http\RequestEntity\ShippingGoodsPaymentRequest;
use Payever\Sdk\Payments\Http\RequestEntity\ShippingDetailsEntity;
use Payever\Sdk\Core\Http\ResponseEntity;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class WC_Payever_Api_Shipping_Goods_Service {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Action_Decider_Wrapper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Order_Totals_Trait;
	use WC_Payever_Payment_Action_Wrapper_Trait;

	/**
	 * @var PaymentsApiClient
	 */
	private $api;

	/**
	 * @var ActionDecider
	 */
	private $action_decider;

	/**
	 * @var WC_Payever_Payment_Action_Wrapper
	 */
	private $payment_action;

	public function __construct() {
		$this->api            = $this->get_api_wrapper()->get_payments_api_client();
		$this->action_decider = $this->get_action_decider_wrapper()->get_action_decider( $this->api );
		$this->payment_action = $this->get_payment_action_wrapper();
	}

	/**
	 * Check if capture is allowed for an order.
	 *
	 * @param WC_Order $order The order object.
	 * @param float|null $amount The amount to be captured. Optional.
	 *
	 * @return bool Returns true if capture is allowed, false otherwise.
	 */
	public function is_capture_allowed( WC_Order $order, $amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( $this->is_partial_action( $order->get_total(), $amount ) ) {
			if ( ! $this->action_decider->isPartialShippingAllowed( $payment_id, false ) ) {
				return false;
			}

			return true;
		}

		if ( ! $this->action_decider->isShippingAllowed( $payment_id, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Capture a payment for an order.
	 *
	 * @param WC_Order $order The order object.
	 * @param float|null $amount The amount to refund, defaults to available refund amount.
	 * @param string $reason
	 * @param string|null $tracking_number
	 * @param string|null $tracking_url
	 * @param string|null $shipping_provider
	 * @param string|null $shipping_date
	 *
	 * @return ResponseEntity The response entity from the refundPaymentRequest API call.
	 *
	 * @throws BadMethodCallException If the order does not have a payment ID.
	 * @throws UnexpectedValueException If the amount is not valid.
	 * @throws Exception If an error occurs during the API call.
	 */
	public function capture(
		WC_Order $order,
		$amount = null,
		$reason = '',
		$tracking_number = null,
		$tracking_url = null,
		$shipping_provider = null,
		$shipping_date = null
	) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		if ( ! $amount ) {
			$totals = $this->get_order_total_model()->get_totals( $order );
			$amount = $totals['available_capture'];
		}

		$shipping_goods_entity = new ShippingGoodsPaymentRequest();
		$shipping_goods_entity
			->setAmount( $amount )
			->setReason( $reason );

		$this->set_shipping_information(
			$shipping_goods_entity,
			$tracking_number,
			$tracking_url,
			$shipping_provider,
			$shipping_date
		);

		try {
			$response_entity = $this->api->shippingGoodsPaymentRequest(
				$payment_id,
				$shipping_goods_entity,
				$this->payment_action->add_shipping_action( $order->get_id(), $amount )
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		$this->add_order_tracking(
			$order,
			$tracking_number,
			$tracking_url,
			$shipping_provider,
			$shipping_date
		);

		$order->add_order_note(
			sprintf(
				/* translators: %s: Order note. */
				'<p style="color: green;">%s</p>',
				sprintf(
					/* translators: %s: amount and call id. */
					__( 'Transaction has been shipped successfully. Amount: %1$s. Call ID: %2$s', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount ),
					$response_entity->getCall()->getId()
				)
			)
		);

		// Validate amount
		$this->validate_capture_amount( $amount, $order );

		// Register requested amount per items
		$this->get_order_total_model()->partial_capture( $amount, $order );

		return $response_entity;
	}

	/**
	 * Capture items from an order and make a payment request with shipping information.
	 *
	 * @param WC_Order $order - The order object.
	 * @param array<string, array{item_id: int, qty: int}> $items - The items to capture.
	 * @param string $reason - The reason for capturing the items. Optional.
	 * @param string|null $tracking_number - The tracking number for the shipment. Optional.
	 * @param string|null $tracking_url - The tracking URL for the shipment. Optional.
	 * @param string|null $shipping_provider - The shipping provider for the shipment. Optional.
	 * @param string|null $shipping_date - The shipping date for the shipment. Optional.
	 *
	 * @return ResponseEntity - The response entity from the API.
	 *
	 * @throws BadMethodCallException - If the order does not have a payment ID.
	 * @throws Exception - If an error occurs while making the shipping goods payment request.
	 */
	public function capture_items(
		WC_Order $order,
		array $items,
		$reason = '',
		$tracking_number = null,
		$tracking_url = null,
		$shipping_provider = null,
		$shipping_date = null
	) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		$items_data = $this->get_helper()->get_payment_items_data( $order, $items );

		$shipping_goods_entity = new ShippingGoodsPaymentRequest();
		$shipping_goods_entity
			->setReason( $reason )
			->setPaymentItems( $items_data['items'] )
			->setDeliveryFee( $items_data['delivery_fee'] );

		$this->set_shipping_information(
			$shipping_goods_entity,
			$tracking_number,
			$tracking_url,
			$shipping_provider,
			$shipping_date
		);

		try {
			$response_entity = $this->api->shippingGoodsPaymentRequest(
				$payment_id,
				$shipping_goods_entity,
				$this->payment_action->add_shipping_action( $order->get_id(), $items_data['amount'] )
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		$this->add_order_tracking(
			$order,
			$tracking_number,
			$tracking_url,
			$shipping_provider,
			$shipping_date
		);

		$order->add_order_note(
			sprintf(
				/* translators: %s: order note. */
				'<p style="color: green;">%s</p>',
				sprintf(
					/* translators: %s: amount and call id. */
					__( 'Transaction has been shipped successfully:<br />%1$s<br />Amount: %2$s.<br />Call ID: %3$s', 'payever-woocommerce-gateway' ),
					$this->get_helper()->format_payment_items( $items_data['items'] ),
					$this->get_wp_wrapper()->wc_price( $items_data['amount'] ),
					$response_entity->getCall()->getId()
				)
			)
		);

		// Update item qty
		$this->get_order_total_model()->update_captured_qty( $order, $items );

		return $response_entity;
	}

	/**
	 * Set shipping information for a given shipping goods entity.
	 *
	 * @param ShippingGoodsPaymentRequest $shipping_goods_entity The shipping goods entity to set the shipping information for.
	 * @param string|null $tracking_number The tracking number of the shipment. Default is null.
	 * @param string|null $tracking_url The tracking URL of the shipment. Default is null.
	 * @param string|null $shipping_provider The shipping provider of the shipment. Default is null.
	 * @param string|null $shipping_date The shipping date of the shipment. Default is null.
	 *
	 * @return void
	 */
	private function set_shipping_information(
		&$shipping_goods_entity,
		$tracking_number = null,
		$tracking_url = null,
		$shipping_provider = null,
		$shipping_date = null
	) {
		if ( ! empty( $tracking_number ) || ! empty( $tracking_url ) || ! empty( $shipping_provider ) ) {
			$shipping_details = new ShippingDetailsEntity();
			$shipping_details
				->setTrackingNumber( $tracking_number )
				->setTrackingUrl( $tracking_url )
				->setShippingCarrier( $shipping_provider )
				->setShippingDate( $shipping_date )
				->setReturnCarrier( $shipping_provider )
				->setReturnTrackingNumber( $tracking_number )
				->setShippingMethod( $shipping_provider )
				->setReturnTrackingUrl( $tracking_url );

			$shipping_goods_entity->setShippingDetails( $shipping_details );
		}
	}

	/**
	 * Adds order tracking details to the provided WC_Order instance.
	 *
	 * @param WC_Order $order The order object.
	 * @param mixed $tracking_number The tracking number for the order.
	 * @param string $tracking_url The tracking URL for the order.
	 * @param string $shipping_carrier The shipping carrier for the order.
	 * @param string $shipping_date The shipping date for the order.
	 *
	 * @return void
	 */
	private function add_order_tracking(
		WC_Order $order,
		$tracking_number,
		$tracking_url,
		$shipping_carrier,
		$shipping_date
	) {
		$order->update_meta_data( WC_Payever_Gateway::META_TRACKING_NUMBER, $tracking_number );
		$order->update_meta_data( WC_Payever_Gateway::META_TRACKING_URL, $tracking_url );
		$order->update_meta_data( WC_Payever_Gateway::META_SHIPPING_PROVIDER, $shipping_carrier );
		$order->update_meta_data( WC_Payever_Gateway::META_SHIPPING_DATE, $shipping_date );
		$order->save_meta_data();

		// Call WooCommerce Shipment Tracking if it's installed
		if ( function_exists( 'wc_st_add_tracking_number' ) ) {
			wc_st_add_tracking_number(
				$order->get_id(),
				$tracking_number,
				$shipping_carrier,
				$shipping_date,
				$tracking_url
			);
		}

		// Call `pe_add_tracking` action
		do_action(
			'pewc_add_tracking_data',
			$order,
			$tracking_number,
			$shipping_carrier,
			$shipping_date,
			$tracking_url
		);
	}

	/**
	 * Determines if the action is a partial action.
	 *
	 * @param float $order_total The total order amount.
	 * @param float|null $amount The capture amount.
	 *
	 * @return bool True if it is a partial action, false otherwise.
	 */
	private function is_partial_action( $order_total, $amount = null ) {
		if ( ! $amount || $amount > $order_total ) {
			return false;
		}

		return ! $this->get_helper()->are_same( $order_total, (float) $amount );
	}

	/**
	 * @param $amount
	 * @param WC_Order $order
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_capture_amount( &$amount, WC_Order $order ) {
		if ( $amount <= 0 ) {
			throw new \UnexpectedValueException(
				sprintf( 'Wrong amount %s value.', esc_html( $amount ) )
			);
		}

		$totals = $this->get_order_total_model()->get_totals( $order );
		if ( $amount > $totals['available_capture'] ) {
			$amount = $totals['available_capture'];
		}
	}
}
