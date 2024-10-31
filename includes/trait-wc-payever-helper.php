<?php

defined( 'ABSPATH' ) || exit;

trait WC_Payever_Helper_Trait {

	/** @var WC_Payever_Helper */
	private $helper;

	/**
	 * @param WC_Payever_Helper $helper
	 * @return $this
	 * @internal
	 */
	public function set_helper( WC_Payever_Helper $helper ) {
		$this->helper = $helper;

		return $this;
	}

	/**
	 * @return WC_Payever_Helper
	 * @codeCoverageIgnore
	 */
	protected function get_helper() {
		return null === $this->helper
			? $this->helper = WC_Payever_Helper::instance()
			: $this->helper;
	}
}
