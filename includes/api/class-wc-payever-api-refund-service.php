<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\PaymentsApiClient;
use Payever\Sdk\Payments\Action\ActionDecider;
use Payever\Sdk\Core\Http\ResponseEntity;

// @codeCoverageIgnoreStart
if ( class_exists( 'WC_Payever_Api_Refund_Service' ) ) {
	return;
}
// @codeCoverageIgnoreEnd

class WC_Payever_Api_Refund_Service {
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
	 * Checks whether refund is allowed for the given order and amount.
	 *
	 * @param WC_Order $order The order object.
	 * @param float|null $amount The refund amount.
	 *
	 * @return bool Returns true if refund is allowed, false otherwise.
	 */
	public function is_refund_allowed( WC_Order $order, $amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( $this->is_partial_action( $order->get_total(), $amount ) ) {
			if ( ! $this->action_decider->isPartialRefundAllowed( $payment_id, false ) ) {
				return false;
			}

			return true;
		}

		if ( ! $this->action_decider->isRefundAllowed( $payment_id, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Refunds a payment for an order.
	 *
	 * @param WC_Order $order The order object.
	 * @param float|null $amount The amount to refund, defaults to available refund amount.
	 *
	 * @return ResponseEntity The response entity from the refundPaymentRequest API call.
	 *
	 * @throws BadMethodCallException If the order does not have a payment ID.
	 * @throws UnexpectedValueException If the amount is not valid.
	 * @throws Exception If an error occurs during the API call.
	 */
	public function refund( WC_Order $order, $amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		if ( ! $amount ) {
			$totals = $this->get_order_total_model()->get_totals( $order );
			$amount = $totals['available_refund'];
		}

		// Validate amount
		$this->validate_refund_amount( $amount, $order );

		try {
			$response_entity = $this->api->refundPaymentRequest(
				$payment_id,
				$amount,
				$this->payment_action->add_refund_action(
					$order->get_id(),
					$amount
				)
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: order note */
				'<p style="color: green;">%s</p>',
				sprintf(
					/* translators: %s: amount and call id */
					__( 'Transaction has been refunded successfully. Amount %1$s. Call ID: %2$s', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount ),
					$response_entity->getCall()->getId()
				)
			)
		);

		// Register requested amount per items
		$this->get_order_total_model()->partial_cancel( $amount, $order );

		return $response_entity;
	}

	/**
	 * Refunds items for a given order.
	 *
	 * @param WC_Order $order The order object.
	 * @param array<string, array{item_id: int, qty: int}> $items The items to be refunded.
	 * @param mixed $control_amount The amount to be refunded. If provided and not equal to the calculated items amount,
	 * perform refund by amount.
	 *
	 * @return ResponseEntity The response entity from the API.
	 *
	 * @throws BadMethodCallException If the order does not have a payment ID.
	 * @throws Exception If an error occurred during the refund process.
	 */
	public function refund_items( WC_Order $order, array $items, $control_amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		$items_data = $this->get_helper()->get_payment_items_data( $order, $items );

		// Verify amount: If it doesn't to the calculated items amount, perform refund by amount
		if ( $control_amount && ! $this->get_helper()->are_same( $control_amount, $items_data['amount'] ) ) {
			return $this->refund( $order, $control_amount );
		}

		try {
			$response_entity = $this->api->refundItemsPaymentRequest(
				$payment_id,
				$items_data['items'],
				$items_data['delivery_fee'],
				$this->payment_action->add_refund_action( $order->get_id(), $items_data['amount'] )
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		// Update item qty
		$this->get_order_total_model()->update_refunded_qty( $order, $items );

		$order->add_order_note(
			sprintf(
				/* translators: %s: order note */
				'<p style="color: green;">%s</p>',
				sprintf(
				/* translators: %s: amount and call id */
					__( 'Transaction has been refunded successfully:<br />%1$s<br />Amount: %2$s.<br />Call ID: %3$s', 'payever-woocommerce-gateway' ),
					$this->get_helper()->format_payment_items( $items_data['items'] ),
					$this->get_wp_wrapper()->wc_price( $items_data['amount'] ),
					$response_entity->getCall()->getId()
				)
			)
		);

		return $response_entity;
	}

	/**
	 * Determines if cancel is allowed for the given order.
	 *
	 * @param WC_Order $order The order object.
	 * @param float|null $amount (Optional) The cancellation amount.
	 *
	 * @return bool True if cancel is allowed, false otherwise.
	 */
	public function is_cancel_allowed( WC_Order $order, $amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( $this->is_partial_action( $order->get_total(), $amount ) ) {
			if ( ! $this->action_decider->isPartialCancelAllowed( $payment_id, false ) ) {
				return false;
			}

			return true;
		}

		if ( ! $this->action_decider->isCancelAllowed( $payment_id, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Cancels a payment for a WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order to cancel the payment for.
	 * @param float|null $amount The amount to cancel. If null, the available cancel amount will be used.
	 *
	 * @return ResponseEntity The response entity returned by the cancelPaymentRequest method.
	 *
	 * @throws BadMethodCallException If the order does not have a payment ID.
	 * @throws Exception If an error occurs while canceling the payment.
	 */
	public function cancel( WC_Order $order, $amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		if ( ! $amount ) {
			$totals = $this->get_order_total_model()->get_totals( $order );
			$amount = $totals['available_cancel'];
		}

		// Validate amount
		$this->validate_cancel_amount( $amount, $order );

		try {
			$response_entity = $this->api->cancelPaymentRequest(
				$payment_id,
				$amount,
				$this->payment_action->add_cancel_action(
					$order->get_id(),
					$amount
				)
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: Order note. */
				'<p style="color: green;">%s</p>',
				sprintf(
					/* translators: %s: amount and call id. */
					__( 'Transaction has been cancelled successfully. Amount %1$s. Call ID: %2$s', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount ),
					$response_entity->getCall()->getId()
				)
			)
		);

		// Register requested amount per items
		$this->get_order_total_model()->partial_cancel( $amount, $order );

		return $response_entity;
	}

	/**
	 * Cancels items from a WC_Order.
	 * If the control_amount is provided and it does not match the calculated items amount,
	 * a refund by the control_amount will be performed instead.
	 *
	 * @param WC_Order $order The order to cancel items from
	 * @param array<string, array{item_id: int, qty: int}> $items The items to cancel
	 * @param mixed $control_amount (optional) The amount to refund, if it does not match the calculated items amount
	 *
	 * @return ResponseEntity The response entity from the cancelItemsPaymentRequest API call
	 * @throws BadMethodCallException If the order does not have a payment ID
	 * @throws Exception If an error occurs during the cancelItemsPaymentRequest API call
	 */
	public function cancel_items( WC_Order $order, array $items, $control_amount = null ) {
		$payment_id = $this->get_helper()->get_payment_id( $order );
		if ( ! $payment_id ) {
			throw new \BadMethodCallException( 'Order does not have payment ID.' );
		}

		$items_data = $this->get_helper()->get_payment_items_data( $order, $items );

		// Verify amount: If it doesn't to the calculated items amount, perform refund by amount
		if ( $control_amount && ! $this->get_helper()->are_same( $control_amount, $items_data['amount'] ) ) {
			return $this->cancel( $order, $control_amount );
		}

		try {
			$response_entity = $this->api->cancelItemsPaymentRequest(
				$payment_id,
				$items_data['items'],
				$items_data['delivery_fee'],
				$this->payment_action->add_cancel_action( $order->get_id(), $items_data['amount'] )
			)->getResponseEntity();
		} catch ( Exception $exception ) {
			// Reset tokens in case some of them caused this error
			$this->api->getTokens()->clear()->save();

			throw $exception;
		}

		// Update item qty
		$this->get_order_total_model()->update_cancelled_qty( $order, $items );

		$order->add_order_note(
			sprintf(
				/* translators: %s: Order note */
				'<p style="color: green;">%s</p>',
				sprintf(
					/* translators: %s: amount and call id */
					__( 'Transaction has been cancelled successfully:<br />%1$s<br />Amount: %2$s.<br />Call ID: %3$s', 'payever-woocommerce-gateway' ),
					$this->get_helper()->format_payment_items( $items_data['items'] ),
					$this->get_wp_wrapper()->wc_price( $items_data['amount'] ),
					$response_entity->getCall()->getId()
				)
			)
		);

		return $response_entity;
	}

	/**
	 * Check if the action is a partial action.
	 *
	 * @param float|null $order_total The order total.
	 * @param float|null $amount The amount.
	 *
	 * @return bool Whether the action is a partial action.
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
	private function validate_refund_amount( &$amount, WC_Order $order ) {
		if ( $amount <= 0 ) {
			throw new \UnexpectedValueException(
				sprintf( 'Wrong amount %s value.', esc_html( $amount ) )
			);
		}

		$totals = $this->get_order_total_model()->get_totals( $order );
		if ( $amount > $totals['available_refund'] ) {
			$amount = $totals['available_refund'];
		}
	}

	/**
	 * @param $amount
	 * @param WC_Order $order
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_cancel_amount( &$amount, WC_Order $order ) {
		if ( $amount <= 0 ) {
			throw new \UnexpectedValueException(
				sprintf( 'Wrong amount %s value.', esc_html( $amount ) )
			);
		}

		$totals = $this->get_order_total_model()->get_totals( $order );
		if ( $amount > $totals['available_cancel'] ) {
			$amount = $totals['available_cancel'];
		}
	}
}
