<?php
/**
 * Server-Side Render template for wm/modular-type-container
 *
 * The editor of this block cannot hold inner blocks, and no stylesheet reads the two custom properties
 * it sets, so the container has no effect on a page. Until 1.2.2 it filled that gap with a demo text
 * on the public page ("Alle verschachtelten Überschriften und Absätze skalieren harmonisch"). Content
 * written into the block by hand in the code editor is still printed.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === trim( (string) $content ) ) {
	echo wp_kses_post( wm_suite_hub_block_notice( __( 'Dieser Container nimmt im Editor keine Inhalte auf und wirkt nicht auf die Seite; Besucher sehen ihn nicht.', 'wm-suite-hub' ) ) );
	return;
}

$ratio = sanitize_text_field( (string) ( $attributes['scaleRatio'] ?? '1.25' ) );
$base  = sanitize_text_field( (string) ( $attributes['baseSize'] ?? '1rem' ) );
?>
<div class="wm-block-container wm-modular-type-container" style="--wm-scale-ratio: <?php echo esc_attr( $ratio ); ?>; --wm-font-size-root: <?php echo esc_attr( $base ); ?>;">
	<?php echo wp_kses_post( $content ); ?>
</div>
