<?php
/**
 * Server-Side Render template for wm/peppol-id-verifier
 *
 * Checks in the browser whether an ID has the form Scheme:ID (four digits, a colon, an identifier).
 * It looks nothing up: until 1.2.2 a well-formed ID was answered "Gültige Peppol / GEBA ID" under a
 * promise of automated XRechnung 4.0 / ZUGFeRD 2.5 delivery, although no participant was queried.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading     = sanitize_text_field( (string) ( $attributes['heading'] ?? 'Peppol E-Rechnung Identifikator prüfen' ) );
$placeholder = sanitize_text_field( (string) ( $attributes['placeholder'] ?? 'z.B. 0208:0843657437 oder 9958:DE...' ) );
?>
<div class="wm-block-container wm-peppol-verifier">
	<h4 style="margin: 0 0 var(--wm-space-xs) 0; font-size: 1.125rem; font-weight: 600;">
		<?php echo wp_kses( wm_suite_hub_icon( 'document' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $heading ); ?>
	</h4>
	<p style="font-size: 0.8125rem; color: var(--wm-text-secondary); margin: 0 0 var(--wm-space-sm) 0;">
		<?php esc_html_e( 'Prüft im Browser, ob die Kennung dem Format Schema:ID entspricht (z. B. 0208:0843657437). Ob der Teilnehmer im Peppol-Netz erreichbar ist, prüft dieser Block nicht.', 'wm-suite-hub' ); ?>
	</p>
	<form class="wm-peppol-form" action="#" method="get">
		<input type="text" class="wm-input-text wm-peppol-input" placeholder="<?php echo esc_attr( $placeholder ); ?>" required pattern="^[0-9]{4}:[A-Za-z0-9\-_.]+$" aria-label="<?php esc_attr_e( 'Peppol ID', 'wm-suite-hub' ); ?>">
		<button type="submit" class="wm-btn-primary" style="width: auto;"><?php esc_html_e( 'Prüfen', 'wm-suite-hub' ); ?></button>
	</form>
	<div class="wm-peppol-result" style="margin-top: var(--wm-space-sm); font-size: 0.875rem; font-weight: 500;" aria-live="polite"></div>
</div>
