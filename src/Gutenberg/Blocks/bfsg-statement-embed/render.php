<?php
/**
 * Server-Side Render template for wm/bfsg-statement-embed
 *
 * Shows the accessibility statement that WM BFSG Compliance Dashboard builds from the operator's own
 * settings ([wm_bfsg_statement]). Until 1.2.2 this block printed a statement of its own on every page
 * it was placed on: "erfüllt die Anforderungen nach EN 301 549 … (WCAG 2.2 AA)", "Stand der
 * Konformitätsprüfung: August 2026 · Automatisch verifiziert via WM Suite Compliance Matrix" — for a
 * site nobody had assessed, by a matrix that verifies nothing.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'shortcode_exists' ) || ! shortcode_exists( 'wm_bfsg_statement' ) ) {
	echo wp_kses_post( wm_suite_hub_missing_plugin_notice( 'WM BFSG 2025 Legal Compliance Dashboard & Anlage 3 Generator' ) );
	return;
}
?>
<div class="wm-block-container wm-bfsg-statement-embed">
	<?php
	// The statement is headings, paragraphs, lists and links, all of which wp_kses_post() keeps.
	echo wp_kses_post( do_shortcode( '[wm_bfsg_statement]' ) );
	?>
</div>
