<?php

defined( 'ABSPATH' ) || exit;

/**
 * @method add_action( $order_id, $identifier, $type, $amount = null )
 * @method string add_shipping_action( $order_id, $amount = null )
 * @method string add_refund_action( $order_id, $amount = null )
 * @method string add_cancel_action( $order_id, $amount = null )
 * @method add_item( array $data )
 * @method stdClass get_item( $order_id, $identifier, $source )
 */
class WC_Payever_Payment_Action_Wrapper {
	/**
	 * @param string $method
	 * @param array $args
	 * @return false|mixed|null
	 */
	public function __call( $method, $args ) {
		$payment_action = new WC_Payever_Payment_Action();
		return method_exists( $payment_action, $method ) ?
			call_user_func_array( array( $payment_action, $method ), $args ) : null;
	}
}
