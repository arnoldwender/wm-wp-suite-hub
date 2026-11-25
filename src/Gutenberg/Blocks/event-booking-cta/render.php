<?php
/**
 * Server-Side Render template for wm/event-booking-cta
 *
 * A button to the booking page the editor enters, with an optional note under it. Until 1.2.2 the
 * button pointed at /kasse/?wm_book_event=<id>, a page and a parameter no plugin of the suite
 * provides, and printed "Preise zzgl. 19% MwSt. · Konforme E-Rechnung (EN 16931) für B2B-Kunden"
 * under every button — a net-price statement and an e-invoicing promise for whatever was sold there.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$booking_url = esc_url( (string) ( $attributes['bookingUrl'] ?? '' ) );
if ( '' === $booking_url ) {
	echo wp_kses_post( wm_suite_hub_unconfigured_notice( __( 'Buchungsseite (URL)', 'wm-suite-hub' ) ) );
	return;
}

$text = sanitize_text_field( (string) ( $attributes['buttonText'] ?? '' ) );
if ( '' === $text ) {
	$text = __( 'Zur Buchung', 'wm-suite-hub' );
}
$note = sanitize_text_field( (string) ( $attributes['taxNote'] ?? '' ) );
?>
<div class="wm-block-container wm-booking-cta-wrap" style="max-width: 380px;">
	<a href="<?php echo esc_url( $booking_url ); ?>" class="wm-btn-primary">
		<?php echo wp_kses( wm_suite_hub_icon( 'ticket' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $text ); ?>
	</a>
	<?php if ( '' !== $note ) : ?>
		<p style="font-size: 0.75rem; color: var(--wm-text-muted); text-align: center; margin-top: var(--wm-space-xs);">
			<?php echo esc_html( $note ); ?>
		</p>
	<?php endif; ?>
</div>
