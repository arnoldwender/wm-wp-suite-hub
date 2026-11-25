<?php
/**
 * Server-Side Render template for wm/script-placeholder
 *
 * A placeholder with a load button for external content. The button inserts the content of the
 * <template> whose ID the editor enters, and nothing inside a <template> loads before that; a hidden
 * element is only shown again, and an iframe in it has loaded with the page already. Until 1.2.2 the
 * block announced "wurde blockiert … nach TDDDG § 25 vorab blockiert" while it only un-hid an element,
 * so the provider had received the visitor's data before the button was ever pressed.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$target = sanitize_text_field( (string) ( $attributes['targetElementId'] ?? '' ) );
if ( '' === $target ) {
	echo wp_kses_post( wm_suite_hub_unconfigured_notice( __( 'Ziel-Element-ID', 'wm-suite-hub' ) ) );
	return;
}

$service = sanitize_text_field( (string) ( $attributes['serviceName'] ?? '' ) );
?>
<div class="wm-block-container wm-script-placeholder" data-unblock-target="<?php echo esc_attr( $target ); ?>">
	<?php if ( '' !== $service ) : ?>
		<h4 style="margin: 0 0 var(--wm-space-xs) 0; font-size: 1.125rem;">
			<?php echo esc_html( $service ); ?>
		</h4>
	<?php endif; ?>
	<p style="color: var(--wm-text-secondary); font-size: 0.8125rem; max-width: 440px; margin: 0 auto var(--wm-space-md) auto;">
		<?php esc_html_e( 'Beim Laden dieses Inhalts werden Daten an den Anbieter übertragen.', 'wm-suite-hub' ); ?>
	</p>
	<button type="button" class="wm-btn-primary wm-unblock-btn" style="max-width: 260px; margin: 0 auto;">
		<?php esc_html_e( 'Inhalt einmalig laden', 'wm-suite-hub' ); ?>
	</button>
</div>
