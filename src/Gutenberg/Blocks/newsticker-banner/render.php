<?php
/**
 * Server-Side Render template for wm/newsticker-banner
 *
 * A scrolling line of the editor's own text with a pause button. Until 1.2.2 a newly inserted ticker
 * ran three invented headlines ("Weinberg Campus Innovation Hub eröffnet neue Labore · B2B E-Rechnung
 * 2027 Workshop ausgebucht · …") until somebody replaced them.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text = sanitize_text_field( (string) ( $attributes['tickerText'] ?? '' ) );
if ( '' === $text ) {
	echo wp_kses_post( wm_suite_hub_unconfigured_notice( __( 'Ticker-Meldungen', 'wm-suite-hub' ) ) );
	return;
}

$badge = sanitize_text_field( (string) ( $attributes['badgeText'] ?? '' ) );
?>
<aside class="wm-block-container wm-newsticker-wrap" aria-label="<?php esc_attr_e( 'Nachrichten-Ticker', 'wm-suite-hub' ); ?>">
	<?php if ( '' !== $badge ) : ?>
		<div class="wm-newsticker-badge">
			<?php echo esc_html( $badge ); ?>
		</div>
	<?php endif; ?>
	<div class="wm-newsticker-viewport" aria-live="off">
		<div class="wm-newsticker-track">
			<span><?php echo esc_html( $text ); ?></span>
		</div>
	</div>
	<button type="button" class="wm-newsticker-pause-btn" aria-label="<?php esc_attr_e( 'Ticker anhalten oder fortsetzen', 'wm-suite-hub' ); ?>" aria-pressed="false">
		<span class="wm-sr-status" style="display: none;"><?php esc_html_e( 'Pausieren', 'wm-suite-hub' ); ?></span>
		<?php echo wp_kses( wm_suite_hub_icon( 'pause' ), wm_suite_hub_icon_allowed_html() ); ?>
	</button>
</aside>
