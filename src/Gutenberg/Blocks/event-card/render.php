<?php
/**
 * Server-Side Render template for wm/event-card
 *
 * The values the editor types, nothing read live: free seats are the number entered, not a count.
 * The button goes to the booking page the editor enters. Until 1.2.2 it read "Jetzt verbindlich
 * buchen" and led to #book-event, an anchor nothing defined, a sold-out card offered a disabled
 * "Warteliste" no plugin keeps, and the fill-in notice was shown to visitors.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title       = sanitize_text_field( (string) ( $attributes['title'] ?? '' ) );
$category    = sanitize_text_field( (string) ( $attributes['category'] ?? '' ) );
$date        = sanitize_text_field( (string) ( $attributes['date'] ?? '' ) );
$location    = sanitize_text_field( (string) ( $attributes['location'] ?? '' ) );
$raw_cap     = $attributes['capacityRemaining'] ?? null;
$capacity    = ( null !== $raw_cap && '' !== $raw_cap && is_numeric( $raw_cap ) ) ? (int) $raw_cap : -1;
$price       = sanitize_text_field( (string) ( $attributes['price'] ?? '' ) );
$booking_url = esc_url( (string) ( $attributes['bookingUrl'] ?? '' ) );

if ( '' === trim( $title ) ) :
	echo wp_kses_post( wm_suite_hub_block_notice( __( 'Veranstaltungskarte noch nicht konfiguriert — bitte Titel und Veranstaltungsdaten im Block-Inspektor eintragen.', 'wm-suite-hub' ) ) );
	return;
endif;

$dot_class = 'wm-capacity-dot';
$cap_text  = '';
if ( 0 === $capacity ) {
	$dot_class .= ' wm-capacity-dot--empty';
	$cap_text   = __( 'Ausgebucht', 'wm-suite-hub' );
} elseif ( $capacity > 0 && $capacity < 5 ) {
	$dot_class .= ' wm-capacity-dot--low';
	/* translators: %d: remaining seats */
	$low_text = __( 'Nur noch %d Plätze', 'wm-suite-hub' );
	$cap_text = sprintf( $low_text, $capacity );
} elseif ( $capacity >= 5 ) {
	/* translators: %d: remaining seats */
	$free_text = __( '%d freie Plätze', 'wm-suite-hub' );
	$cap_text  = sprintf( $free_text, $capacity );
}
?>
<article class="wm-block-container wm-event-card">
	<div>
		<?php if ( '' !== $category ) : ?>
			<span class="wm-event-card__badge"><?php echo esc_html( $category ); ?></span>
		<?php endif; ?>
		<h3 class="wm-event-card__title"><?php echo esc_html( $title ); ?></h3>
		<?php if ( '' !== $location || '' !== $date || '' !== $price ) : ?>
			<div class="wm-event-card__meta">
				<?php if ( '' !== $location ) : ?>
					<span><?php echo wp_kses( wm_suite_hub_icon( 'location' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $location ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $date ) : ?>
					<span><?php echo wp_kses( wm_suite_hub_icon( 'calendar' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $date ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $price ) : ?>
					<span><?php echo wp_kses( wm_suite_hub_icon( 'tag' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $price ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $capacity >= 0 ) : ?>
			<div class="wm-event-card__capacity">
				<span class="<?php echo esc_attr( $dot_class ); ?>"></span>
				<span><?php echo esc_html( $cap_text ); ?></span>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $booking_url && 0 !== $capacity ) : ?>
		<a href="<?php echo esc_url( $booking_url ); ?>" class="wm-btn-primary"><?php esc_html_e( 'Zur Buchung', 'wm-suite-hub' ); ?></a>
	<?php endif; ?>
</article>
