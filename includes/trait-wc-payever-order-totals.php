<?php

defined( 'ABSPATH' ) || exit;

if ( trait_exists( 'WC_Payever_Wpdb_Trait' ) ) {
	return;
}

trait WC_Payever_Order_Totals_Trait {
	/**
	 * @var WC_Payever_Order_Total
	 */
	private $order_total_model;

	/**
	 * @return WC_Payever_Order_Total
	 * @codeCoverageIgnore
	 */
	protected function get_order_total_model() {
		return null === $this->order_total_model
			? $this->order_total_model = new WC_Payever_Order_Total()
			: $this->order_total_model;
	}

	/**
	 * @param WC_Payever_Order_Total $payever_order_total
	 * @codeCoverageIgnore
	 */
	public function set_order_total_model( WC_Payever_Order_Total $payever_order_total ) {
		$this->order_total_model = $payever_order_total;
	}
}
