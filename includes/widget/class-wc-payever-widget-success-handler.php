<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Payever\Sdk\Payments\Http\MessageEntity\AddressEntity;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Psr\Log\LoggerInterface;

class WC_Payever_Widget_Success_Handler {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Api_Payment_Service_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Helper_Trait;

	const LOCK_WAIT_SECONDS = 30;

	/**
	 * @var LockInterface
	 */
	private $locker;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var WC_Payever_Payment_Handler
	 */
	private $payment_handler;

	public function __construct() {
		$this->locker          = $this->get_api_wrapper()->get_locker();
		$this->logger          = $this->get_api_wrapper()->get_logger();
		$this->payment_handler = new WC_Payever_Payment_Handler();
	}

	/**
	 * Handles the payment result entity.
	 *
	 * @param RetrievePaymentResultEntity $payment
	 *
	 * @return WC_Order|null
	 */
	public function handle( RetrievePaymentResultEntity $payment ) {
		$payment_id = $payment->getId();

		$this->logger->debug( sprintf( 'Attempt to lock %s', $payment_id ) );
		$this->locker->acquireLock( $payment_id, self::LOCK_WAIT_SECONDS );

		$payment_status = $payment->getStatus();

		if ( ! ( $this->get_api_payment_service()->is_successful( $payment ) || $this->get_api_payment_service()->is_new( $payment ) ) ) {
			$this->logger->info( sprintf( 'Skip handling payever payment status %s', $payment_status ) );

			return null;
		}

		$order = $this->get_order_helper()->get_order_by_payment_id( $payment_id );
		if ( $order ) {
			$this->logger->debug(
				sprintf( 'Found order %s. Payment ID: %s', $order->get_id(), $payment_id )
			);
		}

		if ( ! $order ) {
			$order = $this->create_order( $payment );
			$this->logger->info( sprintf( 'Generated order %s', $order->get_id() ) );
		}

		// Update order status and data
		$this->payment_handler->update_order( $order, $payment );

		// Update captured items qty
		if ( $this->get_api_payment_service()->is_paid( $payment ) ) {
			$this->payment_handler->allocate_totals( $order );
		}

		$this->locker->releaseLock( $payment_id );
		$this->logger->debug( sprintf( 'Unlocked  %s', $payment_id ) );

		return $order;
	}

	/**
	 * Creates an order based on the payment details.
	 *
	 * @param RetrievePaymentResultEntity $payment The payment details.
	 *
	 * @return WC_Order The created order.
	 *
	 * @throws UnexpectedValueException If the amount paid is not equal to the product amount.
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	private function create_order( RetrievePaymentResultEntity $payment ) {
		$widget_cart     = array();
		$clear_cart      = false;
		$reference       = $payment->getReference();
		$shipping_option = $payment->getShippingOption();

		// create shipping object
		$shipping = null;
		if ( $shipping_option ) {
			$shipping = new WC_Order_Item_Shipping();
			$shipping->set_method_title( $shipping_option->getName() );
			$shipping->set_method_id( $shipping_option->getCarrier() );
			$shipping->set_total( $shipping_option->getPrice() );
		}

		if ( false !== strpos( $reference, WC_Payever_Widget_Purchase_Unit::CART_REFERENCE_PREFIX ) ) {
			$widget_cart = $this->build_widget_cart( $payment );
			$clear_cart  = true;
		}
		if ( false !== strpos( $reference, WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX ) ) {
			$reference = str_replace(
				WC_Payever_Widget_Purchase_Unit::PROD_REFERENCE_PREFIX,
				'',
				$reference
			);
			$product       = wc_get_product( $reference );
			$product_price = ( is_a( $product, WC_Product::class ) ) ? wc_get_price_including_tax( $product ) : 0;
			$order_amount  = floatval( $product_price ) + floatval( $shipping ? $shipping->get_total() : 0 );
			if ( floatval( $order_amount ) !== floatval( $payment->getTotal() ) ) {
				/* translators: %s: amount */
				$message = sprintf( __( 'The amount really paid (%1$s) is not equal to the product amount (%2$s).', 'payever-woocommerce-gateway' ), $payment->getTotal(), $order_amount );
				throw new \UnexpectedValueException( esc_html( $message ) );
			}

			$widget_cart[] = array(
				'product'  => $product,
				'quantity' => 1,
			);
		}

		/** @var AddressEntity $payment_address */
		$payment_address = $payment->getAddress();
		$email           = $payment_address->getEmail();
		$customer_id = null;
		/** @var WP_User $user */
		$user            = get_user_by( 'login', $email );

		if ( $user ) {
			$customer_id = $user->ID;
		}

		$order = wc_create_order( array( 'customer_id' => $customer_id ) );

		foreach ( $widget_cart as $widget_cart_item ) {
			$order->add_product( $widget_cart_item['product'], $widget_cart_item['quantity'] );
		}

		$order->set_address( $this->get_address( $payment_address ), 'billing' );
		$order->set_address( $this->get_address( $payment->getShippingAddress() ), 'shipping' );
		$order->set_currency( $payment->getCurrency() );
		$order->set_payment_method( $this->get_helper()->add_payever_prefix( $payment->getPaymentType() ) );
		$order->set_transaction_id( $payment->getId() );
		$order->add_meta_data( WC_Payever_Gateway::PAYEVER_PAYMENT_ID, $payment->getId() );

		if ( $shipping ) {
			$order->add_item( $shipping );
		}

		$order->calculate_totals();

		if ( $clear_cart ) {
			WC()->cart->empty_cart();
		}

		return $order;
	}

	/**
	 * @param AddressEntity $address_entity
	 *
	 * @return array
	 */
	private function get_address( $address_entity ) {
		return array(
			'first_name' => $address_entity->getFirstName(),
			'last_name'  => $address_entity->getLastName(),
			'email'      => $address_entity->getEmail(),
			'phone'      => $address_entity->getPhone(),
			'address_1'  => $address_entity->getStreet(),
			'address_2'  => $address_entity->getStreetNumber(),
			'city'       => $address_entity->getCity(),
			'postcode'   => $address_entity->getZipCode(),
			'country'    => $address_entity->getCountry(),
		);
	}

	/**
	 * @param $payment
	 *
	 * @return array
	 */
	private function build_widget_cart( $payment ) {
		$widget_cart   = array();
		$payment_items = $payment->getItems();
		if ( ! empty( $payment_items && count( $payment_items ) ) ) {
			foreach ( $payment_items as $item ) {
				$product       = wc_get_product( $item->getIdentifier() );
				$widget_cart[] = array(
					'product'  => $product,
					'quantity' => intval( $item->getQuantity() ),
				);
			}

			return $widget_cart;
		}

		$cart_hash = WC()->cart->get_cart_hash();
		$reference = str_replace(
			WC_Payever_Widget_Purchase_Unit::CART_REFERENCE_PREFIX,
			'',
			$payment->getReference()
		);
		if ( $cart_hash !== $reference ) {
			throw new \UnexpectedValueException( esc_html__( 'Invalid cart hash validation', 'payever-woocommerce-gateway' ) );
		}

		$items = WC()->cart->get_cart();
		foreach ( $items as $item ) {
			$product_id    = isset( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$product       = wc_get_product( $product_id );
			$widget_cart[] = array(
				'product'  => $product,
				'quantity' => intval( $item['quantity'] ),
			);
		}

		return $widget_cart;
	}
}
