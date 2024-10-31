<?php

defined( 'ABSPATH' ) || exit;

class WC_Payever_Widget_Quote_Helper {
	/**
	 * Estimates the shipping methods available for the given country code, zip code, and cart.
	 *
	 * @param WC_Cart $cart
	 * @param array $address Shipping Address.
	 * @return array An array of shipping methods.
	 *               Each shipping method has the following structure:
	 *               - price: The price of the shipping method.
	 *               - name: The name of the shipping method in the specified language.
	 *               - reference: The reference of the shipping method.
	 */
	public function estimate( WC_Cart $cart, array $address ) {
		WC()->shipping()->calculate_shipping(
			$this->get_shipping_packages(
				$cart,
				$address['country'],
				$address['region'],
				$address['zipCode'],
				$address['city'],
				$address['line1'],
				$address['line2'],
			)
		);

		$active_methods = array();
		$shipping_methods = WC()->shipping()->packages;
		if ( $shipping_methods ) {
			$shipping_methods = array_shift( $shipping_methods );
			if ( isset( $shipping_methods['rates'] ) ) {
				foreach ( $shipping_methods['rates'] as $shipping_method ) {
					$active_methods[] = array(
						'price'     => number_format( $shipping_method->cost, 2, '.', '' ),
						'name'      => $shipping_method->label,
						'countries' => array( $address['country'] ),
						'reference' => $shipping_method->method_id,
					);
				}
			}
		}

		return $active_methods;
	}

	/**
	 * Get the shipping packages for the given cart and address.
	 *
	 * @param WC_Cart $cart The cart object.
	 * @param string $country The country code.
	 * @param string $state The state code.
	 * @param string $postcode The zip code.
	 * @param string $city The city.
	 * @param string $address The address line 1.
	 * @param string $address2 The address line 2.
	 *
	 * @return array The shipping packages.
	 */
	private function get_shipping_packages(
		WC_Cart $cart,
		$country,
		$state,
		$postcode,
		$city,
		$address,
		$address2
	) {
		$packages = array(
			array(
				'contents'      => $cart->cart_contents,
				'contents_cost' => $cart->get_total(),
				'destination'   => array(
					'country'   => $country,
					'state'     => $state,
					'postcode'  => $postcode,
					'city'      => $city,
					'address'   => $address,
					'address_2' => $address2,
				),
			),
		);

		return apply_filters( 'woocommerce_cart_shipping_packages', $packages );
	}
}
