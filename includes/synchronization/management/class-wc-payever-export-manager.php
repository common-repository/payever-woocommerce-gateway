<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Export_Manager' ) ) {
	return;
}

class WC_Payever_Export_Manager {

	use WC_Payever_Generic_Manager_Trait;
	use WC_Payever_Inventory_Api_Client_Trait;
	use WC_Payever_Products_Api_Client_Trait;
	use WC_Payever_Subscription_Manager_Trait;
	use WC_Payever_Wpdb_Trait;

	const DEFAULT_LIMIT = 5;

	/** @var int|null */
	private $nextPage;

	/** @var int */
	private $aggregate = 0;

	/**
	 * @return int|null
	 */
	public function get_next_page() {
		return $this->nextPage;
	}

	/**
	 * @return int
	 */
	public function get_aggregate() {
		return $this->aggregate;
	}

	/**
	 * @return int
	 */
	public function get_total_pages() {
		return (int) ceil( count( $this->get_products( - 1 ) ) / self::DEFAULT_LIMIT );
	}

	/**
	 * @param int $current_page
	 * @param int $aggregate
	 * @return bool
	 */
	public function export( $current_page, $aggregate ) {
		$result = true;
		$this->errors = array();
		$this->nextPage = null;
		try {
			if ( ! $this->is_products_sync_enabled() ) {
				$this->errors[] = __(
					'Synchronization must be enabled in order to export products',
					'payever-woocommerce-gateway'
				);
				return false;
			}
			$this->aggregate = $aggregate;
			$pages = $this->get_total_pages();
			if ( ! $pages ) {
				$this->get_logger()->info(
					'No products to export',
					array(
						'pages'         => $pages,
						'product_count' => count( $this->get_products( - 1 ) ),
					)
				);
			}
			if ( $current_page < $pages ) {
				$this->aggregate += $this->process_batch( $current_page );
				$this->nextPage = $current_page + 1;
				if ( $this->nextPage >= $pages ) {
					$this->nextPage = null;
				}
			}
		} catch ( \Exception $exception ) {
			$result = false;
			$this->get_subscription_manager()->disable();
			$this->errors[] = $exception->getMessage();
			$this->nextPage = null;
		}

		return $result;
	}

	/**
	 * @param int $current_page
	 * @return int
	 * @throws Exception
	 */
	private function process_batch( $current_page ) {
		$wc_products = $this->get_products( self::DEFAULT_LIMIT, $current_page );
		$successCount = $this->export_products( $wc_products );
		$this->export_inventory( $wc_products );

		return $successCount;
	}

	/**
	 * @param array $wc_products
	 * @return int
	 * @throws Exception
	 */
	private function export_products( array $wc_products ) {
		$products_iterator  = new WC_Payever_Export_Products( $wc_products );

		return $this->get_product_api_client()->exportProducts( $products_iterator, $this->get_external_id() );
	}

	/**
	 * @param array $wc_products
	 * @throws Exception
	 */
	private function export_inventory( array $wc_products ) {
		$wc_products_inventory = array();
		foreach ( $wc_products as $wc_product ) {
			if ( self::need_send_stock( $wc_product ) ) {
				$wc_products_inventory[] = $wc_product;
			}

			foreach ( $wc_product->get_children() as $wc_child_product_id ) {
				$wc_child_product = $this->get_wp_wrapper()->wc_get_product( $wc_child_product_id );
				if ( self::need_send_stock( $wc_child_product ) ) {
					$wc_products_inventory[] = $wc_child_product;
				}
			}
		}
		$inventory_iterator = new WC_Payever_Export_Inventory( $wc_products_inventory );
		$this->get_inventory_api_client()->exportInventory( $inventory_iterator, $this->get_external_id() );
	}

	/**
	 * @param int $limit
	 * @param null $offset
	 * @return array|object|stdClass|null
	 */
	private function get_products( $limit, $offset = null ) {
		$args = array(
			'limit' => $limit,
			'type'  => array(
				'simple',
				'external',
				'variable',
				'downloadable',
				'virtual',
			),
		);
		if ( $offset ) {
			$args['page'] = $offset;
		}

		return $this->get_wp_wrapper()->wc_get_products( $args );
	}

	/**
	 * @param WC_Product $product
	 * @return bool
	 */
	private function need_send_stock( $product ) {
		return $product->get_manage_stock() || 'outofstock' === $product->get_stock_status();
	}
}
