<?php
/**
 * Server-Side Render template for wm/cra-security-status
 *
 * A download link for the SBOM that `wp wm-hub sbom --output=<file>` writes, once the editor enters
 * where that file is published. Until 1.2.2 the box was headed "EU Cyber Resilience Act (CRA)" with a
 * "BSI TR-03183" badge and stated that components and SBOMs "werden kryptografisch auditiert und
 * kontinuierlich überwacht" — nothing in this plugin audits or monitors them — and it showed all of
 * that with no download at all while the URL was empty.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The default used to be rest_url( 'wm-suite/v1/sbom' ), a route this plugin never registers, so the
// button led to a 404. Empty means no box rather than a broken link.
$sbom_url = esc_url( (string) ( $attributes['sbomUrl'] ?? '' ) );
$version  = sanitize_text_field( (string) ( $attributes['version'] ?? '' ) );

if ( '' === $sbom_url ) {
	echo wp_kses_post( wm_suite_hub_unconfigured_notice( __( 'SBOM-Download-URL', 'wm-suite-hub' ) ) );
	return;
}
?>
<div class="wm-block-container wm-privacy-center" style="max-width: 480px;">
	<h4 style="margin: 0 0 var(--wm-space-sm) 0; font-size: 1rem; font-weight: 700;">
		<?php echo wp_kses( wm_suite_hub_icon( 'package' ), wm_suite_hub_icon_allowed_html() ); ?>
		<?php esc_html_e( 'Software-Stückliste (SBOM)', 'wm-suite-hub' ); ?><?php echo '' !== $version ? ' · ' . esc_html( $version ) : ''; ?>
	</h4>
	<a href="<?php echo esc_url( $sbom_url ); ?>" class="wm-btn-primary" target="_blank" rel="noopener noreferrer" style="font-size: 0.8125rem; padding: var(--wm-space-xs) var(--wm-space-sm); width: auto; display: inline-flex;">
		<?php esc_html_e( 'CycloneDX 1.6 SBOM herunterladen', 'wm-suite-hub' ); ?>
	</a>
</div>
