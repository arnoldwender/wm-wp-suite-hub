<?php
/**
 * Server-Side Render template for wm/privacy-center
 *
 * A heading and the button of WM Sovereign Consent Vault ([wm_consent_settings]) that opens its
 * consent dialog, where the visitor's choice is stored. Until 1.2.2 the block drew three switches of
 * its own that no script read, so a visitor could switch them without anything being recorded, under
 * the sentence "Konform nach TDDDG § 25 und DSGVO Art. 7 … revisionssicher im Merkle-Audit-Tree
 * protokolliert".
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

$heading = sanitize_text_field( (string) ( $attributes['heading'] ?? '' ) );
if ( '' === $heading ) {
	$heading = __( 'Datenschutz-Einstellungen', 'wm-suite-hub' );
}
?>
<section class="wm-block-container wm-privacy-center" id="wm-privacy-preferences">
	<h3 style="margin-top: 0; font-size: 1.5rem; font-weight: 700;">
		<?php echo wp_kses( wm_suite_hub_icon( 'shield' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $heading ); ?>
	</h3>
	<?php
	// A button carrying data-wm-consent-open; wp_kses_post() keeps buttons and data-* attributes.
	echo wp_kses_post( do_shortcode( '[wm_consent_settings]' ) );
	?>
</section>
