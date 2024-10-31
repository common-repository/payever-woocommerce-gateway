<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Url_Helper_Trait {
	/**
	 * @var WC_Payever_Url_Helper
	 */
	protected $url_helper;

	/**
	 * @param WC_Payever_Url_Helper $url_helper
	 * @return $this
	 * @internal
	 */
	public function set_url_helper( WC_Payever_Url_Helper $url_helper ) {
		$this->url_helper = $url_helper;

		return $this;
	}

	/**
	 * @return WC_Payever_Url_Helper
	 * @codeCoverageIgnore
	 */
	protected function get_url_helper() {
		if ( null === $this->url_helper ) {
			$this->url_helper = new WC_Payever_Url_Helper();
		}

		return $this->url_helper;
	}
}
