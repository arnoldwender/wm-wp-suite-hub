<?php
/**
 * Cockpit Admin View Template
 *
 * Semantic layout of the WM Suite cockpit. Every status it shows comes from the health matrix or the
 * registry; it states no conformity level (until 1.2.2 it printed AAA, BFSG and CRA seals of its own).
 * Default Design System: CyberEvents Enterprise (matching wm-cyber-events-core)
 * with Multi-Theme Engine & WordPress Admin native palette integration.
 *
 * @package ArnoldWender\SuiteHub\Admin\Views
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $matrix */
// A matrix without these keys reports nothing, so the defaults are the ones that do not mean "all good".
$score          = (int) ($matrix['score'] ?? 0);
$global_status  = (string) ($matrix['global_status'] ?? 'WARNING');
$counts         = $matrix['counts'] ?? ['total' => 0, 'healthy' => 0, 'degraded' => 0, 'error' => 0, 'needs_config' => 0];
$modules        = $matrix['modules'] ?? [];
$env            = $matrix['environment'] ?? [];
$active_theme   = function_exists('wm_get_suite_theme') ? wm_get_suite_theme() : 'cyber-events';
// Decorative SVG icons (wm_suite_hub_icon), escaped at every echo with the helper's own allowlist.
$wm_icon_html   = wm_suite_hub_icon_allowed_html();
// Translated status labels; the template has no namespace of its own, hence the full class name.
$wm_status_label = static fn(string $status): string => \ArnoldWender\SuiteHub\Telemetry\Health_Matrix::status_label($status);
?>

<div class="wrap wm-admin wm-cockpit-wrap" data-wm-theme="<?php echo esc_attr($active_theme); ?>">
    <!-- Skip link -->
    <a href="#wm-main-content" class="wm-skip-link">
        <?php esc_html_e('Zum Hauptinhalt springen', 'wm-suite-hub'); ?>
    </a>

    <!-- Enterprise Mission Control Command Header (CyberEvents Signature Banner) -->
    <header class="wm-enterprise-banner" role="banner">
        <div class="wm-banner-content">
            <div class="wm-banner-title">
                <h1>
                    <span class="wm-banner-icon"><?php echo wp_kses(wm_suite_hub_icon('shield'), $wm_icon_html); ?></span>
                    <span><?php esc_html_e('WM Suite Hub Command Console', 'wm-suite-hub'); ?></span>
                    <span class="wm-version-tag">v<?php echo esc_html(WM_SUITE_HUB_VERSION); ?></span>
                </h1>
                <p>
                    <?php esc_html_e('Zustand der registrierten WM-Suite-Plugins, Software-Stückliste (CycloneDX 1.6) und eine mit HMAC-SHA256 verkettete Audit-Tabelle.', 'wm-suite-hub'); ?>
                </p>
            </div>
            <div class="wm-banner-actions">
                <?php
                // Only HEALTHY is green; CRITICAL is red, anything else (a warning, an unknown status) amber.
                // Until 1.2.3 the pill was green whatever word it carried.
                $wm_badge_state = 'HEALTHY' === $global_status ? '' : ('CRITICAL' === $global_status ? ' is-critical' : ' is-warning');
                ?>
                <div class="wm-system-status-badge<?php echo esc_attr($wm_badge_state); ?>">
                    <?php if ('HEALTHY' === $global_status) : ?>
                        <span class="wm-status-dot-pulse"></span>
                    <?php endif; ?>
                    <span><?php echo esc_html($wm_status_label($global_status)); ?></span>
                </div>
                <button type="button" id="wm-btn-refresh-telemetry" class="wm-btn wm-btn-secondary" aria-label="<?php esc_attr_e('Flottenstatus aktualisieren', 'wm-suite-hub'); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <span><?php esc_html_e('Aktualisieren', 'wm-suite-hub'); ?></span>
                </button>
                <?php // No aria-label: the visible text is the accessible name (WCAG 2.5.3); until 1.2.3 it differed. ?>
                <button type="button" id="wm-btn-verify-crypto" class="wm-btn wm-btn-primary">
                    <span class="dashicons dashicons-shield"></span>
                    <span><?php esc_html_e('Audit-Integrität prüfen', 'wm-suite-hub'); ?></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs & Theme Switcher (WordPress Native Standard) -->
    <nav class="wm-nav-tabs" aria-label="<?php esc_attr_e('Cockpit Navigation', 'wm-suite-hub'); ?>">
        <a href="#wm-sec-kpis" class="wm-tab-link active">
            <span class="dashicons dashicons-dashboard"></span>
            <span><?php esc_html_e('Cockpit Übersicht', 'wm-suite-hub'); ?></span>
        </a>
        <a href="#wm-sec-marketplace" class="wm-tab-link">
            <span class="dashicons dashicons-portfolio"></span>
            <span><?php esc_html_e('Solution Packs', 'wm-suite-hub'); ?></span>
        </a>
        <a href="#wm-sec-modules" class="wm-tab-link">
            <span class="dashicons dashicons-networking"></span>
            <span><?php esc_html_e('Satelliten-Flotte', 'wm-suite-hub'); ?></span>
        </a>
        <a href="#wm-sec-audit" class="wm-tab-link">
            <span class="dashicons dashicons-shield-alt"></span>
            <span><?php esc_html_e('Audit-Protokoll', 'wm-suite-hub'); ?></span>
        </a>
        <a href="#wm-sec-theme" class="wm-tab-link">
            <span class="dashicons dashicons-admin-appearance"></span>
            <span><?php esc_html_e('Design & Themes', 'wm-suite-hub'); ?></span>
        </a>

        <!-- Global Theme Selector -->
        <div class="wm-theme-switcher-wrap">
            <span class="dashicons dashicons-art"></span>
            <label for="wm-theme-selector" class="wm-theme-switcher-label"><?php esc_html_e('Suite Theme:', 'wm-suite-hub'); ?></label>
            <select id="wm-theme-selector" class="wm-theme-select" aria-label="<?php esc_attr_e('WM Suite Theme auswählen', 'wm-suite-hub'); ?>">
                <option value="cyber-events" <?php selected($active_theme, 'cyber-events'); ?>>
                    CyberEvents (Standard)
                </option>
                <option value="wp-native" <?php selected($active_theme, 'wp-native'); ?>>
                    WordPress Native
                </option>
                <option value="obsidian-dark" <?php selected($active_theme, 'obsidian-dark'); ?>>
                    Obsidian Dark
                </option>
                <option value="high-contrast" <?php selected($active_theme, 'high-contrast'); ?>>
                    High-Contrast
                </option>
            </select>
        </div>
    </nav>

    <!-- Quick Jump Navigation -->
    <nav class="wm-jump-nav" aria-label="<?php esc_attr_e('Schnellnavigation', 'wm-suite-hub'); ?>">
        <span class="wm-jump-nav-label"><?php esc_html_e('Direkt zu:', 'wm-suite-hub'); ?></span>
        <a href="#wm-sec-guide" class="wm-jump-link"><?php echo wp_kses(wm_suite_hub_icon('bulb'), $wm_icon_html); ?> <?php esc_html_e('Kurzanleitung', 'wm-suite-hub'); ?></a>
        <a href="#wm-sec-kpis" class="wm-jump-link"><?php echo wp_kses(wm_suite_hub_icon('chart'), $wm_icon_html); ?> <?php esc_html_e('Metriken & Status', 'wm-suite-hub'); ?></a>
        <a href="#wm-sec-modules" class="wm-jump-link"><?php echo wp_kses(wm_suite_hub_icon('network'), $wm_icon_html); ?> <?php esc_html_e('Satelliten-Flotte', 'wm-suite-hub'); ?></a>
        <a href="#wm-sec-audit" class="wm-jump-link"><?php echo wp_kses(wm_suite_hub_icon('shield'), $wm_icon_html); ?> <?php esc_html_e('Audit-Protokoll', 'wm-suite-hub'); ?></a>
        <a href="#wm-sec-theme" class="wm-jump-link"><?php echo wp_kses(wm_suite_hub_icon('palette'), $wm_icon_html); ?> <?php esc_html_e('Theme-Einstellungen', 'wm-suite-hub'); ?></a>
    </nav>

    <!-- Plain Language Quick Usability Guide -->
    <section id="wm-sec-guide" class="wm-quick-guide">
        <div class="wm-quick-guide-title">
            <span><?php echo wp_kses(wm_suite_hub_icon('bulb'), $wm_icon_html); ?> <?php esc_html_e('Was ist das WM Suite Hub und wie steuern Sie Ihre Plugins?', 'wm-suite-hub'); ?></span>
        </div>
        <p class="wm-quick-guide-desc">
            <?php esc_html_e('Das WM Suite Hub ist die zentrale', 'wm-suite-hub'); ?> <strong><?php esc_html_e('Gouvernanz- und Kontrollzentrale aller WM Suite WordPress-Plugins', 'wm-suite-hub'); ?></strong><?php esc_html_e('. Es zeigt den Zustand, den die registrierten Module melden, erzeugt eine Software-Stückliste im Format CycloneDX 1.6 und verkettet die Ereignisse, die ihm gemeldet werden, mit HMAC-SHA256: Änderungen ohne den Schlüssel der Website werden erkennbar. Es bewertet keine Rechtskonformität.', 'wm-suite-hub'); ?>
        </p>
        <div class="wm-quick-guide-actions">
            <strong><?php esc_html_e('Schnellzugriff:', 'wm-suite-hub'); ?></strong>
            <a href="#wm-sec-modules" class="wm-preset-pill-btn">
                <?php echo wp_kses(wm_suite_hub_icon('network'), $wm_icon_html); ?> <?php esc_html_e('Aktive Module prüfen', 'wm-suite-hub'); ?>
            </a>
            <a href="#wm-sec-audit" class="wm-preset-pill-btn">
                <?php echo wp_kses(wm_suite_hub_icon('shield'), $wm_icon_html); ?> <?php esc_html_e('Audit-Protokoll ansehen', 'wm-suite-hub'); ?>
            </a>
            <a href="#wm-sec-theme" class="wm-preset-pill-btn">
                <?php echo wp_kses(wm_suite_hub_icon('palette'), $wm_icon_html); ?> <?php esc_html_e('Theme & Erscheinungsbild wechseln', 'wm-suite-hub'); ?>
            </a>
        </div>
    </section>

    <main id="wm-main-content" class="wm-cockpit-main" tabindex="-1">
        
        <!-- Summary Metric Cards -->
        <section id="wm-sec-kpis" class="wm-metrics-grid" aria-label="<?php esc_attr_e('Systemmetriken', 'wm-suite-hub'); ?>">
            <!-- Compliance Gauge Card -->
            <div class="wm-card wm-card-score">
                <div class="wm-card-header">
                    <span class="wm-card-eyebrow"><?php esc_html_e('GESAMTSTATUS', 'wm-suite-hub'); ?></span>
                    <span class="wm-status-pill wm-status-<?php echo esc_attr(strtolower($global_status)); ?>">
                        <?php echo esc_html($wm_status_label($global_status)); ?>
                    </span>
                </div>
                <div class="wm-score-display">
                    <span class="wm-score-value" id="wm-metric-score"><?php echo esc_html((string) $score); ?></span>
                    <span class="wm-score-unit">%</span>
                </div>
                <div class="wm-score-bar-bg">
                    <div class="wm-score-bar-fill" style="width: <?php echo esc_attr((string) $score); ?>%;"></div>
                </div>
                <p class="wm-card-caption">
                    <?php esc_html_e('Gewichteter Zustand der registrierten Module (gesund 100, eingeschränkt 60, nicht konfiguriert 30, Fehler 0).', 'wm-suite-hub'); ?>
                </p>
            </div>

            <!-- Module Counts Card -->
            <div class="wm-card">
                <div class="wm-card-header">
                    <span class="wm-card-eyebrow"><?php esc_html_e('SATELLITEN-INVENTAR', 'wm-suite-hub'); ?></span>
                    <span class="dashicons dashicons-networking"></span>
                </div>
                <div class="wm-count-grid">
                    <div class="wm-count-item">
                        <span class="wm-count-num"><?php echo esc_html((string) $counts['total']); ?></span>
                        <span class="wm-count-label"><?php esc_html_e('Registriert', 'wm-suite-hub'); ?></span>
                    </div>
                    <div class="wm-count-item wm-count-success">
                        <span class="wm-count-num"><?php echo esc_html((string) $counts['healthy']); ?></span>
                        <span class="wm-count-label"><?php esc_html_e('Gesund', 'wm-suite-hub'); ?></span>
                    </div>
                    <div class="wm-count-item wm-count-warning">
                        <span class="wm-count-num"><?php echo esc_html((string) ($counts['degraded'] + $counts['needs_config'])); ?></span>
                        <span class="wm-count-label"><?php esc_html_e('Warnungen', 'wm-suite-hub'); ?></span>
                    </div>
                    <div class="wm-count-item wm-count-danger">
                        <span class="wm-count-num"><?php echo esc_html((string) $counts['error']); ?></span>
                        <span class="wm-count-label"><?php esc_html_e('Kritisch', 'wm-suite-hub'); ?></span>
                    </div>
                </div>
            </div>

            <?php
            // Runtime card: the environment snapshot the health matrix takes. Until 1.2.3 this card was a
            // checklist of three green ticks that nothing computed. Only HTTPS carries a verdict; the other
            // lines are values. (A PHP comment on purpose: an HTML one reaches the page source.)
            $wm_https = (bool) ($env['is_ssl'] ?? false);
            ?>
            <div class="wm-card">
                <div class="wm-card-header">
                    <span class="wm-card-eyebrow"><?php esc_html_e('LAUFZEITUMGEBUNG', 'wm-suite-hub'); ?></span>
                    <span class="dashicons dashicons-admin-generic"></span>
                </div>
                <ul class="wm-security-checklist wm-runtime-list">
                    <li>
                        <span class="dashicons dashicons-info-outline"></span>
                        <span><strong>PHP:</strong> <?php echo esc_html((string) ($env['php_version'] ?? PHP_VERSION)); ?></span>
                    </li>
                    <li>
                        <span class="dashicons dashicons-info-outline"></span>
                        <span><strong>WordPress:</strong> <?php echo esc_html((string) ($env['wp_version'] ?? '')); ?></span>
                    </li>
                    <li>
                        <span class="dashicons <?php echo $wm_https ? 'dashicons-yes-alt wm-check-ok' : 'dashicons-warning wm-check-warn'; ?>"></span>
                        <span><strong>HTTPS:</strong> <?php echo $wm_https ? esc_html__('ja', 'wm-suite-hub') : esc_html__('nein', 'wm-suite-hub'); ?></span>
                    </li>
                    <li>
                        <span class="dashicons dashicons-info-outline"></span>
                        <span><strong><?php esc_html_e('Persistenter Objekt-Cache', 'wm-suite-hub'); ?>:</strong> <?php echo !empty($env['has_object_cache']) ? esc_html__('ja', 'wm-suite-hub') : esc_html__('nein', 'wm-suite-hub'); ?></span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Solution Packs Section -->
        <?php include __DIR__ . '/marketplace-view.php'; ?>

        <!-- Fleet Modules Section -->
        <section id="wm-sec-modules" class="wm-section" aria-labelledby="wm-section-modules-title">
            <div class="wm-section-header">
                <h2 id="wm-section-modules-title" class="wm-section-title">
                    <?php esc_html_e('Flottenstatus & Satelliten-Module', 'wm-suite-hub'); ?>
                </h2>
                <span class="wm-section-badge"><?php echo count($modules); ?> <?php esc_html_e('Module aktiv', 'wm-suite-hub'); ?></span>
            </div>

            <?php if (empty($modules)) : ?>
                <div class="wm-empty-state">
                    <span class="dashicons dashicons-info-outline"></span>
                    <h3><?php esc_html_e('Keine Satelliten-Module registriert', 'wm-suite-hub'); ?></h3>
                    <p><?php esc_html_e('Sobald WM Suite Plugins aktiviert werden, verbinden sie sich automatisch über den Filter wm_register_suite_module.', 'wm-suite-hub'); ?></p>
                </div>
            <?php else : ?>
                <div class="wm-modules-grid">
                    <?php foreach ($modules as $slug => $mod) : 
                        $m_status = strtolower($mod['status'] ?? 'degraded');
                    ?>
                        <article class="wm-module-card wm-module-status-<?php echo esc_attr($m_status); ?>" id="wm-module-<?php echo esc_attr($slug); ?>">
                            <div class="wm-module-header">
                                <div>
                                    <h3 class="wm-module-name"><?php echo esc_html($mod['name']); ?></h3>
                                    <span class="wm-module-slug"><code><?php echo esc_html($slug); ?></code> &bull; v<?php echo esc_html($mod['version']); ?></span>
                                </div>
                                <span class="wm-status-pill wm-status-<?php echo esc_attr($m_status); ?>">
                                    <?php echo esc_html($wm_status_label((string) ($mod['status'] ?? ''))); ?>
                                </span>
                            </div>
                            <p class="wm-module-desc">
                                <?php echo esc_html($mod['description']); ?>
                            </p>
                            <?php if (!empty($mod['dependencies'])) : ?>
                                <div class="wm-module-deps">
                                    <span class="wm-dep-label"><?php esc_html_e('Abhängigkeiten:', 'wm-suite-hub'); ?></span>
                                    <?php foreach ($mod['dependencies'] as $dep) : ?>
                                        <span class="wm-dep-tag"><?php echo esc_html($dep); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="wm-module-footer">
                                <?php if (!empty($mod['settings_url'])) : ?>
                                    <a href="<?php echo esc_url($mod['settings_url']); ?>" class="wm-btn wm-btn-outline" target="_blank" rel="noopener noreferrer">
                                        <span><?php esc_html_e('Konfiguration', 'wm-suite-hub'); ?></span>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="wm-btn wm-btn-outline wm-btn-action" data-slug="<?php echo esc_attr($slug); ?>" data-action="ping">
                                    <?php esc_html_e('Selbsttest', 'wm-suite-hub'); ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Cryptographic Audit Log Section -->
        <section id="wm-sec-audit" class="wm-section" aria-labelledby="wm-section-audit-title">
            <div class="wm-section-header">
                <h2 id="wm-section-audit-title" class="wm-section-title">
                    <?php esc_html_e('Audit-Protokoll', 'wm-suite-hub'); ?>
                </h2>
                <div class="wm-audit-verification-status" id="wm-audit-verification-banner">
                    <span class="dashicons dashicons-shield-alt"></span>
                    <span id="wm-audit-status-text"><?php esc_html_e('Kettenintegrität: Nicht geprüft (Klicken Sie auf Audit-Integrität prüfen)', 'wm-suite-hub'); ?></span>
                </div>
            </div>
            <div class="wm-audit-table-container">
                <table class="wm-table wm-audit-table" aria-label="<?php esc_attr_e('Audit-Log-Tabelle', 'wm-suite-hub'); ?>">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Zeitstempel', 'wm-suite-hub'); ?></th>
                            <th scope="col"><?php esc_html_e('Modul', 'wm-suite-hub'); ?></th>
                            <th scope="col"><?php esc_html_e('Ereignistyp', 'wm-suite-hub'); ?></th>
                            <th scope="col"><?php esc_html_e('Benutzer', 'wm-suite-hub'); ?></th>
                            <th scope="col"><?php esc_html_e('SHA-256 HMAC Signatur', 'wm-suite-hub'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wm-audit-table-body">
                        <tr>
                            <td colspan="5" class="wm-table-loading">
                                <?php esc_html_e('Lade Audit-Protokolle...', 'wm-suite-hub'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Theme & Appearance Management Section -->
        <section id="wm-sec-theme" class="wm-section" aria-labelledby="wm-section-theme-title">
            <div class="wm-section-header">
                <h2 id="wm-section-theme-title" class="wm-section-title">
                    <?php esc_html_e('Design-System & Theme-Präferenzen', 'wm-suite-hub'); ?>
                </h2>
            </div>
            <p class="wm-module-desc">
                <?php esc_html_e('Wählen Sie das globale Farbschema für das Cockpit und alle WM Suite Satelliten-Plugins. Standardmäßig wird das Enterprise-Design von CyberEvents verwendet, das sich nahtlos in die native WordPress-Admin-Oberfläche einfügt.', 'wm-suite-hub'); ?>
            </p>
            <div class="wm-modules-grid">
                <div class="wm-module-card">
                    <div class="wm-module-header">
                        <h3 class="wm-module-name"><?php echo wp_kses(wm_suite_hub_icon('shield'), $wm_icon_html); ?> CyberEvents Enterprise (Standard)</h3>
                        <span class="wm-status-pill wm-status-healthy"><?php esc_html_e('Standard', 'wm-suite-hub'); ?></span>
                    </div>
                    <p class="wm-module-desc">
                        <?php esc_html_e('Obsidian Midnight Slate Command Banner kombiniert mit nativen WordPress Admin Oberflächenkarten und klaren Kontrasten.', 'wm-suite-hub'); ?>
                    </p>
                    <button type="button" class="wm-btn wm-btn-primary wm-theme-btn" data-theme="cyber-events">
                        <?php esc_html_e('Als Standard aktivieren', 'wm-suite-hub'); ?>
                    </button>
                </div>
                <div class="wm-module-card">
                    <div class="wm-module-header">
                        <h3 class="wm-module-name"><?php echo wp_kses(wm_suite_hub_icon('sliders'), $wm_icon_html); ?> WordPress Native Classic</h3>
                        <span class="wm-status-pill wm-status-needs_config"><?php esc_html_e('Klassisch', 'wm-suite-hub'); ?></span>
                    </div>
                    <p class="wm-module-desc">
                        <?php esc_html_e('100% native WordPress Admin Farbpalette (#2271b1 Blue, #f0f0f1 Canvas, standardisierte WP Formulare).', 'wm-suite-hub'); ?>
                    </p>
                    <button type="button" class="wm-btn wm-btn-secondary wm-theme-btn" data-theme="wp-native">
                        <?php esc_html_e('Aktivieren', 'wm-suite-hub'); ?>
                    </button>
                </div>
                <div class="wm-module-card">
                    <div class="wm-module-header">
                        <h3 class="wm-module-name"><?php echo wp_kses(wm_suite_hub_icon('moon'), $wm_icon_html); ?> Obsidian Midnight Dark</h3>
                        <span class="wm-status-pill wm-status-needs_config"><?php esc_html_e('Dark Mode', 'wm-suite-hub'); ?></span>
                    </div>
                    <p class="wm-module-desc">
                        <?php esc_html_e('Vollständiger Dunkelmodus (#050811 Canvas) mit Glassmorphism-Karten und blauen Akzenten.', 'wm-suite-hub'); ?>
                    </p>
                    <button type="button" class="wm-btn wm-btn-secondary wm-theme-btn" data-theme="obsidian-dark">
                        <?php esc_html_e('Aktivieren', 'wm-suite-hub'); ?>
                    </button>
                </div>
                <div class="wm-module-card">
                    <div class="wm-module-header">
                        <h3 class="wm-module-name"><?php echo wp_kses(wm_suite_hub_icon('eye'), $wm_icon_html); ?> High-Contrast</h3>
                    </div>
                    <p class="wm-module-desc">
                        <?php esc_html_e('Reines Schwarz, Weiß und Sicherheitsgelb für hohen Kontrast.', 'wm-suite-hub'); ?>
                    </p>
                    <button type="button" class="wm-btn wm-btn-secondary wm-theme-btn" data-theme="high-contrast">
                        <?php esc_html_e('Aktivieren', 'wm-suite-hub'); ?>
                    </button>
                </div>
            </div>
        </section>

        <!-- Data Lifecycle & Opt-in Uninstall Section -->
        <section id="wm-sec-lifecycle" class="wm-section" aria-labelledby="wm-section-lifecycle-title">
            <div class="wm-section-header">
                <h2 id="wm-section-lifecycle-title" class="wm-section-title">
                    <?php esc_html_e('Datenaufbewahrung & Deinstallation', 'wm-suite-hub'); ?>
                </h2>
                <span class="wm-section-badge"><?php esc_html_e('Aufbewahrungsschutz', 'wm-suite-hub'); ?></span>
            </div>
            <div class="wm-module-card">
                <label for="wm-toggle-delete-on-uninstall" style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                    <input
                        type="checkbox"
                        id="wm-toggle-delete-on-uninstall"
                        name="wm_suite_hub_delete_data_on_uninstall"
                        value="1"
                        <?php checked((bool) get_option('wm_suite_hub_delete_data_on_uninstall', false)); ?>
                    />
                    <span>
                        <strong style="display: block; margin-bottom: 4px;">
                            <?php esc_html_e('Alle Daten beim Löschen des Plugins unwiderruflich entfernen', 'wm-suite-hub'); ?>
                        </strong>
                        <span class="wm-module-desc" style="margin: 0;">
                            <?php esc_html_e('Standardmäßig bewahrt WM Suite Hub die HMAC-SHA256-Audit-Tabelle, Rollen und Einstellungen bei einer Deinstallation auf. Aktivieren Sie diese Option nur, wenn alle Tabellen und Optionen beim Entfernen des Plugins endgültig gelöscht werden sollen.', 'wm-suite-hub'); ?>
                        </span>
                    </span>
                </label>
            </div>
        </section>
    </main>

    <!-- Modal container -->
    <div id="wm-cockpit-modal" class="wm-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="wm-modal-title" aria-hidden="true">
        <div class="wm-modal-container" role="document">
            <header class="wm-modal-header">
                <h3 id="wm-modal-title" class="wm-modal-title"><?php esc_html_e('Audit-Verifikation', 'wm-suite-hub'); ?></h3>
                <button type="button" class="wm-modal-close" id="wm-modal-close-btn" aria-label="<?php esc_attr_e('Schließen', 'wm-suite-hub'); ?>">
                    &times;
                </button>
            </header>
            <div class="wm-modal-body" id="wm-modal-body-content">
                <!-- Dynamic Content Injected by JS -->
            </div>
            <footer class="wm-modal-footer">
                <button type="button" class="wm-btn wm-btn-secondary" id="wm-modal-dismiss-btn">
                    <?php esc_html_e('Schließen', 'wm-suite-hub'); ?>
                </button>
            </footer>
        </div>
    </div>
</div>
