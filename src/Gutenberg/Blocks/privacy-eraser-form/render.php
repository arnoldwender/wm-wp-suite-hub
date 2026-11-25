<?php
/**
 * Server-Side Render template for wm/privacy-eraser-form
 *
 * Shows the erasure request form of WM DSGVO Privacy Eraser ([wm_dsgvo_erasure_portal]), which posts
 * the address to wm-dsgvo-eraser/v1/request and mails a confirmation link. Until 1.2.2 this block drew
 * its own form, whose address field had no name, so it posted an empty request to that route; and it
 * promised a confirmation token by double opt-in whether or not that plugin was active.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'shortcode_exists' ) || ! shortcode_exists( 'wm_dsgvo_erasure_portal' ) ) {
	echo wp_kses_post( wm_suite_hub_missing_plugin_notice( 'WM DSGVO Art. 17 Cryptographic Privacy Eraser' ) );
	return;
}
?>
<div class="wm-block-container wm-privacy-eraser-form">
	<?php
	// The form (input, button) is markup wp_kses_post() would strip; the eraser plugin escapes it.
	echo do_shortcode( '[wm_dsgvo_erasure_portal]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>
