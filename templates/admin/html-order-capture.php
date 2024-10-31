<?php

defined( 'ABSPATH' ) || exit;

/** @var WC_Order $order */
/** @var int $order_id */
/** @var float $total_captured */
/** @var float $remaining_total */
/** @var array $providers_list */
/** @var bool $disabled */
$currency = $order->get_currency();
$allowed_tags = array(
	'span' => array(),
	'bdi'  => array(),
);
?>
<div class="wc-order-data-row wc-order-data-row-toggle wc-payever-capture" style="display:none;">
	<div class="wc-order-totals payever-order-totals">
		<div class="wc-order-row">
			<span class="label"><?php esc_html_e( 'Amount already captured', 'payever-woocommerce-gateway' ); ?>:</span>
			<span class="total"><?php echo wp_kses( wc_price( $total_captured, array( 'currency' => $currency ) ), $allowed_tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>

		<?php if ( $remaining_total > 0 ) : ?>
			<div class="wc-order-row">
				<span class="label"><?php esc_html_e( 'Remaining order total', 'payever-woocommerce-gateway' ); ?>:</span>
				<span class="total"><?php echo wp_kses( wc_price( $remaining_total, array( 'currency' => $currency ) ), $allowed_tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
		<?php endif; ?>

		<div class="wc-order-row">
			<span class="label"><label for="capture_amount"><?php esc_html_e( 'Capture amount', 'payever-woocommerce-gateway' ); ?>:</label></span>
			<span class="total">
				<input type="text" class="text wc_input_price" id="capture_amount" name="capture_amount"
				<?php if ( $disabled ) : ?>
					disabled="disabled"
				<?php endif; ?> />
			</span>
		</div>
		<div class="wc-order-row">
			<span class="label"><label for="capture_comment"><?php esc_html_e( 'Comment (optional):', 'payever-woocommerce-gateway' ); ?></label></span>
			<span class="total">
				<input type="text" class="text" id="capture_comment" name="capture_comment" />
			</span>
		</div>

		<?php if ( $order->needs_processing() ) : ?>
			<?php if ( count( $providers_list ) > 0 ) : ?>
				<div class="wc-order-row">
					<span class="label">
						<label for="wc_shipping_provider">
							<?php esc_html_e( 'Shipping Provider', 'payever-woocommerce-gateway' ); ?>
						</label>
					</span>
					<span class="total">
							<select id="wc_shipping_provider" name="wc_shipping_provider" class="chosen_select">
								<option value="" selected>
									<?php esc_html_e( 'Custom Provider', 'payever-woocommerce-gateway' ); ?>
								</option>
								<?php foreach ( $providers_list as $provider_group => $providers ) : ?>
								<optgroup label="<?php printf( '%1$s', esc_attr( $provider_group ) ); ?>">
									<?php foreach ( $providers as $provider => $url ) : ?>
										<option value="<?php echo esc_attr( wc_clean( $provider ) ); ?>"
												data-url="<?php echo esc_attr( wc_clean( $url ) ); ?>">
											<?php echo esc_html( $provider ); ?>
										</option>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</select>
					</span>
				</div>
			<?php endif; ?>

			<div id="provider_custom_row" class="wc-order-row">
				<span class="label">
					<label for="wc_shipping_provider_custom">
						<?php esc_html_e( 'Shipping Provider Name', 'payever-woocommerce-gateway' ); ?>:
					</label>
				</span>
				<span class="total">
					<input type="text" class="text" id="wc_shipping_provider_custom" name="wc_shipping_provider_custom"  />
				</span>
			</div>

			<div class="wc-order-row">
				<span class="label">
					<label for="wc_tracking_number">
						<?php esc_html_e( 'Shipping Tracking Number', 'payever-woocommerce-gateway' ); ?>:
					</label>
				</span>
				<span class="total">
					<input type="text" class="text" id="wc_tracking_number" name="wc_tracking_number" />
				</span>
			</div>

			<div id="tracking_url_row" class="wc-order-row">
				<span class="label">
					<label for="wc_tracking_url">
						<?php esc_html_e( 'Shipping Tracking Url', 'payever-woocommerce-gateway' ); ?>
					</label>
				</span>
				<span class="total">
					<input type="text" class="text" id="wc_tracking_url" name="wc_tracking_url" placeholder="http://" />
				</span>
			</div>
			<div class="wc-order-row">
				<span class="label">
					<label for="wc_shipping_date">
						<?php esc_html_e( 'Shipping Date', 'payever-woocommerce-gateway' ); ?>
					</label>
				</span>
				<span class="total">
					<input type="text"
						class="date-picker-field"
						id="wc_shipping_date"
						name="wc_shipping_date"
						placeholder="<?php echo esc_html( date_i18n( 'Y-m-d', time() ) ); ?>"
						value="<?php echo esc_html( date_i18n( 'Y-m-d', time() ) ); ?>"
					/>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<div class="clear"></div>
	<div class="capture-actions">
		<?php $amount = '<span class="capture-amount">' . wc_price( 0, array( 'currency' => $currency ) ) . '</span>'; ?>
		<button type="button" class="button button-primary payever-capture-action" data-order-id="<?php echo esc_attr( $order_id ); ?>" disabled="disabled">
			<?php
			printf(
				/* translators: %s: capture amount */
				esc_html__( 'Capture %s', 'payever-woocommerce-gateway' ),
				wp_kses( $amount, $allowed_tags )
			);
			?>
		</button>
		<button type="button" class="button cancel-action">
			<?php esc_html_e( 'Cancel', 'payever-woocommerce-gateway' ); ?>
		</button>

		<div class="clear"></div>
	</div>
</div>
<?php
wp_print_inline_script_tag(
	'
		if (typeof woocommerce_admin_meta_boxes !== "undefined") {
			woocommerce_admin_meta_boxes["payever_capture_item_nonce"] = "' . esc_html( wp_create_nonce( 'wp_ajax_payever_capture_item' ) ) . '"
		}
	'
);
?>
