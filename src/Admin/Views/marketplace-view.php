<?php
/**
 * Solution Packs Admin View Template (included by the cockpit view)
 *
 * Lists the solution packs of the bundled manifest: each pack's plugins with their active state,
 * the install order Kahn's sort resolves, a pre-flight check of the server and a dry run. Nothing
 * here installs anything; until 1.2.2 it offered two install buttons whose request answered 501,
 * printed a TTFB figure no one measured and an AAA seal per pack. Its ids are prefixed
 * wm-preflight-*: the cockpit's own dialog has the ids wm-modal-*, and a second copy of those ids sent
 * the audit verification into this hidden dialog. The classes are shared on purpose (same overlay).
 *
 * @package ArnoldWender\SuiteHub\Admin\Views
 * @since   1.2.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$manifest_bundles = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();
$deduplicator     = \ArnoldWender\SuiteHub\SharedKernel\Deduplication\Asset_Deduplicator::get_instance();
$telemetry        = $deduplicator->get_telemetry();
$hub_registry     = \ArnoldWender\SuiteHub\Core\Hub_Orchestrator::get_instance()->get_registry();
$active_spokes    = $hub_registry->get_all_modules();
// Decorative SVG icons (wm_suite_hub_icon), escaped at every echo with the helper's own allowlist.
$wm_icon_html     = wm_suite_hub_icon_allowed_html();
?>

<section id="wm-sec-marketplace" class="wm-section-wrap" aria-labelledby="wm-sec-marketplace-title">

    <!-- Section header -->
    <div class="wm-section-header">
        <div>
            <h2 id="wm-sec-marketplace-title">
                <span class="dashicons dashicons-portfolio"></span>
                <span><?php esc_html_e('Solution Packs', 'wm-suite-hub'); ?></span>
            </h2>
            <p class="wm-section-desc">
                <?php esc_html_e('Thematische Pakete aus WM-Suite-Plugins: Die Reihenfolge ihrer Abhängigkeiten löst Kahns topologische Sortierung auf, und der Pre-Flight Check prüft vorab den Server. Eine Installation gibt es in dieser Version nicht; die Plugins werden einzeln installiert.', 'wm-suite-hub'); ?>
            </p>
        </div>

        <!-- Deduplication counters of this page load (Asset_Deduplicator) -->
        <div class="wm-telemetry-badge-card" id="wm-telemetry-badge">
            <span class="dashicons dashicons-performance"></span>
            <div>
                <strong><?php esc_html_e('Deduplizierung auf dieser Seite:', 'wm-suite-hub'); ?></strong>
                <span>
                    <?php
                    /* translators: 1: dropped duplicate scripts, 2: dropped duplicate stylesheets, 3: size of the dropped files in KB */
                    $dedup_text = __('%1$d Skripte und %2$d Stylesheets entfernt (%3$s KB)', 'wm-suite-hub');
                    echo esc_html(sprintf($dedup_text, (int) $telemetry['scripts_deduplicated'], (int) $telemetry['styles_deduplicated'], number_format_i18n($telemetry['bytes_saved'] / 1024, 1)));
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Bundles Grid -->
    <div class="wm-bundles-grid">
        <?php foreach ($manifest_bundles as $bundle_id => $bundle): ?>
            <?php 
                $packages  = $bundle->get_packages();
                $pkg_count = count($packages);
                $tags      = $bundle->get_compliance_tags();
                $graph     = $bundle->get_dependency_graph();
                $order     = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($graph);

                // Count how many modules in this bundle are currently active
                $active_in_bundle = 0;
                foreach ($packages as $p) {
                    if (isset($active_spokes[$p['slug']])) {
                        $active_in_bundle++;
                    }
                }
            ?>
            <article class="wm-bundle-card" data-bundle-id="<?php echo esc_attr($bundle_id); ?>">
                
                <!-- Bundle Header & Badges -->
                <div class="wm-bundle-card-header">
                    <div class="wm-bundle-badge-row">
                        <?php foreach ($tags as $tag): ?>
                            <span class="wm-compliance-pill wm-tag-<?php echo esc_attr(strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $tag))); ?>">
                                <?php echo esc_html($tag); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="wm-bundle-title-row">
                        <h3 class="wm-bundle-title"><?php echo esc_html($bundle->get_suite_name()); ?></h3>
                        <span class="wm-bundle-status-badge <?php echo esc_attr( $active_in_bundle === $pkg_count ? 'wm-status-full' : ( $active_in_bundle > 0 ? 'wm-status-partial' : 'wm-status-available' ) ); ?>">
                            <?php
                            if ($active_in_bundle === $pkg_count) {
                                /* translators: 1: active modules, 2: modules in the pack */
                                $status_text = __('Vollständig aktiv (%1$d/%2$d)', 'wm-suite-hub');
                                echo wp_kses(wm_suite_hub_icon('check'), $wm_icon_html) . ' ' . esc_html(sprintf($status_text, (int) $active_in_bundle, (int) $pkg_count));
                            } elseif ($active_in_bundle > 0) {
                                /* translators: 1: active modules, 2: modules in the pack */
                                $status_text = __('Teilweise aktiv (%1$d/%2$d)', 'wm-suite-hub');
                                echo esc_html(sprintf($status_text, (int) $active_in_bundle, (int) $pkg_count));
                            } else {
                                /* translators: %d: modules in the pack */
                                $status_text = __('%d Module verfügbar', 'wm-suite-hub');
                                echo esc_html(sprintf($status_text, (int) $pkg_count));
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Pack description -->
                <p class="wm-bundle-desc"><?php echo esc_html($bundle->get_description()); ?></p>

                <!-- Plugins of the pack -->
                <div class="wm-bundle-packages-summary">
                    <div class="wm-packages-header">
                        <div>
                            <strong>
                                <?php
                                /* translators: %d: modules in the pack */
                                $contents_text = __('Paketinhalt & Modul-Architektur (%d):', 'wm-suite-hub');
                                echo esc_html(sprintf($contents_text, (int) $pkg_count));
                                ?>
                            </strong>
                        </div>
                        <span class="wm-php-req">PHP <?php echo esc_html($bundle->get_required_php_version()); ?>+</span>
                    </div>

                    <div class="wm-modules-breakdown-list">
                        <?php foreach ($packages as $pkg): ?>
                            <?php 
                                $is_active = isset($active_spokes[$pkg['slug']]);
                            ?>
                            <div class="wm-module-item-card <?php echo esc_attr( $is_active ? 'is-active-module' : 'is-pending-module' ); ?>">
                                <div class="wm-module-item-header">
                                    <div class="wm-module-item-title-wrap">
                                        <span class="dashicons dashicons-admin-plugins wm-module-icon"></span>
                                        <div>
                                            <strong class="wm-module-item-name"><?php echo esc_html($pkg['name']); ?></strong>
                                        </div>
                                    </div>
                                    <div class="wm-module-item-meta">
                                        <span class="wm-module-status-pill <?php echo esc_attr( $is_active ? 'pill-active' : 'pill-available' ); ?>">
                                            <?php echo wp_kses(wm_suite_hub_icon($is_active ? 'check' : 'circle'), $wm_icon_html) . ' ' . ($is_active ? esc_html__('Aktiviert', 'wm-suite-hub') : esc_html__('Im Paket enthalten', 'wm-suite-hub')); ?>
                                        </span>
                                        <code class="wm-pkg-version"><?php echo esc_html($pkg['version_constraint']); ?></code>
                                    </div>
                                </div>

                                <?php
                                // The plugin's own description, once it is active and registered; an
                                // inactive plugin shows none rather than copy written for it here.
                                $live_desc = '';
                                if ($is_active) {
                                    try {
                                        $live_desc = (string) $active_spokes[$pkg['slug']]->get_description();
                                    } catch (\Throwable $e) {
                                        $live_desc = '';
                                    }
                                }
                                ?>
                                <?php if ($live_desc !== ''): ?>
                                    <p class="wm-module-item-desc"><?php echo esc_html($live_desc); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Install order: Kahn's sort when a package requires another, otherwise a plain statement -->
                <div class="wm-dag-preview">
                    <?php if ($bundle->has_dependencies()): ?>
                        <div class="wm-dag-header">
                            <span class="wm-dag-label"><?php echo wp_kses(wm_suite_hub_icon('chain'), $wm_icon_html); ?> <?php esc_html_e('Mathematische DAG-Installationsfolge (Kahn-Sortierung):', 'wm-suite-hub'); ?></span>
                        </div>
                        <code class="wm-dag-sequence"><?php echo esc_html(implode(' → ', $order)); ?></code>
                    <?php else: ?>
                        <div class="wm-dag-header">
                            <span class="wm-dag-label"><?php echo wp_kses(wm_suite_hub_icon('chain'), $wm_icon_html); ?> <?php esc_html_e('Keines dieser Plugins setzt ein anderes voraus; die Reihenfolge der Installation ist frei.', 'wm-suite-hub'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <?php
                /* translators: %s: suite name */
                $preflight_label = sprintf(__('Pre-Flight Check für %s starten', 'wm-suite-hub'), $bundle->get_suite_name());
                ?>
                <div class="wm-bundle-card-actions">
                    <button type="button" class="wm-btn wm-btn-secondary wm-btn-preflight" data-bundle-id="<?php echo esc_attr($bundle_id); ?>" aria-label="<?php echo esc_attr($preflight_label); ?>">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span><?php esc_html_e('Pre-Flight Check', 'wm-suite-hub'); ?></span>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php
// Pre-flight dialog: the same overlay and container as the cockpit's own dialog (focus moves in, Tab stays
// inside, Escape closes, focus returns). Until 1.2.3 it carried two classes no sheet styles, so the "dialog"
// opened as a bare block at the end of the page.
?>
<div id="wm-preflight-modal" class="wm-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="wm-preflight-title" aria-hidden="true">
    <div class="wm-modal-container" role="document">
        <header class="wm-modal-header">
            <h3 id="wm-preflight-title" class="wm-modal-title">
                <span id="wm-modal-suite-name"><?php esc_html_e('Pre-Flight Check', 'wm-suite-hub'); ?></span>
            </h3>
            <button type="button" id="wm-btn-modal-close" class="wm-modal-close" aria-label="<?php esc_attr_e('Schließen', 'wm-suite-hub'); ?>">
                &times;
            </button>
        </header>

        <div class="wm-modal-body" id="wm-preflight-body">
            <div class="wm-preflight-loader">
                <span class="dashicons dashicons-update wm-spin" aria-hidden="true"></span>
                <p><?php esc_html_e('Prüfe PHP-Version, Sodium-Erweiterung, Speicherlimit, Schreibrechte und OPcache …', 'wm-suite-hub'); ?></p>
            </div>

            <div id="wm-preflight-results" hidden>
                <!-- Environmental checks table rendered via JS -->
            </div>
        </div>

        <footer class="wm-modal-footer">
            <button type="button" id="wm-btn-modal-dryrun" class="wm-btn wm-btn-secondary">
                <?php echo wp_kses(wm_suite_hub_icon('flask'), $wm_icon_html); ?> <?php esc_html_e('Dry-Run Simulation', 'wm-suite-hub'); ?>
            </button>
        </footer>
    </div>
</div>
