<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Api_Refund_Service_Trait {
	/**
	 * @var WC_Payever_Api_Refund_Service
	 */
	private $api_refund_service;

	/**
	 * @param WC_Payever_Api_Refund_Service $api_refund_service
	 * @return $this
	 * @internal
	 * @codeCoverageIgnore
	 */
	public function set_api_refund_service( WC_Payever_Api_Refund_Service $api_refund_service ) {
		$this->api_refund_service = $api_refund_service;

		return $this;
	}

	/**
	 * @return WC_Payever_Api_Refund_Service
	 * @codeCoverageIgnore
	 */
	protected function get_api_refund_service() {
		return null === $this->api_refund_service
			? $this->api_refund_service = new WC_Payever_Api_Refund_Service()
			: $this->api_refund_service;
	}
}
