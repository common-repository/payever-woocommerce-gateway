<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Export_Products_Transformer' ) ) {
	return;
}

use Payever\Sdk\Products\Enum\ProductTypeEnum;
use Payever\Sdk\Products\Http\RequestEntity\ProductRequestEntity;

class WC_Payever_Export_Products_Transformer {

	use WC_Payever_WP_Wrapper_Trait;

	/**
	 * @param WC_Product $wc_product
	 *
	 * @return ProductRequestEntity
	 */
	public function transform_woocommerce_into_payever( WC_Product $wc_product ) {
		$wc_products_type = $wc_product->get_type();
		$product_entity   = new ProductRequestEntity();

		$this->fill_product_request_entity_from_product( $wc_product, $product_entity );

		$product_entity->setType( ProductTypeEnum::TYPE_PHYSICAL );
		if ( $wc_product->is_virtual() || $wc_product->is_downloadable() ) {
			$product_entity->setType( ProductTypeEnum::TYPE_DIGITAL );
		} elseif ( 'service' === $wc_products_type ) {
			$product_entity->setType( ProductTypeEnum::TYPE_SERVICE );
		}

		$this->set_product_categories( $wc_product, $product_entity );
		$product_entity->setImages( $this->collect_product_images( $wc_product ) );

		if ( 'variable' === $wc_products_type ) {
			$this->collect_product_variants( $wc_product, $product_entity );
		}

		return $product_entity;
	}

	/**
	 * @param $wc_product
	 * @param $product_entity
	 * @return $this
	 */
	private function set_product_categories( $wc_product, $product_entity ) {
		$wc_product_categories = $this->get_wp_wrapper()->wc_get_product_category_list( $wc_product->get_id(), ',' );
		$product_entity->setCategories( explode( ',', wp_strip_all_tags( $wc_product_categories ) ) );

		return $this;
	}

	/**
	 * @param WC_Product $wc_product
	 * @param ProductRequestEntity $product_entity
	 */
	private function fill_product_request_entity_from_product( WC_Product $wc_product, ProductRequestEntity $product_entity ) {
		$product_entity->setPrice( (float) $wc_product->get_regular_price() );
		$product_entity->setSalePrice( (float) $wc_product->get_sale_price() );
		$product_entity->setBusinessUuid( $this->get_wp_wrapper()->get_option( WC_Payever_Helper::PAYEVER_BUSINESS_ID ) );
		$product_entity->setTitle( $wc_product->get_title() );
		$product_entity->setDescription( $wc_product->get_description() );
		$product_entity->setActive( $wc_product->get_status() === 'publish' );
		$product_entity->setSku( $wc_product->get_sku() );
		$product_entity->setCurrency( $this->get_wp_wrapper()->get_woocommerce_currency() );
		$product_entity->setShipping(
			array(
				'weight' => (float) $wc_product->get_weight(),
				'width'  => (float) $wc_product->get_width(),
				'length' => (float) $wc_product->get_length(),
				'height' => (float) $wc_product->get_height(),
			)
		);
	}

	/**
	 * @param WC_Product $wc_product
	 * @param ProductRequestEntity $requestEntity
	 */
	private function collect_product_variants( WC_Product $wc_product, ProductRequestEntity $requestEntity ) {
		foreach ( $this->get_variants( $wc_product ) as $variation ) {
			$variant = new ProductRequestEntity();
			$this->fill_product_request_entity_from_product( $variation, $variant );

			$variant->setPrice( (float) $variation->get_regular_price() );
			$slug = $variation->get_slug( 'edit' );
			$variant->setSku( $variation->get_sku() === $wc_product->get_sku() ? $slug : $variation->get_sku() );
			$image_data = $this->get_wp_wrapper()->wc_get_product_attachment_props( $variation->get_image_id() );
			if ( ! empty( $image_data['image']['full_src'] ) ) {
				$variant->setImages( array( $image_data['image']['full_src'] ) );
			}

			foreach ( $variation->get_variation_attributes() as $product_attribute_code => $product_attribute_value ) {
				$attribute_label = $this->get_wp_wrapper()->wc_attribute_label( str_replace( 'attribute_', '', $product_attribute_code ), $wc_product );
				$variant->addOption( $attribute_label, $product_attribute_value );
			}

			$requestEntity->addVariant( $variant );
		}
	}

	/**
	 * @param WC_Product $wc_product
	 *
	 * @return array
	 */
	private function collect_product_images( WC_Product $wc_product ) {
		$result = array();
		if ( $wc_product->get_image_id() ) {
			$result[] = $this->get_wp_wrapper()->wp_get_attachment_image_url( $wc_product->get_image_id(), 'full' );
		}
		$gallery_image_ids = $wc_product->get_gallery_image_ids();
		foreach ( $gallery_image_ids as $image_id ) {
			$result[] = $this->get_wp_wrapper()->wp_get_attachment_image_url( $image_id, 'full' );
		}

		return $result;
	}

	/**
	 * @param WC_Product $product
	 * @return WC_Product[]
	 */
	private function get_variants( WC_Product $product ) {
		$variation_ids = $product->get_children();
		$variations = array();
		foreach ( $variation_ids as $variation_id ) {
			$variation = $this->get_wp_wrapper()->wc_get_product( $variation_id );
			$variations[] = $variation;
		}

		return $variations;
	}
}
