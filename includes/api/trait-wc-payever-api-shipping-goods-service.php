<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Api_Shipping_Goods_Service_Trait {
	/**
	 * @var WC_Payever_Api_Shipping_Goods_Service
	 */
	private $api_shipping_goods_service;

	/**
	 * @param WC_Payever_Api_Shipping_Goods_Service $shipping_goods_service
	 * @return $this
	 * @internal
	 * @codeCoverageIgnore
	 */
	public function set_api_shipping_goods_service( WC_Payever_Api_Shipping_Goods_Service $shipping_goods_service ) {
		$this->api_shipping_goods_service = $shipping_goods_service;

		return $this;
	}

	/**
	 * @return WC_Payever_Api_Shipping_Goods_Service
	 * @codeCoverageIgnore
	 */
	protected function get_api_shipping_goods_service() {
		return null === $this->api_shipping_goods_service
			? $this->api_shipping_goods_service = new WC_Payever_Api_Shipping_Goods_Service()
			: $this->api_shipping_goods_service;
	}
}
