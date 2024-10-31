<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Order_Helper_Trait {
	/**
	 * @var WC_Payever_Order_Helper
	 */
	protected $order_helper;

	/**
	 * @param WC_Payever_Order_Helper $order_helper
	 * @return $this
	 * @internal
	 */
	public function set_order_helper( WC_Payever_Order_Helper $order_helper ) {
		$this->order_helper = $order_helper;

		return $this;
	}

	/**
	 * @return WC_Payever_Order_Helper
	 * @codeCoverageIgnore
	 */
	protected function get_order_helper() {
		if ( null === $this->order_helper ) {
			$this->order_helper = new WC_Payever_Order_Helper();
		}

		return $this->order_helper;
	}
}
