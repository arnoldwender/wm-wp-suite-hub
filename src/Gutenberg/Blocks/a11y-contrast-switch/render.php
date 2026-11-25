<?php
/**
 * Server-Side Render template for wm/a11y-contrast-switch
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_contrast = (bool) ( $attributes['showHighContrast'] ?? true );
$show_dyslexic = (bool) ( $attributes['showDyslexicFont'] ?? true );
$show_scaler   = (bool) ( $attributes['showTextScaler'] ?? true );
?>
<aside class="wm-block-container wm-a11y-bar" aria-label="<?php esc_attr_e( 'Barrierefreiheits-Einstellungen', 'wm-suite-hub' ); ?>">
	<?php if ( $show_contrast ) : ?>
		<button type="button" class="wm-a11y-btn" data-wm-a11y-action="toggle-contrast" aria-pressed="false">
			<?php echo wp_kses( wm_suite_hub_icon( 'eye' ), wm_suite_hub_icon_allowed_html() ); ?> <?php esc_html_e( 'Kontrast', 'wm-suite-hub' ); ?>
		</button>
	<?php endif; ?>

	<?php if ( $show_dyslexic ) : ?>
		<button type="button" class="wm-a11y-btn" data-wm-a11y-action="toggle-dyslexic" aria-pressed="false">
			<?php echo wp_kses( wm_suite_hub_icon( 'book' ), wm_suite_hub_icon_allowed_html() ); ?> <?php esc_html_e( 'Leseschrift', 'wm-suite-hub' ); ?>
		</button>
	<?php endif; ?>

	<?php if ( $show_scaler ) : ?>
		<button type="button" class="wm-a11y-btn" data-wm-a11y-action="scale-120" aria-pressed="false">
			A+
		</button>
		<button type="button" class="wm-a11y-btn" data-wm-a11y-action="scale-140" aria-pressed="false">
			A++
		</button>
		<button type="button" class="wm-a11y-btn" data-wm-a11y-action="scale-reset" aria-pressed="false">
			A (Standard)
		</button>
	<?php endif; ?>
</aside>
