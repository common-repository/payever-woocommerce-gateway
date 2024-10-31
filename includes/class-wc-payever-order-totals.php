<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Order_Total' ) ) {
	return;
}

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WC_Payever_Order_Total {
	/**
	 * Get Totals.
	 *
	 * @param WC_Order $order
	 *
	 * @return array{cancelled: float, captured: float, refunded: float, available_capture: float, available_cancel: float, available_refund: float}
	 */
	public function get_totals( WC_Order $order ) {
		$items = $this->get_order_items( $order );

		$paid      = 0.0;
		$cancelled = 0.0;
		$captured  = 0.0;
		$refunded  = 0.0;
		foreach ( $items as $item ) {
			$unit_price = $item['unit_price'];
			$paid      += $unit_price * $item['qty'];

			$totals = $this->get_item_total( $item );

			$cancelled += $totals['cancelled'];
			$refunded  += $totals['refunded'];
			$captured  += $totals['captured'];
		}

		return array(
			'cancelled'         => $cancelled,
			'captured'          => $captured,
			'refunded'          => $refunded,
			'available_capture' => $paid - ( $captured + $cancelled ),
			'available_cancel'  => $paid - ( $captured + $cancelled ),
			'available_refund'  => $captured - $refunded,
		);
	}

	private function get_item_total( $item ) {
		$unit_price = $item['unit_price'];
		$totals     = array(
			'cancelled' => $unit_price * $item['cancelled_qty'],
			'captured'  => $unit_price * $item['captured_qty'],
			'refunded'  => $unit_price * $item['refunded_qty'],
		);
		if ( array_key_exists( 'captured_amount', $item ) && $item['captured_amount'] > 0 ) {
			$totals['captured'] = $item['captured_amount'];
		}
		if ( array_key_exists( 'refunded_amount', $item ) && $item['refunded_amount'] > 0 ) {
			$totals['refunded'] = $item['refunded_amount'];
		}
		if ( array_key_exists( 'cancelled_amount', $item ) && $item['cancelled_amount'] > 0 ) {
			$totals['cancelled'] = $item['cancelled_amount'];
		}

		return $totals;
	}

	/**
	 * Get Order Items.
	 *
	 * @param WC_Order $order
	 *
	 * @return array<int, array{order_id: int, name: string, type: string, item_id: string, unit_price: float, qty: int}>
	 */
	public function get_order_items( WC_Order $order ) {
		if ( ! $order->meta_exists( WC_Payever_Gateway::META_ORDER_ITEMS ) ) {
			$this->convert_order_items_from_old_versions( $order );
		}

		// Store order items in metadata if not exists
		$items = $order->get_meta( WC_Payever_Gateway::META_ORDER_ITEMS );
		if ( empty( $items ) || ! is_array( $items ) ) {
			$items = array();

			$order_items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );
			foreach ( $order_items as $order_item ) {
				/** @var WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item */
				$type = $order_item->get_type();

				$unit_price = ( 'line_item' === $type )
					? $this->get_order_item_subtotal( $order_item )
					: $this->get_order_shipping_item_total( $order_item );

				$items[] = array(
					'order_id'         => $order->get_id(),
					'name'             => $order_item->get_name(),
					'type'             => $type,
					'item_id'          => $order_item->get_id(),
					'unit_price'       => $unit_price,
					'qty'              => $order_item->get_quantity(),
					'cancelled_qty'    => 0,
					'captured_qty'     => 0,
					'refunded_qty'     => 0,
					'captured_amount'  => 0,
					'refunded_amount'  => 0,
					'cancelled_amount' => 0,
					'is_payment_fee'   => is_a( $order_item, 'WC_Order_Item_Fee' ),
				);
			}

			$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $items );
		}

		return $items;
	}

	/**
	 * Get Order Item by Item ID
	 *
	 * @param WC_Order $order
	 * @param string|int $item_id
	 *
	 * @return array{order_id: int, name: string, type: string, item_id: string, unit_price: float, qty: int}
	 * @throws BadMethodCallException
	 */
	public function get_order_item( WC_Order $order, $item_id ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $order_items as $item ) {
			if ( (string) $item_id === (string) $item['item_id'] ) {
				return $item;
			}
		}

		throw new \BadMethodCallException(
			sprintf(
				/* translators: %s: item id */
				esc_html__( 'Unable to find item %s', 'payever-woocommerce-gateway' ),
				esc_html( $item_id )
			)
		);
	}

	/**
	 * Get Order ItemID by Identifier.
	 *
	 * @param WC_Order $order
	 * @param string   $identifier
	 *
	 * @return int|false
	 */
	public function get_order_item_id_by_identifier( WC_Order $order, $identifier ) {
		$order_items = $order->get_items( array( 'line_item', 'fee', 'shipping', 'coupon' ) );
		foreach ( $order_items as $order_item ) {
			/** @var WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item */
			if ( (string) $identifier === (string) $this->get_order_item_identifier( $order, $order_item ) ) {
				return $order_item->get_id();
			}
		}

		return false;
	}

	/**
	 * @param WC_Order $order
	 * @param WC_Order_Item $order_item
	 *
	 * @return null|mixed|string
	 */
	private function get_order_item_identifier( WC_Order $order, $order_item ) {
		if ( $this->is_type( $order, $order_item, 'variable' ) ) {
			return $order_item->get_variation_id();
		}

		if ( $this->is_type( $order, $order_item, 'line_item' ) ) {
			return $order_item->get_product_id();
		}

		if ( $this->is_type( $order, $order_item, 'fee' ) ) {
			return 'fee-' . $order_item->get_id();
		}

		if ( $this->is_type( $order, $order_item, 'coupon' ) ) {
			return 'discount';
		}

		if ( $this->is_type( $order, $order_item, 'shipping' ) ) {
			return $order_item->get_id();
		}

		return null;
	}

	/**
	 * Get Delivery Fee Item.
	 *
	 * @param WC_Order $order
	 *
	 * @return array|null
	 */
	public function get_delivery_fee_item( WC_Order $order ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $order_items as $order_item ) {
			if ( 'shipping' === $order_item['type'] ) {
				return $order_item;
			}
		}

		return null;
	}

	/**
	 * Register requested amount per items
	 *
	 * @param float $amount capture amount.
	 * @param WC_Order $order Order
	 * @throws Exception
	 */
	public function partial_capture( &$amount, WC_Order $order ) {
		$order_items          = $this->get_order_items( $order );
		$remained_for_capture = $amount;

		/**
		 * Capture payment fee first
		 */
		foreach ( $order_items as &$item_fee ) {
			if ( array_key_exists( 'is_payment_fee', $item_fee ) && $item_fee['is_payment_fee'] ) {
				$this->partial_capture_per_item( $item_fee, $remained_for_capture );
				break;
			}
		}

		/**
		 * Capture other items
		 */
		foreach ( $order_items as &$item ) {
			if ( array_key_exists( 'is_payment_fee', $item ) && $item['is_payment_fee'] ) {
				continue;
			}
			$this->partial_capture_per_item( $item, $remained_for_capture );
		}

		if ( $remained_for_capture && $remained_for_capture <= 0.01 ) {
			$amount -= $remained_for_capture;
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();
	}

	/**
	 * Register requested amount per items
	 *
	 * @param $amount
	 * @param WC_Order $order
	 * @throws Exception
	 */
	public function partial_refund( &$amount, WC_Order $order ) {
		$order_items         = $this->get_order_items( $order );
		$remained_for_refund = $amount;

		/**
		 * Refund payment fee first
		 */
		foreach ( $order_items as &$item_fee ) {
			if ( array_key_exists( 'is_payment_fee', $item_fee ) && $item_fee['is_payment_fee'] ) {
				$this->partial_refund_per_item( $item_fee, $remained_for_refund );
				break;
			}
		}

		/**
		 * Refund other items
		 */
		foreach ( $order_items as &$item ) {
			if ( array_key_exists( 'is_payment_fee', $item ) && $item['is_payment_fee'] ) {
				continue;
			}
			$this->partial_refund_per_item( $item, $remained_for_refund );
		}

		if ( $remained_for_refund && $remained_for_refund <= 0.01 ) {
			$amount -= $remained_for_refund;
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();
	}

	/**
	 * Register requested amount per items
	 *
	 * @param $amount
	 * @param WC_Order $order
	 * @throws Exception
	 */
	public function partial_cancel( &$amount, WC_Order $order ) {
		$order_items         = $this->get_order_items( $order );
		$remained_for_cancel = $amount;

		/**
		 * Cancel payment fee first
		 */
		foreach ( $order_items as &$item_fee ) {
			if ( array_key_exists( 'is_payment_fee', $item_fee ) && $item_fee['is_payment_fee'] ) {
				$this->partial_cancel_per_item( $item_fee, $remained_for_cancel );
				break;
			}
		}

		/**
		 * Cancel other items
		 */
		foreach ( $order_items as &$item ) {
			if ( array_key_exists( 'is_payment_fee', $item ) && $item['is_payment_fee'] ) {
				continue;
			}
			$this->partial_cancel_per_item( $item, $remained_for_cancel );
		}

		if ( $remained_for_cancel && $remained_for_cancel <= 0.01 ) {
			$amount -= $remained_for_cancel;
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();
	}

	/**
	 * Check if the manual capture was not applied before.
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public function is_allow_order_capture_by_qty( WC_Order $order ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $order_items as $item ) {
			if ( array_key_exists( 'captured_amount', $item ) && $item['captured_amount'] > 0 ) {
				// customer used shipping by amount before
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if the manual capture could be applied.
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public function is_allow_order_capture_by_amount( WC_Order $order ) {
		$totals = $this->get_totals( $order );

		// Order not captured yet or not allowed capture by qty
		return 0.001 <= $totals['captured'] || ! $this->is_allow_order_capture_by_qty( $order );
	}

	/**
	 * Update cancelled qty.
	 *
	 * @param WC_Order $order
	 * @param array<string, array{item_id: int, qty: int}> $items
	 *
	 * @return array
	 * @throws Exception
	 */
	public function update_cancelled_qty( WC_Order $order, array $items ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $items as $item ) {
			$item_id  = $item['item_id'];
			$quantity = $item['qty'];

			foreach ( $order_items as $key => $order_item ) {
				if ( (string) $item_id === (string) $order_item['item_id'] ) {
					$order_items[ $key ]['cancelled_qty'] += $quantity;
				}
			}
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();

		return $order_items;
	}

	/**
	 * Update refunded qty.
	 *
	 * @param WC_Order $order
	 * @param array<string, array{item_id: int, qty: int}> $items
	 *
	 * @return array
	 * @throws Exception
	 */
	public function update_refunded_qty( WC_Order $order, array $items ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $items as $item ) {
			$item_id  = $item['item_id'];
			$quantity = $item['qty'];

			foreach ( $order_items as $key => $order_item ) {
				if ( (string) $item_id === (string) $order_item['item_id'] ) {
					$order_items[ $key ]['refunded_qty'] += $quantity;
				}
			}
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();

		return $order_items;
	}

	/**
	 * Update captured qty.
	 *
	 * @param WC_Order $order
	 * @param array<string, array{item_id: int, qty: int}> $items
	 *
	 * @return array
	 * @throws Exception
	 */
	public function update_captured_qty( WC_Order $order, array $items ) {
		$order_items = $this->get_order_items( $order );
		foreach ( $items as $item ) {
			$item_id  = $item['item_id'];
			$quantity = $item['qty'];

			foreach ( $order_items as $key => $order_item ) {
				if ( (string) $item_id === (string) $order_item['item_id'] ) {
					$order_items[ $key ]['captured_qty'] += $quantity;
				}
			}
		}

		$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $order_items );
		$order->save_meta_data();

		return $order_items;
	}

	/**
	 * @param $item
	 * @param $remained_for_capture
	 *
	 * @return $this
	 */
	private function partial_capture_per_item( &$item, &$remained_for_capture ) {
		try {
			if ( ! $remained_for_capture ) {
				throw new \InvalidArgumentException( 'Invalid remained for capture amount' );
			}
			$item_captured = 0;
			if ( array_key_exists( 'captured_amount', $item ) ) {
				$item_captured = $item['captured_amount'];
			}

			$item_qty = $item['qty'] - $item['cancelled_qty'] - $item['refunded_qty'];
			if ( ! $item_qty ) {
				throw new \UnexpectedValueException( 'Capture quantity is not available' );
			}

			$row_total    = $item['unit_price'] * $item_qty;
			$remain_total = round( $row_total - $item_captured, 2 );
			if ( $remain_total < 0.001 ) {
				throw new \UnexpectedValueException( 'Remain total is not available for capture' );
			}

			if ( $remain_total >= $remained_for_capture ) {
				$item['captured_amount'] += $remained_for_capture;
				$item['captured_qty']     = floor( $item['captured_amount'] / $item['unit_price'] );
				$remained_for_capture     = 0;
				throw new \UnexpectedValueException( 'Remain total more than remained for capture' );
			}

			$item['captured_amount'] += $remain_total;
			$item['captured_qty']     = $item_qty;
			$remained_for_capture    -= $remain_total;
			return $this;
		} catch ( \Exception $e ) {
			return $this;
		}
	}

	/**
	 * @param $item
	 * @param $remained_for_refund
	 *
	 * @return $this
	 */
	private function partial_refund_per_item( &$item, &$remained_for_refund ) {
		try {
			if ( ! $remained_for_refund ) {
				throw new \InvalidArgumentException( 'Invalid remained for refund amount' );
			}
			$item_refunded = 0;

			if ( array_key_exists( 'refunded_amount', $item ) ) {
				$item_refunded = $item['refunded_amount'];
			}

			$item_qty = $item['qty'] - $item['cancelled_qty'] - $item['refunded_qty'];
			if ( ! $item_qty ) {
				throw new \UnexpectedValueException( 'Refund quantity is not available' );
			}

			$row_total    = $item['unit_price'] * $item_qty;
			$remain_total = round( $row_total - $item_refunded, 2 );
			if ( $remain_total < 0.001 ) {
				throw new \UnexpectedValueException( 'Remain total is not available for refund' );
			}

			if ( $remain_total >= $remained_for_refund ) {
				$item['refunded_amount'] += $remained_for_refund;
				$item['refunded_qty']     = floor( $item['refunded_amount'] / $item['unit_price'] );
				$remained_for_refund      = 0;
				throw new \UnexpectedValueException( 'Remain total more than remained for refund' );
			}

			$item['refunded_amount'] += $remain_total;
			$item['refunded_qty']     = $item_qty;
			$remained_for_refund     -= $remain_total;

			return $this;
		} catch ( \Exception $exception ) {
			return $this;
		}
	}

	/**
	 * @param $item
	 * @param $remained_for_cancel
	 *
	 * @return $this
	 */
	private function partial_cancel_per_item( &$item, &$remained_for_cancel ) {
		try {
			if ( ! $remained_for_cancel ) {
				throw new \InvalidArgumentException( 'Invalid remained for cancel amount' );
			}

			$item_cancel = 0;
			if ( array_key_exists( 'refunded_amount', $item ) ) {
				$item_cancel = $item['refunded_amount'];
			}

			$item_qty = $item['qty'] - $item['cancelled_qty'] - $item['refunded_qty'];
			if ( ! $item_qty ) {
				throw new \UnexpectedValueException( 'Cancel quantity is not available' );
			}

			$row_total    = $item['unit_price'] * $item_qty;
			$remain_total = round( $row_total - $item_cancel, 2 );
			if ( $remain_total < 0.001 ) {
				throw new \UnexpectedValueException( 'Remain total is not available for cancel' );
			}

			if ( $remain_total >= $remained_for_cancel ) {
				$item['cancelled_amount'] += $remained_for_cancel;
				$item['cancelled_qty']     = floor( $item['cancelled_amount'] / $item['unit_price'] );
				$remained_for_cancel       = 0;
				throw new \UnexpectedValueException( 'Remain total more than remained for cancel.' );
			}

			$item['cancelled_amount'] += $remain_total;
			$item['cancelled_qty']     = $item_qty;
			$remained_for_cancel      -= $remain_total;

			return $this;
		} catch ( \Exception $e ) {
			return $this;
		}
	}

	/**
	 * Backward compatibility
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	private function convert_order_items_from_old_versions( WC_Order $order ) {
		$items = array();
		$captures = $order->get_meta( '_payever_partial_capture' );
		if ( ! empty( $captures ) ) {
			$order_items = $order->get_items( array( 'line_item', 'fee', 'shipping' ) );
			foreach ( $captures as $capture ) {
				foreach ( $order_items as $order_item ) {
					/** @var WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item */
					if ( $capture['item_id'] === $order_item->get_id() ) {
						$items[] = array(
							'order_id'         => $capture['order_id'],
							'name'             => $capture['name'],
							'item_id'          => $capture['item_id'],
							'unit_price'       => $capture['amount'],
							'qty'              => $order_item->get_quantity(),
							'cancelled_qty'    => 0,
							'captured_qty'     => $capture['qty'],
							'refunded_qty'     => 0,
							'captured_amount'  => 0,
							'refunded_amount'  => 0,
							'cancelled_amount' => 0,
							'is_payment_fee'   => is_a( $order_item, 'WC_Order_Item_Fee' ),
						);
						break;
					}
				}
			}

			$order->update_meta_data( WC_Payever_Gateway::META_ORDER_ITEMS, $items );
			$order->save_meta_data();
		}
	}

	/**
	 * @param WC_Order_Item_Shipping $order_item
	 *
	 * @return float|int
	 */
	private function get_order_shipping_item_total( $order_item ) {
		return (float) $order_item->get_total() + (float) $order_item->get_total_tax();
	}

	/**
	 * @param WC_Order_Item_Product $order_item
	 *
	 * @return float|int
	 */
	private function get_order_item_subtotal( $order_item ) {
		$qty = $order_item->get_quantity();
		return $qty > 0 ? ( ( $order_item->get_subtotal() + $order_item->get_subtotal_tax() ) / $qty ) : 0;
	}

	/**
	 * Check Order item type.
	 *
	 * @param WC_Order            $order
	 * @param WC_Order_Item|array $order_item
	 * @param string              $type
	 *
	 * @return false|mixed
	 */
	private function is_type( $order, $order_item, $type ) {
		if ( is_object( $order_item ) ) {
			return $order_item->is_type( $type );
		}

		$product = $order->get_product_from_item( $order_item );
		if ( is_object( $product ) ) {
			return $product->is_type( $type );
		}

		return false;
	}
}
