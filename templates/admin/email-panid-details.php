<?php

defined( 'ABSPATH' ) || exit;

/** @var WC_Order $order */
$text_align = is_rtl() ? 'right' : 'left';
$pan_id = $order->get_meta( WC_Payever_Gateway::META_PAN_ID );
if ( ! empty( $pan_id ) ) { ?>
	<div style="margin-bottom: 40px;">
		<div style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;border:1px solid;padding:6px;">
			<div class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;">
				<?php
				echo wp_kses_post(
					'Sie haben bei dieser Bestellung eine Zahlungsart der Santander gewählt.
                        In Kürze erhalten Sie von der Santander eine Email mit allen Informationen
                        zu den weiteren Schritten und Zahlungsfristen. Bitte achten Sie darauf,
                        das Geld nicht an uns, sondern an das unten angegebene Konto der Santander
                        zu überweisen und dabei den folgenden Verwendungszweck anzugeben:'
				);
				?>
				<br><br>
				<?php echo wp_kses_post( 'Empfänger: Santander Consumer Bank AG' ); ?><br>
				<?php echo wp_kses_post( 'IBAN: DE89 3101 0833 8810 0761 20' ); ?><br>
				<?php echo wp_kses_post( 'BIC: SCFBDE33XXX' ); ?><br>
				<?php echo wp_kses_post( 'Betrag: ' ); ?><?php echo wp_kses_post( $order->get_total() ); ?><br>
				<?php echo wp_kses_post( 'Verwendungszweck: ' ); ?><?php echo wp_kses_post( $pan_id ); ?>
			</div>
		</div>
	</div>
<?php } ?>
