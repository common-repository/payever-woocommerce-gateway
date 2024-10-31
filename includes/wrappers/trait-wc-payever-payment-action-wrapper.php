<?php

defined( 'ABSPATH' ) || exit;

if ( trait_exists( 'WC_Payever_Payment_Action_Wrapper_Trait' ) ) {
	return;
}

trait WC_Payever_Payment_Action_Wrapper_Trait {

	/**
	 * @var WC_Payever_Payment_Action_Wrapper
	 */
	private $payment_action_wrapper;

	/**
	 * @param WC_Payever_Payment_Action_Wrapper $payment_action_wrapper
	 * @return $this
	 * @internal
	 */
	public function set_payment_action_wrapper( WC_Payever_Payment_Action_Wrapper $payment_action_wrapper ) {
		$this->payment_action_wrapper = $payment_action_wrapper;

		return $this;
	}

	/**
	 * @return WC_Payever_Payment_Action_Wrapper
	 * @codeCoverageIgnore
	 */
	protected function get_payment_action_wrapper() {
		return null === $this->payment_action_wrapper
			? $this->payment_action_wrapper = new WC_Payever_Payment_Action_Wrapper()
			: $this->payment_action_wrapper;
	}
}
