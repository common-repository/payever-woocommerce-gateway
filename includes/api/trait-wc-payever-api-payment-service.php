<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Api_Payment_Service_Trait {
	/**
	 * @var WC_Payever_Api_Payment_Service
	 */
	private $api_payment_service;

	/**
	 * @param WC_Payever_Api_Payment_Service $service
	 *
	 * @return $this
	 * @internal
	 * @codeCoverageIgnore
	 */
	public function set_api_payment_service(
		WC_Payever_Api_Payment_Service $service
	) {
		$this->api_payment_service = $service;

		return $this;
	}

	/**
	 * @return WC_Payever_Api_Payment_Service
	 * @codeCoverageIgnore
	 */
	private function get_api_payment_service() {
		return null === $this->api_payment_service
			? $this->api_payment_service = new WC_Payever_Api_Payment_Service()
			: $this->api_payment_service;
	}
}
