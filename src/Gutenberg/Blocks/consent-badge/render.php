<?php
/**
 * Server-Side Render template for wm/consent-badge
 *
 * A link that opens the consent dialog of WM Sovereign Consent Vault: its script opens the dialog for
 * any element with data-wm-consent-open and for links to #wm-consent-settings. Until 1.2.2 the badge
 * read "DSGVO & TDDDG Versiegelt" and linked to the switches of the privacy-center block, which
 * recorded nothing.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'shortcode_exists' ) || ! shortcode_exists( 'wm_consent_settings' ) ) {
	echo wp_kses_post( wm_suite_hub_missing_plugin_notice( 'WM Sovereign Consent Vault (TDDDG § 25 & DSGVO)' ) );
	return;
}

$label = sanitize_text_field( (string) ( $attributes['label'] ?? '' ) );
if ( '' === $label ) {
	$label = __( 'Datenschutz-Einstellungen', 'wm-suite-hub' );
}
?>
<div class="wm-block-container" style="display: inline-block;">
	<a href="#wm-consent-settings" class="wm-consent-badge" data-wm-consent-open>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
		</svg>
		<span><?php echo esc_html( $label ); ?></span>
	</a>
</div>
