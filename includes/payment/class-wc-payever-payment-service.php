<?php

use Payever\Sdk\Core\Http\RequestEntity;
use Payever\Sdk\Core\Enum\ChannelSet;
use Payever\Sdk\Payments\Http\MessageEntity\CustomerAddressV3Entity;
use Payever\Sdk\Payments\Http\MessageEntity\ChannelEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PaymentDataEntity;
use Payever\Sdk\Payments\Http\RequestEntity\CreatePaymentV3Request;
use Payever\Sdk\Payments\Http\RequestEntity\SubmitPaymentRequestV3;
use Payever\Sdk\Payments\Http\MessageEntity\UrlsEntity;
use Payever\Sdk\Payments\Http\MessageEntity\ShippingOptionEntity;
use Payever\Sdk\Payments\Http\MessageEntity\PurchaseEntity;
use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\CustomerEntity;
use Payever\Sdk\Payments\Http\RequestEntity\CreatePaymentRequest;
use Payever\Sdk\Payments\Http\RequestEntity\CreatePaymentV2Request;
use Payever\Sdk\Payments\Http\RequestEntity\SubmitPaymentRequest;
use Payever\Sdk\Payments\Http\MessageEntity\CustomerAddressEntity;

defined( 'ABSPATH' ) || exit;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ElseExpression)
 */
class WC_Payever_Payment_Service {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Url_Helper_Trait;
	use WC_Payever_Order_Helper_Trait;

	/**
	 * @var array
	 */
	private $plugin_settings;

	/**
	 * @var array
	 */
	private $settings = array();

	/**
	 * @var bool
	 */
	private $is_submit_method = false;

	/**
	 * @var bool
	 */
	private $is_redirect_method = false;

	/**
	 * @param WC_Order $order
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_payment_url( WC_Order $order ) {
		$this->plugin_settings = $this->get_helper()->get_payever_plugin_settings();
		$this->settings = $this->get_helper()
			->get_payever_payment_settings( $order->get_payment_method() );
		$this->is_submit_method = isset( $this->settings['is_submit_method'] ) &&
			'yes' === $this->settings['is_submit_method'];

		$this->is_redirect_method = isset( $this->settings['is_redirect_method'] ) &&
			'yes' === $this->settings['is_redirect_method'];

		$current_api_version = $this->plugin_settings[WC_Payever_Helper::PAYEVER_API_VERSION];

		if ( WC_Payever_Admin_Settings::API3_VERSION_OPTION_VALUE === $current_api_version ) {
			//@todo is_submit_method not support without company search feature
			// return $this->is_submit_method ? $this->submit_payment( $order ) : $this->create_payment_v3( $order );
			return $this->create_payment_v3( $order );
		}

		return $this->create_payment_v2( $order );
	}

	/**
	 * Create payment request
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 * @throws Exception
	 */
	private function create_payment_v2( WC_Order $order ) {
		$payment_parameters = $this->build_payment_parameters_v2( $order, new CreatePaymentV2Request() );

		$payment_data = $payment_parameters->getPaymentData() ?: new PaymentDataEntity();
		$payment_data->setForceRedirect( (bool) $this->is_redirect_method );
		$payment_parameters->setPaymentData( $payment_data );
		$payment_parameters->setLocale( $this->get_helper()->get_checkout_language() );

		$payment_response = $this->get_api_wrapper()
			->get_payments_api_client()
			->createPaymentV2Request( $payment_parameters );

		return $payment_response
			->getResponseEntity()
			->getRedirectUrl();
	}

	/**
	 * Create payment request
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 * @throws Exception
	 */
	private function create_payment_v3( WC_Order $order ) {
		$payment_parameters = $this->build_payment_parameters_v3( $order, new CreatePaymentV3Request() );

		$payment_data = $payment_parameters->getPaymentData() ?: new PaymentDataEntity();
		$payment_data->setForceRedirect( (bool) $this->is_redirect_method );
		$payment_parameters->setPaymentData( $payment_data );
		$payment_parameters->setLocale( $this->get_helper()->get_checkout_language() );

		$payment_response = $this->get_api_wrapper()
			->get_payments_api_client()
			->createPaymentV3Request( $payment_parameters );

		return $payment_response
			->getResponseEntity()
			->getRedirectUrl();
	}

	/**
	 * Submit payment request
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 * @throws Exception
	 */
	private function submit_payment( WC_Order $order ) {
		$payment_parameters = $this->build_payment_parameters_v3( $order, new submitPaymentRequestV3() );
		$payment_parameters->setPaymentData(
			array(
				'frontendFinishUrl' => $this->get_url_helper()->get_finish_url( $order ),
				'frontendCancelUrl' => $this->get_url_helper()->get_cancel_url( $order ),
			)
		);

		$payment_response = $this->get_api_wrapper()
			->get_payments_api_client()
			->submitPaymentRequestV3( $payment_parameters );

		$response_entity_result = $payment_response->getResponseEntity()->getResult();

		if (
			in_array(
				$response_entity_result->getStatus(),
				array( Status::STATUS_CANCELLED, Status::STATUS_DECLINED, Status::STATUS_FAILED )
			)
		) {
			return $this->get_url_helper()->get_failure_url( $order );
		}

		$order->update_meta_data(
			WC_Payever_Gateway::CURRENT_PAYEVER_PAYMENT_ID,
			$response_entity_result->getId()
		);

		return $this->get_url_helper()->get_success_url( $order );
	}

	/**
	 * Forms the payever payment parameters
	 *
	 * @param WC_Order $order
	 * @param CreatePaymentV2Request $payment_parameters
	 *
	 * @return CreatePaymentV2Request
	 */
	private function build_payment_parameters_v2( WC_Order $order, RequestEntity $payment_parameters ) {
		$products    = $this->get_order_helper()->get_order_products_v2( $order );
		$order_total = $order->get_total();

		// Calculate fees
		$fees = 0.0;
		$fee_name = __( 'payever Fee', 'payever-woocommerce-gateway' );
		foreach ( $order->get_fees() as $order_item_fee ) {
			/** @var $order_item_fee WC_Order_Item_Fee */
			if ( $fee_name === $order_item_fee->get_name() ) {
				$fees += ( (float) $order_item_fee->get_total() + (float) $order_item_fee->get_total_tax() );
			}
		}

		$order_id = strval( $order->get_id() );
		if ( strlen( $order_id ) < 4 ) {
			$order_id = str_repeat( '0', 4 - strlen( $order_id ) ) . $order_id;
		}

		// set order data
		$payment_parameters
			->setAmount( $this->get_wp_wrapper()->apply_filters( 'pewc_format', $order_total - $fees ) )
			->setFee( $this->get_wp_wrapper()->apply_filters( 'pewc_format', (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ) )
			->setOrderId( $order_id )
			->setCurrency( $order->get_currency() )
			->setPaymentMethod( $this->get_helper()->remove_payever_prefix( $this->settings['method_code'] ) )
			->setCart( wp_json_encode( $products ) );

		if ( array_key_exists( 'variant_id', $this->settings ) ) {
			$payment_parameters->setVariantId( $this->settings['variant_id'] );
		}

		$this->set_billing_address( $order, $payment_parameters );
		if ( $order->has_shipping_address() ) {
			$this->set_shipping_address( $order, $payment_parameters );
		}

		$payment_parameters
			->setSuccessUrl( $this->get_url_helper()->get_success_url( $order ) )
			->setFailureUrl( $this->get_url_helper()->get_failure_url( $order ) )
			->setCancelUrl( $this->get_url_helper()->get_cancel_url( $order ) )
			->setNoticeUrl( $this->get_url_helper()->get_notice_url( $order ) )
			->setPendingUrl( $this->get_url_helper()->get_pending_url( $order ) );

		return $payment_parameters;
	}

	/**
	 * Forms the payever payment parameters
	 *
	 * @param WC_Order $order
	 * @param CreatePaymentV3Request|SubmitPaymentRequestV3 $payment_parameters
	 *
	 * @return CreatePaymentV3Request|SubmitPaymentRequestV3
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	private function build_payment_parameters_v3( WC_Order $order, RequestEntity $payment_parameters ) {
		$products    = $this->get_order_helper()->get_order_products_v3( $order );
		$order_total = $order->get_total();

		// Calculate fees
		$fees = 0.0;
		$fee_name = __( 'payever Fee', 'payever-woocommerce-gateway' );
		foreach ( $order->get_fees() as $order_item_fee ) {
			/** @var $order_item_fee WC_Order_Item_Fee */
			if ( $fee_name === $order_item_fee->get_name() ) {
				$fees += ( (float) $order_item_fee->get_total() + (float) $order_item_fee->get_total_tax() );
			}
		}

		$order_id = strval( $order->get_id() );
		if ( strlen( $order_id ) < 4 ) {
			$order_id = str_repeat( '0', 4 - strlen( $order_id ) ) . $order_id;
		}

		$payment_parameters->setUrls( $this->get_urls_entity( $order ) );

		if ( method_exists( $order, 'get_customer_ip_address' ) ) {
			$customer_ip = $order->get_customer_ip_address();
		} else {
			$customer_ip = WC_Geolocation::get_ip_address();
		}

		// set order data
		$payment_parameters
			->setAmount( apply_filters( 'pewc_format', $order_total - $fees ) )
			->setFee( apply_filters( 'pewc_format', (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ) )
			->setCurrency( $order->get_currency() )
			->setPurchase( $this->get_purchase_entity( $order ) )
			->setReference( $order_id )
			->setPaymentMethod( $this->get_helper()->remove_payever_prefix( $this->settings['method_code'] ) )
			->setClientIp( $customer_ip )
			->setPluginVersion( WC_PAYEVER_PLUGIN_VERSION )
			->setCart( $products );

		if ( array_key_exists( 'variant_id', $this->settings ) ) {
			$payment_parameters->setPaymentVariantId( $this->settings['variant_id'] );
		}

		$this->set_billing_address( $order, $payment_parameters );
		if ( $order->has_shipping_address() ) {
			$this->set_shipping_address( $order, $payment_parameters );
		}

		if ( $order->needs_shipping_address() ) {
			$payment_parameters->setShippingOption( $this->get_shipping_option_entity( $order ) );
		}

		// Set Customer
		$payment_parameters->setCustomer( $this->get_customer_entity( $order ) );

		return $payment_parameters;
	}

	/**
	 * @param WC_Order $order
	 * @param RequestEntity $payment_parameters
	 *
	 * @return $this
	 */
	private function set_shipping_address( WC_Order $order, &$payment_parameters ) {
		$shipping_address = array(
			'country'    => $order->get_shipping_country(),
			'state'      => $order->get_shipping_state(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'city'       => $order->get_shipping_city(),
			'postcode'   => $order->get_shipping_postcode(),
		);
		$base_location    = $this->get_wp_wrapper()->wc_get_base_location();
		$country          = $base_location['country'];
		$billing_country  = $order->get_billing_country();
		if ( ! empty( $billing_country ) ) {
			$country = $billing_country;
		}

		$address_entity = new CustomerAddressV3Entity();
		$address_entity
			->setFirstName( $shipping_address['first_name'] )
			->setLastName( $shipping_address['last_name'] )
			->setCity( $shipping_address['city'] )
			->setZip( $shipping_address['postcode'] )
			->setStreet( trim( $shipping_address['address_1'] . ' ' . $shipping_address['address_2'] ) )
			->setCountry( $country );

		$state = $shipping_address['state'];
		if ( ! empty( $state ) ) {
			$states = WC()->countries->get_states( $country );
			$address_entity->setRegion( $states[ $state ] ?? $state );
		}

		$payment_parameters->setShippingAddress( $address_entity );

		return $this;
	}

	/**
	 * @param WC_Order $order
	 * @param RequestEntity $payment_parameters
	 *
	 * @return $this
	 */
	private function set_billing_address( WC_Order $order, &$payment_parameters ) {
		$billing_address = array(
			'country'    => $order->get_billing_country(),
			'company'    => $order->get_billing_company(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
			'state'      => $order->get_billing_state(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'city'       => $order->get_billing_city(),
			'postcode'   => $order->get_billing_postcode(),
		);

		$street = trim( $billing_address['address_1'] . ' ' . $billing_address['address_2'] );
		$payment_parameters
			->setPluginVersion( WC_PAYEVER_PLUGIN_VERSION )
			->setEmail( $billing_address['email'] )
			->setPhone( $billing_address['phone'] );

		$billing_address_entity = new CustomerAddressV3Entity();
		$billing_address_entity
			->setFirstName( $billing_address['first_name'] )
			->setLastName( $billing_address['last_name'] )
			->setCity( $billing_address['city'] )
			->setZip( $billing_address['postcode'] )
			->setStreet( $street )
			->setCountry( $billing_address['country'] );

		$state = $billing_address['state'];
		if ( ! empty( $state ) ) {
			$states = WC()->countries->get_states( $billing_address['country'] );
			$billing_address_entity->setRegion( $states[ $state ] ?? $state );
		}

		$channel_entity = new ChannelEntity();
		$channel_entity
			->setName( ChannelSet::CHANNEL_WOOCOMMERCE )
			->setSource( $this->get_wp_wrapper()->get_bloginfo( 'version' ) )
			->setType( 'ecommerce' );

		$payment_data = new PaymentDataEntity();
		if ( ! empty( $billing_address['company'] ) ) {
			$payment_data->setOrganizationName( $billing_address['company'] );
		}

		$payment_parameters->setBillingAddress( $billing_address_entity );
		$payment_parameters->setChannel( $channel_entity );
		$payment_parameters->setPaymentData( $payment_data );

		return $this;
	}

	/**
	 * Get Purchase entity.
	 *
	 * @param WC_Order $order
	 *
	 * @return PurchaseEntity
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 */
	private function get_purchase_entity( $order ) {
		$order_total = $order->get_total();

		$currency        = $order->get_currency();
		$shipping_amount = $order->get_shipping_total() + $order->get_shipping_tax();

		// Calculate fees
		$fees = 0.0;
		$fee_name = __( 'payever Fee', 'payever-woocommerce-gateway' );
		foreach ( $order->get_fees() as $order_item_fee ) {
			/** @var $order_item_fee WC_Order_Item_Fee */
			if ( $fee_name === $order_item_fee->get_name() ) {
				$fees += ( (float) $order_item_fee->get_total() + (float) $order_item_fee->get_total_tax() );
			}
		}

		// Get country
		$billing_country = $order->get_billing_country();

		if ( ! empty( $billing_country ) ) {
			$country = $billing_country;
		} else {
			$base_location = wc_get_base_location();
			$country       = $base_location['country'];
		}

		$purchase = new PurchaseEntity();
		$purchase->setAmount( apply_filters( 'pewc_round', $order_total - $fees ) )
			->setDeliveryFee( apply_filters( 'pewc_round', $shipping_amount ) )
			->setCurrency( $currency )
			->setCountry( $country );

		return $purchase;
	}

	/**
	 * Get Shipping option entity.
	 *
	 * @param WC_Order $order
	 *
	 * @return ShippingOptionEntity
	 */
	private function get_shipping_option_entity( $order ) {
		$shipping_method_name = $order->get_shipping_method() ?: 'Carrier';
		$shipping_tax = $order->get_shipping_tax();
		$shipping_excl = $order->get_total_shipping();
		$shipping_incl = $shipping_excl + $shipping_tax;
		$shipping_tax_rate = $shipping_excl > 0 ? ( $shipping_incl / $shipping_excl - 1 ) * 100 : 0;

		$shipping_option_entity = new ShippingOptionEntity();
		$shipping_option_entity->setName( (string) $shipping_method_name )
			->setCarrier( (string) $shipping_method_name )
			->setPrice( (float) apply_filters( 'pewc_round', $shipping_incl ) )
			->setTaxAmount( (float) apply_filters( 'pewc_round', $shipping_tax ) )
			->setTaxRate( (float) apply_filters( 'pewc_round', $shipping_tax_rate ) );

		return $shipping_option_entity;
	}

	/**
	 * @param $order
	 * @return UrlsEntity
	 */
	private function get_urls_entity( $order ) {
		$urls = new UrlsEntity();
		$urls->setSuccess( $this->get_url_helper()->get_success_url( $order ) )
			->setFailure( $this->get_url_helper()->get_failure_url( $order ) )
			->setCancel( $this->get_url_helper()->get_cancel_url( $order ) )
			->setNotification( $this->get_url_helper()->get_notice_url( $order ) )
			->setPending( $this->get_url_helper()->get_pending_url( $order ) );

		return $urls;
	}

	/**
	 * Get Customer entity.
	 *
	 * @param WC_Order $order
	 *
	 * @return CustomerEntity
	 */
	private function get_customer_entity( $order ) {
		$entity = new CustomerEntity();

		$billing_email = $order->get_billing_email();
		$billing_phone = $order->get_billing_phone();
		$company       = $order->get_billing_company();

		$entity->setType( 'person' )
			->setEmail( $billing_email )
			->setPhone( $billing_phone );

		if ( ! empty( $company ) && $this->settings['is_b2b_method'] === 'yes' ) {
			$entity->setType( 'organization' );
		}

		return $entity;
	}
}
