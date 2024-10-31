<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Export_Products' ) ) {
	return;
}

use Payever\Sdk\Products\Base\ProductsIteratorInterface;
use Payever\Sdk\Products\Http\RequestEntity\ProductRequestEntity;

class WC_Payever_Export_Products implements ProductsIteratorInterface {

	use WC_Payever_Export_Products_Transformer_Trait;

	/** @var ArrayIterator */
	private $products_iterator;

	/** @var int  */
	private $position = 0;

	/**
	 * @param array $wc_products
	 */
	public function __construct( $wc_products ) {
		$this->products_iterator = $wc_products;
	}

	/**
	 * @return ProductRequestEntity
	 */
	public function current() {
		return $this->get_products_transformer()->transform_woocommerce_into_payever(
			$this->products_iterator[ $this->position ]
		);
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function next() {
		++$this->position;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function key() {
		return $this->position;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function valid() {
		return isset( $this->products_iterator[ $this->key() ] );
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->position = 0;
	}
}
