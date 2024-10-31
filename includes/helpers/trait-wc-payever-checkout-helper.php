<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Checkout_Helper_Trait {
	/**
	 * @var WC_Payever_Checkout_Helper
	 */
	protected $checkout_helper;

	/**
	 * @param WC_Payever_Checkout_Helper $checkout_helper
	 * @return $this
	 * @internal
	 */
	public function set_checkout_helper( WC_Payever_Checkout_Helper $checkout_helper ) {
		$this->checkout_helper = $checkout_helper;

		return $this;
	}

	/**
	 * @return WC_Payever_Checkout_Helper
	 * @codeCoverageIgnore
	 */
	private function get_checkout_helper() {
		if ( null === $this->checkout_helper ) {
			$this->checkout_helper = new WC_Payever_Checkout_Helper();
		}

		return $this->checkout_helper;
	}
}
