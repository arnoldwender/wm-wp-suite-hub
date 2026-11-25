<?php
/**
 * Server-Side Render template for wm/whistleblower-wizard
 *
 * Shows the reporting portal of WM HinSchG Whistleblower Desk ([wm_hinschg_portal]), which stores
 * the report and issues the case ID and access key. Until 1.2.2 this block drew a three-step form of
 * its own that sent nothing anywhere, announced "Meldung erfolgreich verschlüsselt!" and handed out a
 * random key no reporting office could ever look up.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'shortcode_exists' ) || ! shortcode_exists( 'wm_hinschg_portal' ) ) {
	echo wp_kses_post( wm_suite_hub_missing_plugin_notice( 'WM HinSchG Whistleblower Desk' ) );
	return;
}
?>
<div class="wm-block-container wm-whistleblower-wizard" id="whistleblower-wizard">
	<?php
	// The portal is a form (select, inputs) that wp_kses_post() would strip; the whistleblower
	// plugin escapes its own markup.
	echo do_shortcode( '[wm_hinschg_portal]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>
