<?php
/**
 * Server-Side Render template for wm/whistleblower-card
 *
 * A link to the reporting channel: by default the portal block on the same page, or the address the
 * editor enters. Until 1.2.2 the card called the channel "HinSchG § 12 konform", promised client-side
 * RSA-4096 encryption and no IP logging for whatever channel it pointed at, showed a green status dot
 * and linked a "Status abfragen" button to an anchor nothing defined.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company     = sanitize_text_field( (string) ( $attributes['companyName'] ?? '' ) );
$channel_url = trim( (string) ( $attributes['channelUrl'] ?? '' ) );
if ( '' === $channel_url ) {
	$channel_url = '#whistleblower-wizard';
}
?>
<section class="wm-block-container wm-whistleblower-card">
	<?php if ( '' !== $company ) : ?>
		<h3 style="margin: 0 0 var(--wm-space-md) 0; font-size: 1.5rem; font-weight: 700; color: var(--wm-text-on-accent);">
			<?php echo esc_html( $company ); ?>
		</h3>
	<?php endif; ?>
	<a href="<?php echo esc_url( $channel_url ); ?>" class="wm-btn-primary">
		<?php echo wp_kses( wm_suite_hub_icon( 'megaphone' ), wm_suite_hub_icon_allowed_html() ); ?> <?php esc_html_e( 'Hinweis abgeben', 'wm-suite-hub' ); ?>
	</a>
</section>
