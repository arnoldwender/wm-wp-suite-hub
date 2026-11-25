<?php
/**
 * What a block prints when it lacks what it needs to work.
 *
 * A visitor gets nothing, and whoever can edit posts gets a notice that names what is missing. Until
 * 1.2.2 the blocks did the opposite in two ways: some printed their editor notice ("bitte im
 * Block-Inspektor eintragen") on the public page, and others filled the gap with content of their
 * own — a conformity statement dated August 2026, a whistleblower form that sent nothing, consent
 * switches no script read, a booking link to a checkout page that does not exist.
 *
 * @package ArnoldWender\SuiteHub\Gutenberg
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wm_suite_hub_block_notice' ) ) {
	/**
	 * The editor-only notice, or an empty string for everyone else.
	 *
	 * The block editor previews a block through the REST block renderer, which runs as the editing
	 * user, so the notice shows in the editor and on the page for logged-in editors only.
	 *
	 * @param string $message Plain text; it is escaped here.
	 * @return string Ready-to-echo markup, or ''.
	 */
	function wm_suite_hub_block_notice( string $message ): string {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="wm-block-container wm-block-notice">' . esc_html( $message ) . '</p>';
	}
}

if ( ! function_exists( 'wm_suite_hub_missing_plugin_notice' ) ) {
	/**
	 * Notice for a block whose content comes from another WM Suite plugin that is not active.
	 *
	 * @param string $plugin_name The plugin's name as its header gives it.
	 * @return string Ready-to-echo markup, or ''.
	 */
	function wm_suite_hub_missing_plugin_notice( string $plugin_name ): string {
		/* translators: %s: name of the WordPress plugin the block needs */
		$text = __( 'Ohne aktives Plugin „%s“ zeigt dieser Block Besuchern nichts an.', 'wm-suite-hub' );
		return wm_suite_hub_block_notice( sprintf( $text, $plugin_name ) );
	}
}

if ( ! function_exists( 'wm_suite_hub_unconfigured_notice' ) ) {
	/**
	 * Notice for a block that needs a value from the block inspector before it can show anything.
	 *
	 * @param string $field_label Label of the inspector field, as the editor shows it.
	 * @return string Ready-to-echo markup, or ''.
	 */
	function wm_suite_hub_unconfigured_notice( string $field_label ): string {
		/* translators: %s: label of a field in the block inspector */
		$text = __( 'Noch nicht konfiguriert — bitte „%s“ im Block-Inspektor eintragen. Besucher sehen den Block bis dahin nicht.', 'wm-suite-hub' );
		return wm_suite_hub_block_notice( sprintf( $text, $field_label ) );
	}
}
