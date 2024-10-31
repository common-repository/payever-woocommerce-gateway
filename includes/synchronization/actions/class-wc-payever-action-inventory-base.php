<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Synchronization_Action_Handler_InventoryBase' ) ) {
	return;
}

use Payever\Sdk\ThirdParty\Action\ActionHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class WC_Payever_Synchronization_Action_Handler_InventoryBase implements
	ActionHandlerInterface,
	LoggerAwareInterface {

	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	/** @var LoggerInterface */
	protected $logger;

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string   $sku
	 * @param int      $expected_result
	 * @param int|null $diff +/-
	 *
	 * @throws \Exception
	 */
	public function change_stock( $sku, $expected_result, $diff = null ) {
		$this->logger->info(
			sprintf(
				'Inventory qty will change for SKU=%s, expected=%d diff=%d',
				$sku,
				$expected_result,
				$diff
			)
		);

		$product_id = $this->get_wp_wrapper()->wc_get_product_id_by_sku( $sku ) ?:
			$this->get_helper()->get_product_variation_id_by_sku( $sku );
		if ( ! $product_id ) {
			throw new \UnexpectedValueException( sprintf( 'Product not found by SKU=%s', esc_html( $sku ) ) );
		}

		$product = $this->get_wp_wrapper()->wc_get_product( $product_id );
		$update_result = $this->update_product_stock( $product, $diff, $expected_result );
		if ( $update_result ) {
			return $update_result;
		}

		$new_stock = $product->get_stock_quantity();
		$product->set_manage_stock( $new_stock > 0 );

		$this->logger->info(
			sprintf(
				'Inventory qty changed for SKU=%s, diff=%d, new qty=%d, inStock=%d',
				$sku,
				$diff,
				$new_stock,
				$new_stock > 0 ? 'instock' : 'outofstock'
			)
		);

		return $new_stock;
	}

	/**
	 * @param WC_Product $product
	 * @param $diff
	 * @param $expected_result
	 * @return bool
	 */
	private function update_product_stock( WC_Product $product, $diff, $expected_result ) {
		if ( $product->get_manage_stock() && null !== $diff ) {
			$diff      = (float) $diff;
			$operation = abs( $diff ) === $diff ? 'increase' : 'decrease';

			return $this->get_wp_wrapper()->wc_update_product_stock(
				$product,
				abs( $diff ),
				$operation,
				true
			);
		}

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $expected_result );
		$product->save();

		return false;
	}
}
