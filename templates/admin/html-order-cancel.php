<?php

defined( 'ABSPATH' ) || exit;

/** @var WC_Order $order */
/** @var int $order_id */
/** @var float $total_cancelled */
$currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();
$allowed_tags = array(
	'span' => array(),
	'bdi'  => array(),
);
?>
<div class="wc-order-data-row wc-order-data-row-toggle wc-payever-cancel" style="display: none;">
	<div class="wc-order-totals payever-order-totals">
		<div class="wc-order-row">
			<span class="label"><?php esc_html_e( 'Amount already cancelled', 'payever-woocommerce-gateway' ); ?>:</span>
			<span class="total"><?php echo wp_kses( wc_price( $total_cancelled, array( 'currency' => $currency ) ), $allowed_tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<div class="wc-order-row">
			<span class="label"><label for="cancel_amount"><?php esc_html_e( 'Cancel amount', 'payever-woocommerce-gateway' ); ?>:</label></span>
			<span class="total">
				<input type="text" class="text wc_input_price" id="cancel_amount" name="cancel_amount" disabled="disabled" />
			</span>
		</div>
	</div>
	<div class="clear"></div>
	<div class="cancel-actions">
		<?php $amount = '<span class="cancel-amount">' . wc_price( 0, array( 'currency' => $currency ) ) . '</span>'; ?>
		<button type="button" class="button button-primary payever-cancel-action" data-order-id="<?php echo esc_attr( $order_id ); ?>" disabled="disabled">
			<?php
			printf(
				/* translators: %s: cancel amount */
				esc_html__( 'Cancel %s', 'payever-woocommerce-gateway' ),
				wp_kses( $amount, $allowed_tags )
			);
			?>
		</button>
		<button type="button" class="button cancel-action"><?php esc_html_e( 'Cancel', 'payever-woocommerce-gateway' ); ?></button>
		<div class="clear"></div>
	</div>
</div>
<?php
wp_print_inline_script_tag(
	'
		if (typeof woocommerce_admin_meta_boxes !== "undefined") {
			woocommerce_admin_meta_boxes["payever_cancel_item_nonce"] = "' . esc_html( wp_create_nonce( 'wp_ajax_payever_cancel_item' ) ) . '"
		}
	'
);
?>
