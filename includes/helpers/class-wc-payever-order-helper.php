<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Http\MessageEntity\CartItemV3Entity;
use Payever\Sdk\Payments\Http\MessageEntity\AttributesEntity;
use Payever\Sdk\Payments\Http\MessageEntity\DimensionsEntity;

class WC_Payever_Order_Helper {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Logger_Trait;

	/**
	 * Gets order products
	 *
	 * @param WC_Order $order Customer order.
	 *
	 * @return array Products array
	 */
	public function get_order_products_v2( WC_Order $order ) {
		$products = array();
		$items    = $order->get_items( array( 'line_item' ) );
		foreach ( $items as $item ) {
			/** @var WC_Order_Item_Product $item */
			$item_data  = $this->get_order_item_data( $order, $item );
			$products[] = array(
				'name'        => $item['name'],
				'sku'         => sanitize_title( $this->get_wp_wrapper()->wc_get_product( $item_data['product_id'] )->get_sku() ),
				'price'       => $this->get_wp_wrapper()->apply_filters( 'pewc_round', $item_data['price_incl'] ),
				'priceNetto'  => $this->get_wp_wrapper()->apply_filters( 'pewc_round', floatval( $item_data['price_ex'] ) ),
				'identifier'  => strval( $item_data['variation_id'] ?: $item_data['product_id'] ),
				'vatRate'     => $item_data['price_ex'] > 0 ?
					$this->get_wp_wrapper()->apply_filters( 'pewc_round', ( $item_data['price_incl'] / $item_data['price_ex'] - 1 ) ) * 100 : 0,
				'quantity'    => intval( $item_data['quantity'] ),
				'description' => $order->get_order_number(),
				'thumbnail'   => $item_data['thumbnail_url'],
				'url'         => $item_data['url'],
			);
		}

		// Add discount line
		$discount_incl = $order->get_total_discount( false );
		if ( $discount_incl > 0 ) {
			$products[]    = array(
				'name'        => __( 'Discount', 'payever-woocommerce-gateway' ),
				'price'       => $this->get_wp_wrapper()->apply_filters( 'pewc_round', - 1 * $discount_incl ),
				'identifier'  => 'discount',
				'quantity'    => 1,
				'description' => '',
				'thumbnail'   => '',
				'url'         => '',
			);
		}
		// Add fee lines
		$payever_fee = $this->add_fees_line_v2( $order, $products );
		$this->add_fee_line_v2( $order, $products, $payever_fee );

		return $products;
	}

	/**
	 * Gets order products
	 *
	 * @param WC_Order $order Customer order.
	 *
	 * @return array Products array
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	public function get_order_products_v3( WC_Order $order ) {
		$products = array();
		$items    = $order->get_items( array( 'line_item' ) );
		foreach ( $items as $item ) {
			/** @var WC_Order_Item_Product $item */
			$item_data  = $this->get_order_item_data( $order, $item );
			$tax_rate = $item_data['price_ex'] > 0 ?
				$this->get_wp_wrapper()->apply_filters( 'pewc_round', ( $item_data['price_incl'] / $item_data['price_ex'] - 1 ) ) * 100 : 0; //phpcs:ignore

			$post = $this->get_wp_wrapper()->get_post( $item_data['product_id'] );
			$product = $this->get_wp_wrapper()->wc_get_product( $item_data['product_id'] );
			$categories    = $product->get_categories();
			$quantity = intval( $item_data['quantity'] );
			$price_ex = $quantity * ( $item_data['price_incl'] -  $item_data['price_ex'] );

			$cart_item = new CartItemV3Entity();
			$cart_item->setName( $item['name'] )
				->setIdentifier( strval( $item_data['variation_id'] ?: $item_data['product_id'] ) )
				->setSku( sanitize_title( $this->get_wp_wrapper()->wc_get_product( $item_data['product_id'] )->get_sku() ) )
				->setUnitPrice( $this->get_wp_wrapper()->apply_filters( 'pewc_round', floatval( $item_data['price_ex'] ) ) )
				->setTaxRate( $tax_rate )
				->setTotalAmount( $this->get_wp_wrapper()->apply_filters( 'pewc_round', $quantity * $item_data['price_incl'] ) )
				->setTotalTaxAmount( $this->get_wp_wrapper()->apply_filters( 'pewc_round', floatval( $price_ex ) ) )
				->setQuantity( $quantity )
				->setImageUrl( $item_data['thumbnail_url'] )
				->setProductUrl( $item_data['url'] );

			if ( $post ) {
				$cart_item->setDescription( $post->post_excerpt );
			}

			// Add attributes
			if ( $product->get_weight() ||
				$product->get_height() ||
				$product->get_width() ||
				$product->get_length()
			) {
				$attributes = new AttributesEntity();
				$attributes->setWeight( (float) $product->get_weight() );

				$dimensions = new DimensionsEntity();
				$dimensions->setHeight( (float) $product->get_height() );
				$dimensions->setWidth( (float) $product->get_width() );
				$dimensions->setLength( (float) $product->get_length() );
				$attributes->setDimensions( $dimensions );

				if ( count( $attributes->toArray() ) > 0 ) {
					$cart_item->setAttributes( $attributes );
				}
			}

			// Add category
			if ( ! empty( $categories ) ) {
				$categories = explode( ', ', $categories );
				$cart_item->setCategory( strip_tags( $categories[0] ) );
			}

			$products[] = $cart_item;
		}

		// Add discount line
		$discount_incl = $order->get_total_discount( false );
		if ( $discount_incl > 0 ) {
			$cart_item = new CartItemV3Entity();
			$cart_item->setName( __( 'Discount', 'payever-woocommerce-gateway' ) )
				->setIdentifier( 'discount' )
				->setSku( 'discount' )
				->setUnitPrice( $this->get_wp_wrapper()->apply_filters( 'pewc_round', - 1 * $discount_incl ) )
				->setTaxRate( 0 )
				->setTotalAmount( apply_filters( 'pewc_round', - 1 * $discount_incl ) )
				->setTotalTaxAmount( 0 )
				->setQuantity( 1 );

			$products[] = $cart_item;
		}
		// Add fee lines
		$payever_fee = $this->add_fees_line_v3( $order, $products );
		$this->add_fee_line_v3( $order, $products, $payever_fee );

		return $products;
	}

	/**
	 * Update the status of an order based on a payment result.
	 *
	 * @param WC_Order $order The order to update.
	 * @param RetrievePaymentResultEntity $payment The payment result.
	 * @param string|null $order_status The status to set for the order. If null, it will be determined based on the payment status.
	 *
	 * @return void
	 */
	public function update_status(
		WC_Order &$order,
		RetrievePaymentResultEntity $payment,
		$order_status = null
	) {
		$payment_status = $payment->getStatus();
		if ( ! $order_status ) {
			$order_status = $this->get_order_status(
				$payment_status
			);
		}

		if ( ! $order->has_status( $order_status ) ) {
			$order->update_status( $order_status );
			$this->get_logger()->info(
				sprintf(
					'Order #%s has been updated to %s. Payment ID: %s. Payment Status: %s',
					$order->get_id(),
					$order_status,
					$payment->getId(),
					$payment_status
				)
			);
		}
	}

	/**
	 * Returns the payever status mapping
	 *
	 * @return array
	 */
	private function get_payever_status_mapping() {
		return array(
			Status::STATUS_IN_PROCESS => 'on-hold',
			Status::STATUS_ACCEPTED   => 'processing',
			Status::STATUS_PAID       => 'processing',
			Status::STATUS_DECLINED   => 'failed',
			Status::STATUS_CANCELLED  => 'cancelled',
			Status::STATUS_FAILED     => 'cancelled',
			Status::STATUS_REFUNDED   => 'refunded',
			Status::STATUS_NEW        => 'pending',
		);
	}

	/**
	 * Get WooCommerce order status by Payment Status.
	 *
	 * @param string $payment_status
	 *
	 * @return string
	 */
	public function get_order_status( $payment_status ) {
		$status_mapping = $this->get_payever_status_mapping();
		return 'wc-' . $status_mapping[ $payment_status ];
	}

	/**
	 * Get Order By Payment ID.
	 *
	 * @param string $payment_id
	 *
	 * @return WC_Order|null
	 * @uses woocommerce_order_data_store_cpt_get_orders_query hook
	 */
	public function get_order_by_payment_id( $payment_id ) {
		$orders = wc_get_orders(
			array(
				'return'     => 'ids',
				'limit'      => 1,
				'meta_query' => array(
					array(
						'key'   => WC_Payever_Gateway::PAYEVER_PAYMENT_ID,
						'value' => $payment_id,
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			if ( is_int( $order ) ) {
				$order = $this->get_wp_wrapper()->wc_get_order( $order );
			}

			if ( $order->get_meta( WC_Payever_Gateway::PAYEVER_PAYMENT_ID ) === $payment_id ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * @param WC_Order $order
	 * @param WC_Order_Item $item
	 * @return array
	 */
	private function get_order_item_data( WC_Order $order, WC_Order_Item $item ) {
		$quantity = $item->get_quantity();
		$product  = $item->get_product();

		return array(
			'quantity'      => $quantity,
			'price_ex'      => $order->get_line_subtotal( $item, false, false ) / $quantity,
			'price_incl'    => $order->get_line_subtotal( $item, true, false ) / $quantity,
			'url'           => $product->get_permalink(),
			'product_id'    => $item->get_product_id(),
			'variation_id'  => $item->get_variation_id(),
			'thumbnail_url' => $this->get_product_thumbnail_url( $product->get_image_id() ),
		);
	}

	/**
	 * Retrieves the thumbnail URL for a given image ID.
	 * If the thumbnail exists, returns the URL. Otherwise, returns a placeholder image URL.
	 *
	 * @param int $image_id The ID of the image to get the thumbnail for.
	 *
	 * @return string The URL of the thumbnail or the placeholder image URL.
	 */
	private function get_product_thumbnail_url( $image_id ) {
		$thumb = $this->get_wp_wrapper()->wp_get_attachment_image_src( $image_id, 'thumbnail' );
		if ( ! empty( $thumb ) ) {
			return array_shift( $thumb );
		}

		return $this->get_wp_wrapper()->wc_placeholder_img_src( 'thumbnail' );
	}

	/**
	 * @param WC_Order $order
	 * @param array $products
	 *
	 * @return float Amount of payever fees
	 */
	private function add_fees_line_v2( WC_Order $order, &$products ) {
		$fees     = 0.0;
		$fee_name = __( 'payever Fee', 'payever-woocommerce-gateway' );
		foreach ( $order->get_fees() as $fee ) {
			// Skip payever fees
			if ( $fee_name === $fee->get_name() ) {
				$fees += ( $fee->get_total() + $fee->get_total_tax() );
				continue;
			}

			$fee_rate = ( abs( $fee->get_total_tax() ) > 0 )
				? round( 100 / ( $fee->get_total() / $fee->get_total_tax() ) ) : 0;

			$products[] = array(
				'name'        => $fee->get_name(),
				'price'       => $this->get_wp_wrapper()->apply_filters( 'pewc_round', $fee->get_total() + $fee->get_total_tax() ),
				'identifier'  => 'fee-' . $fee->get_id(),
				'vatRate'     => $fee_rate,
				'quantity'    => 1,
				'description' => '',
				'thumbnail'   => '',
				'url'         => '',
			);
		}

		return $fees;
	}

	/**
	 * @param WC_Order $order
	 * @param array $products
	 *
	 * @return float Amount of payever fees
	 */
	private function add_fees_line_v3( WC_Order $order, &$products ) {
		$fees     = 0.0;
		$fee_name = __( 'payever Fee', 'payever-woocommerce-gateway' );
		foreach ( $order->get_fees() as $fee ) {
			// Skip payever fees
			if ( $fee_name === $fee->get_name() ) {
				$fees += ( $fee->get_total() + $fee->get_total_tax() );
				continue;
			}

			$fee_rate = ( abs( $fee->get_total_tax() ) > 0 )
				? round( 100 / ( $fee->get_total() / $fee->get_total_tax() ) ) : 0;

			$cart_item = new CartItemV3Entity();
			$cart_item->setName( $fee->get_name() )
				->setIdentifier( 'fee-' . $fee->get_id() )
				->setSku( 'fee-' . $fee->get_id() )
				->setUnitPrice( $this->get_wp_wrapper()->apply_filters( 'pewc_round', $fee->get_total() + $fee->get_total_tax() ) )
				->setTaxRate( $fee_rate )
				->setTotalAmount( $this->get_wp_wrapper()->apply_filters( 'pewc_round', $fee->get_total() + $fee->get_total_tax() ) )
				->setTotalTaxAmount( 0 )
				->setQuantity( 1 );

			$products[] = $cart_item;
		}

		return $fees;
	}

	/**
	 * @param WC_Order $order
	 * @param array $products
	 * @param float $payever_fee
	 * @return $this
	 */
	private function add_fee_line_v3( WC_Order $order, &$products, $payever_fee ) {
		// Verify totals
		$total = 0;
		/** @var CartItemV3Entity|array $product */
		foreach ( $products as $product ) {
			$total += $product->getTotalAmount();
		}
		$diff = $this->get_wp_wrapper()->apply_filters(
			'pewc_round',
			$order->get_total() - ( $total + $payever_fee + $order->get_shipping_total() + $order->get_shipping_tax() )
		);
		if ( abs( $diff ) >= 0.01 ) {
			$cartItem = new CartItemV3Entity();
			$cartItem->setName( __( 'Fee', 'payever-woocommerce-gateway' ) )
				->setIdentifier( 'fee' )
				->setSku( 'fee' )
				->setUnitPrice( $diff )
				->setTaxRate( 0 )
				->setTotalAmount( $diff )
				->setTotalTaxAmount( 0 )
				->setQuantity( 1 );

			$products[] = $cartItem;
		}

		return $this;
	}

	/**
	 * @param WC_Order $order
	 * @param array $products
	 * @param float $payever_fee
	 * @return $this
	 */
	private function add_fee_line_v2( WC_Order $order, &$products, $payever_fee ) {
		// Verify totals
		$total = 0;
		foreach ( $products as $product ) {
			$total += ( $product['price'] * $product['quantity'] );
		}
		$diff = $this->get_wp_wrapper()->apply_filters(
			'pewc_round',
			$order->get_total() - ( $total + $payever_fee + $order->get_shipping_total() + $order->get_shipping_tax() )
		);
		if ( abs( $diff ) >= 0.01 ) {
			$products[] = array(
				'name'        => __( 'Fee', 'payever-woocommerce-gateway' ),
				'price'       => $this->get_wp_wrapper()->apply_filters( 'pewc_round', $diff ),
				'identifier'  => 'fee',
				'quantity'    => 1,
				'description' => '',
				'thumbnail'   => '',
				'url'         => '',
			);
		}

		return $this;
	}
}
