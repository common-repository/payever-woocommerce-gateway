<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;

abstract class WC_Payever_Notification_Abstract_Handler {
	use WC_Payever_WP_Wrapper_Trait;
	use WC_Payever_Logger_Trait;
	use WC_Payever_Helper_Trait;
	use WC_Payever_Order_Helper_Trait;
	use WC_Payever_Order_Totals_Trait;

	/**
	 * Add order note with detailed information.
	 *
	 * @param WC_Order $order
	 * @param RetrievePaymentResultEntity $payment
	 * @param float|int $amount
	 * @param array|null $processed_items
	 *
	 * @return void
	 */
	protected function add_order_note(
		WC_Order $order,
		RetrievePaymentResultEntity $payment,
		$amount,
		$processed_items = null
	) {
		$payment_status = $payment->getStatus();
		switch ( $payment_status ) {
			case Status::STATUS_PAID:
				$comment = sprintf(
					/* translators: %s: amount */
					__( 'Transaction has been shipped successfully. Amount: %s.', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount )
				);
				break;
			case Status::STATUS_REFUNDED:
				$comment = sprintf(
					/* translators: %s: amount */
					__( 'Transaction has been refunded successfully. Amount: %s.', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount )
				);
				break;
			case Status::STATUS_CANCELLED:
				$comment = sprintf(
					/* translators: %s: amount */
					__( 'Transaction has been cancelled successfully. Amount: %s.', 'payever-woocommerce-gateway' ),
					$this->get_wp_wrapper()->wc_price( $amount )
				);
				break;
			default:
				$comment = '';
				break;
		}

		// Append items
		$br_tag = '<br/>';
		if ( is_array( $processed_items ) ) {
			$processed = $br_tag;
			foreach ( $processed_items as $processed_item ) {
				$processed .= $processed_item['quantity'] . ' x ' . $processed_item['name'] . $br_tag;
			}

			$comment .= $br_tag . sprintf(
				/* translators: %s: items number */
				__( 'Items: %s', 'payever-woocommerce-gateway' ),
				$processed
			);
		}

		// Append transaction info
		$comment .= $br_tag . sprintf(
			/* translators: %s: payment status */
			__( 'Status: %1$s.', 'payever-woocommerce-gateway' ),
			ucfirst( str_replace( 'STATUS_', '', $payment_status ) )
		);

		$specific_status = $payment->getSpecificStatus();
		if ( ! empty( $specific_status ) ) {
			$comment .= ' ' . sprintf(
				/* translators: %s: specific status */
				__( 'Specific status: %1$s.', 'payever-woocommerce-gateway' ),
				$specific_status
			);
		}

		$order->add_order_note( sprintf( '<p style="color: green;">%s</p>', $comment ) );
	}
}
