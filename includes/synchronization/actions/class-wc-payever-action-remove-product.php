<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Synchronization_Action_Handler_RemoveProduct' ) ) {
	return;
}

use Payever\Sdk\Products\Http\RequestEntity\ProductRemovedRequestEntity;
use Payever\Sdk\ThirdParty\Action\ActionHandlerInterface;
use Payever\Sdk\ThirdParty\Action\ActionPayload;
use Payever\Sdk\ThirdParty\Action\ActionResult;
use Payever\Sdk\ThirdParty\Enum\ActionEnum;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class WC_Payever_Synchronization_Action_Handler_RemoveProduct implements ActionHandlerInterface, LoggerAwareInterface {

	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Helper_Trait;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function getSupportedAction() {
		return ActionEnum::ACTION_REMOVE_PRODUCT;
	}

	/**
	 * @inheritdoc
	 */
	public function handle( ActionPayload $action_payload, ActionResult $action_result ) {
		/** @var ProductRemovedRequestEntity $productRemovedEntity */
		$productRemovedEntity = $action_payload->getPayloadEntity();
		$sku                  = $productRemovedEntity->getSku();

		$this->logger->info( sprintf( 'Product will be removed SKU=%s', $sku ) );

		$product_id = $this->get_wp_wrapper()->wc_get_product_id_by_sku( $sku ) ?:
			$this->get_helper()->get_product_variation_id_by_sku( $sku );
		if ( ! $product_id ) {
			throw new \UnexpectedValueException( sprintf( 'Product not found by SKU=%s', esc_html( $sku ) ) );
		}

		$product = $this->get_wp_wrapper()->wc_get_product( $product_id );
		if ( $product ) {
			$product->delete();

			$action_result->incrementDeleted();
			$this->logger->info( sprintf( 'Product SKU=%s has been removed', $sku ) );
		}
	}
}
