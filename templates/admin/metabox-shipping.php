<?php

defined( 'ABSPATH' ) || exit;

/** @var string $tracking_number */
/** @var string $tracking_url */
/** @var string $shipping_provider */
/** @var string $shipping_date */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>
<div class="payever-shipping-tracking">
	<p class="payever-tracking-content">
		<strong>
			<?php
				printf(
					/* translators: %s: shipping_provider */
					esc_html( '%1$s' ),
					esc_html( $shipping_provider )
				);
				?>
		</strong>
		<?php if ( ! empty( $tracking_url ) ) : ?>
			-
			<?php
				printf(
					/* translators: %s: tracking link */
					esc_html__( '<a href="%1$s" target="_blank" title="%2$s">%3$s</a>', 'payever-woocommerce-gateway' ),
					esc_url( $tracking_url ),
					esc_attr( __( 'Click here to track your shipment', 'payever-woocommerce-gateway' ) ),
					esc_attr( __( 'Track', 'payever-woocommerce-gateway' ) )
				);
			?>
		<?php endif; ?>
		<br/>
		<em>
			<?php echo esc_html( $tracking_number ); ?>
		</em>
	</p>
	<p class="payever-meta">
		<?php
		printf(
			/* translators: %s:  shipping date */
			esc_html__( 'Shipped on %s', 'payever-woocommerce-gateway' ),
			esc_html( date_i18n( wc_date_format(), $shipping_date ) )
		);
		?>
	</p>
</div>
