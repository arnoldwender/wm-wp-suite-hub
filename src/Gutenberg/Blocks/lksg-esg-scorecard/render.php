<?php
/**
 * Server-Side Render template for wm/lksg-esg-scorecard
 *
 * Three percentages exactly as the editor typed them; the block computes and checks nothing. Until
 * 1.2.2 it captioned them "Transparenzbericht über Tier-1 und Tier-2 Auditprüfungen gemäß §§ 4-10
 * LkSG" and showed its fill-in notice to visitors.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$raw_hr  = $attributes['humanRightsScore'] ?? null;
$raw_env = $attributes['environmentScore'] ?? null;
$raw_gov = $attributes['governanceScore'] ?? null;

$hr_score  = ( null !== $raw_hr && '' !== $raw_hr && is_numeric( $raw_hr ) ) ? (int) $raw_hr : -1;
$env_score = ( null !== $raw_env && '' !== $raw_env && is_numeric( $raw_env ) ) ? (int) $raw_env : -1;
$gov_score = ( null !== $raw_gov && '' !== $raw_gov && is_numeric( $raw_gov ) ) ? (int) $raw_gov : -1;

$is_unconfigured = ( $hr_score < 0 && $env_score < 0 && $gov_score < 0 );

if ( $is_unconfigured ) {
	echo wp_kses_post( wm_suite_hub_block_notice( __( 'Noch keine geprüften LkSG-Kennzahlen eingetragen — bitte Werte im Block-Inspektor hinterlegen.', 'wm-suite-hub' ) ) );
	return;
}

$format_score = static function ( int $value ): string {
	if ( $value < 0 ) {
		return '—';
	}
	$clamped = max( 0, min( 100, $value ) );
	return (string) $clamped . '%';
};
?>
<section class="wm-block-container wm-privacy-center">
	<h4 style="margin: 0 0 var(--wm-space-md) 0; font-size: 1.25rem;">
		<?php echo wp_kses( wm_suite_hub_icon( 'leaf' ), wm_suite_hub_icon_allowed_html() ); ?> <?php esc_html_e( 'Lieferketten-Sorgfaltspflicht (LkSG & CSDDD)', 'wm-suite-hub' ); ?>
	</h4>

	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: var(--wm-space-sm); text-align: center;">
		<div style="background: var(--wm-color-bg); padding: var(--wm-space-md); border-radius: var(--wm-radius-sm); border: 1px solid var(--wm-color-border);">
			<div style="font-size: 1.5rem; font-weight: 700; color: var(--wm-badge-emerald);"><?php echo esc_html( $format_score( $hr_score ) ); ?></div>
			<div style="font-size: 0.75rem; color: var(--wm-text-muted); margin-top: var(--wm-space-xs);"><?php esc_html_e( 'Menschenrechte', 'wm-suite-hub' ); ?></div>
		</div>
		<div style="background: var(--wm-color-bg); padding: var(--wm-space-md); border-radius: var(--wm-radius-sm); border: 1px solid var(--wm-color-border);">
			<div style="font-size: 1.5rem; font-weight: 700; color: var(--wm-badge-sky);"><?php echo esc_html( $format_score( $env_score ) ); ?></div>
			<div style="font-size: 0.75rem; color: var(--wm-text-muted); margin-top: var(--wm-space-xs);"><?php esc_html_e( 'Umweltschutz', 'wm-suite-hub' ); ?></div>
		</div>
		<div style="background: var(--wm-color-bg); padding: var(--wm-space-md); border-radius: var(--wm-radius-sm); border: 1px solid var(--wm-color-border);">
			<div style="font-size: 1.5rem; font-weight: 700; color: var(--wm-badge-violet);"><?php echo esc_html( $format_score( $gov_score ) ); ?></div>
			<div style="font-size: 0.75rem; color: var(--wm-text-muted); margin-top: var(--wm-space-xs);"><?php esc_html_e( 'Gute Governance', 'wm-suite-hub' ); ?></div>
		</div>
	</div>
</section>
