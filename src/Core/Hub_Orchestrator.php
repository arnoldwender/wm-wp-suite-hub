<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\API\API_Controller;
use ArnoldWender\SuiteHub\Admin\Admin_Menu;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Cron\SLA_Monitor;
use ArnoldWender\SuiteHub\CLI\WM_Hub_CLI;
use ArnoldWender\SuiteHub\CLI\WM_SDK_CLI;
use WP_CLI;

/**
 * Class Hub_Orchestrator
 *
 * Central engine and lifecycle manager for the WM Enterprise Cockpit.
 * Coordinates module discovery, REST API routing, Admin UI assets, audit logging,
 * and background SLA monitoring.
 *
 * @package ArnoldWender\SuiteHub\Core
 * @since   1.0.0
 */
final class Hub_Orchestrator {

    /**
     * Singleton instance of Hub_Orchestrator.
     *
     * @var Hub_Orchestrator|null
     */
    private static ?Hub_Orchestrator $instance = null;

    /**
     * Immutable module registry instance.
     *
     * @var Module_Registry|null
     */
    private ?Module_Registry $registry = null;

    /**
     * Audit Chainer instance for cryptographic event sealing.
     *
     * @var Audit_Chainer|null
     */
    private ?Audit_Chainer $audit_chainer = null;

    /**
     * Retrieves the singleton instance.
     *
     * @return Hub_Orchestrator
     */
    public static function get_instance(): Hub_Orchestrator {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor enforcing singleton pattern.
     */
    private function __construct() {
        // Initialization handled via init() method.
    }

    /**
     * Bootstraps the Hub components on WordPress lifecycle hooks.
     *
     * @return void
     */
    public function init(): void {
        // Ensure schema, roles, and options stay current on active installs without activation hook.
        add_action('plugins_loaded', [\ArnoldWender\SuiteHub\Setup\Activator::class, 'maybe_upgrade'], 10);

        // Handshake & Discovery executed late in plugins_loaded
        add_action('plugins_loaded', [$this, 'discover_modules'], 99);

        // REST API registration
        add_action('rest_api_init', [$this, 'register_rest_api']);

        // Admin Dashboard registration
        if (is_admin()) {
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'register_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_dashboard_setup', function(): void {
                if ($this->registry !== null && $this->audit_chainer !== null) {
                    (new \ArnoldWender\SuiteHub\Admin\Dashboard_Widgets($this->registry, $this->audit_chainer))->register_widgets();
                }
            });
        }

        // WP-CLI Commands Registration
        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }

        // Asset deduplication: drops the second copy of a stylesheet or script several spokes enqueue
        \ArnoldWender\SuiteHub\SharedKernel\Deduplication\Asset_Deduplicator::get_instance()->register();

        // Enterprise Borlabs Cookie Bridge (v2.x & v3.x Enterprise)
        \ArnoldWender\SuiteHub\Bridges\Borlabs_Cookie_Bridge::get_instance($this->audit_chainer)->init();

        // Enterprise Gutenberg Block Ecosystem (Block API v3 & Zero-Duplication SSR)
        \ArnoldWender\SuiteHub\Gutenberg\Block_Manager::get_instance()->init();

        // Third-Party Developer SDK & Compliance Hooks Bridge
        \ArnoldWender\SuiteHub\Compliance\Developer_SDK_Bridge::get_instance();

        // Load Internationalization Textdomain
        add_action('init', function(): void {
            load_plugin_textdomain(
                'wm-suite-hub',
                false,
                dirname(plugin_basename(WM_SUITE_HUB_FILE)) . '/languages'
            );
        });

        // Background SLA & Cron Monitors
        add_action('init', [$this, 'init_background_services']);
    }

    /**
     * Triggers the discovery filter and creates the immutable Module_Registry.
     *
     * @return void
     */
    public function discover_modules(): void {
        if ($this->registry === null) {
            $this->registry = new Module_Registry();
            $this->audit_chainer = new Audit_Chainer();
            
            // Register Enterprise Admin Dashboard Widgets
            if (is_admin()) {
                (new \ArnoldWender\SuiteHub\Admin\Dashboard_Widgets($this->registry, $this->audit_chainer))->register();
            }

            /**
             * Fires after the WM Suite Hub has finalized module discovery.
             *
             * @param Module_Registry $registry The immutable module registry.
             */
            do_action('wm_suite_hub_discovered', $this->registry);
        }
    }

    /**
     * Registers REST API endpoints for the Cockpit and telemetry services.
     *
     * @return void
     */
    public function register_rest_api(): void {
        if ($this->registry === null) {
            $this->discover_modules();
        }

        $controller = new API_Controller($this->registry, $this->audit_chainer);
        $controller->register_routes();
    }

    /**
     * Registers the WM Suite Admin Menu Cockpit.
     *
     * @return void
     */
    public function register_admin_menu(): void {
        if ($this->registry === null) {
            $this->discover_modules();
        }

        $admin_menu = new Admin_Menu($this->registry, $this->audit_chainer);
        $admin_menu->register();
    }

    /**
     * Enqueues Zero-CDN Obsidian Glassmorphism assets for the Cockpit page.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Spoke dashboards dispatched from the "WM Suite" menu keep the hook
        // "toplevel_page_<slug>" when the spoke registered that slug itself (remove_menu_page
        // leaves $admin_page_hooks alone); a page slug only the hub registers gets
        // "wm-suite_page_<slug>" (measured 2026-09-14 by parsing every spoke's add_menu_page:
        // 13 of 17 hub page slugs equal the spoke's own; wm-activity-sentinel, wm-prototype-nda-gate,
        // wm-dsgvo-newsletter-suite and wm-cyber-events-core register a different slug).
        // Neither prefix is a reliable signal, so a
        // spoke page is recognised by its slug (matching the prefix alone dropped every
        // stylesheet of wm-a11y-sensory-governor under the hub once the deduplicator removed
        // its legacy copy).
        $page          = sanitize_key((string) ($_GET['page'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing of an admin screen, nothing is changed
        $is_hub_page   = str_contains($hook_suffix, 'wm-suite-hub');
        $is_spoke_page = str_starts_with($hook_suffix, 'wm-suite_page_')
            || in_array($page, Admin_Menu::spoke_page_slugs(), true);
        if (!$is_hub_page && !$is_spoke_page) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__DIR__));
        $version    = defined('WM_SUITE_HUB_VERSION') ? WM_SUITE_HUB_VERSION : '1.2.6';

        // Family sheets (canonical copies): every WM plugin registers the same two handles, so
        // WordPress keeps one copy however many plugins are active. Tokens first, components second.
        // Versioned by file time, not by the plugin version: a sheet updated under an unchanged
        // plugin version was served from the browser cache (measured 2026-09-14).
        $plugin_dir = plugin_dir_path(dirname(__DIR__));
        $tokens_ver = (string) (@filemtime($plugin_dir . 'assets/css/wm-admin-tokens.css') ?: $version);
        $ui_ver     = (string) (@filemtime($plugin_dir . 'assets/css/wm-admin-ui.css') ?: $version);
        wp_enqueue_style('wm-admin-tokens', $plugin_url . 'assets/css/wm-admin-tokens.css', [], $tokens_ver);
        wp_enqueue_style('wm-admin-ui', $plugin_url . 'assets/css/wm-admin-ui.css', ['wm-admin-tokens'], $ui_ver);

        if (!$is_hub_page) {
            return;
        }

        // CSS: Obsidian Glassmorphism cockpit layer (hub pages only), on top of the family sheets
        wp_enqueue_style(
            'wm-obsidian-glass',
            $plugin_url . 'assets/css/wm-obsidian-glass.css',
            ['wm-admin-ui'],
            $version
        );

        // JS: Vanilla ES6+ Cockpit SPA client
        wp_enqueue_script(
            'wm-cockpit-vanilla',
            $plugin_url . 'assets/js/wm-cockpit-vanilla.js',
            [],
            $version,
            true
        );

        // Pass localized security variables (Nonces, REST base, Capabilities, Active Theme)
        wp_localize_script('wm-cockpit-vanilla', 'wmHubSettings', [
            'restUrl'     => esc_url_raw(rest_url('wm-hub/v1/')),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'activeTheme' => function_exists('wm_get_suite_theme') ? wm_get_suite_theme() : 'cyber-events',
            'version'     => $version,
            'i18n'        => self::cockpit_script_strings(),
        ]);
    }

    /**
     * Every text the cockpit script shows, translated here and filled in by the script (%s, %d, %1$s).
     * Until 1.2.3 the script carried its own German strings, so an admin in any of the 25 bundled locales
     * got German toasts and dialogs; five strings were passed and none of them was read.
     *
     * @return array<string, string>
     */
    public static function cockpit_script_strings(): array {
        return [
            'errorOccurred'     => __('Ein Fehler bei der Kommunikation mit dem Hub ist aufgetreten.', 'wm-suite-hub'),
            /* translators: %s: HTTP status code */
            'httpError'         => __('HTTP-Fehler %s', 'wm-suite-hub'),
            'themeUpdated'      => __('Theme erfolgreich gewechselt.', 'wm-suite-hub'),
            /* translators: %s: error message */
            'themeSaveFailed'   => __('Fehler beim Speichern des Themes: %s', 'wm-suite-hub'),
            'noAuditEvents'     => __('Keine Audit-Ereignisse protokolliert.', 'wm-suite-hub'),
            /* translators: %s: WordPress user id */
            'userNumber'        => __('Benutzer #%s', 'wm-suite-hub'),
            /* translators: %s: error message */
            'logsLoadFailed'    => __('Fehler beim Laden der Logs: %s', 'wm-suite-hub'),
            'chainChecking'     => __('Die Audit-Kette wird geprüft …', 'wm-suite-hub'),
            /* translators: %d: number of audit entries checked */
            'chainIntactStatus' => __('Audit-Kette intakt: %d Einträge geprüft', 'wm-suite-hub'),
            'chainIntactTitle'  => __('Audit-Kette intakt', 'wm-suite-hub'),
            /* translators: %d: number of audit entries checked */
            'chainIntactBody'   => __('Alle %d Einträge passen zu ihrer HMAC-SHA256-Signatur und zu ihrem Vorgänger: Keiner wurde ohne den Schlüssel der Website geändert, gelöscht oder umsortiert.', 'wm-suite-hub'),
            /* translators: %s: hexadecimal digest of the latest audit entry */
            'latestHash'        => __('Letzter Hash: %s', 'wm-suite-hub'),
            /* translators: %d: id of the first audit entry that does not verify */
            'chainBrokenStatus' => __('Audit-Kette unterbrochen bei Eintrag #%d', 'wm-suite-hub'),
            'chainBrokenTitle'  => __('Audit-Kette unterbrochen', 'wm-suite-hub'),
            'chainCheckTitle'   => __('Prüfung der Audit-Kette', 'wm-suite-hub'),
            'chainCheckFailed'  => __('Prüfung fehlgeschlagen', 'wm-suite-hub'),
            'refreshSuccess'    => __('Telemetriedaten erfolgreich aktualisiert.', 'wm-suite-hub'),
            /* translators: %s: error message */
            'refreshFailed'     => __('Fehler beim Aktualisieren: %s', 'wm-suite-hub'),
            /* translators: %s: name of the solution pack */
            'preflightTitle'    => __('Pre-Flight: %s', 'wm-suite-hub'),
            'preflightFailed'   => __('Fehler bei der Pre-Flight-Prüfung:', 'wm-suite-hub'),
            'colCheck'          => __('Prüfpunkt', 'wm-suite-hub'),
            'colRequired'       => __('Soll', 'wm-suite-hub'),
            'colActual'         => __('Ist', 'wm-suite-hub'),
            'colStatus'         => __('Status', 'wm-suite-hub'),
            'checkMet'          => __('Erfüllt', 'wm-suite-hub'),
            'checkNotMet'       => __('Nicht erfüllt', 'wm-suite-hub'),
            'orderComputed'     => __('Berechnete Installationsreihenfolge:', 'wm-suite-hub'),
            'orderFree'         => __('Keine Abhängigkeiten deklariert, Reihenfolge frei:', 'wm-suite-hub'),
            /* translators: %s: id of the solution pack */
            'dryRunStarted'     => __('Dry-Run für %s läuft …', 'wm-suite-hub'),
            'dryRunDone'        => __('Dry-Run abgeschlossen.', 'wm-suite-hub'),
            /* translators: %s: error message */
            'dryRunFailed'      => __('Dry-Run fehlgeschlagen: %s', 'wm-suite-hub'),
            'lifecycleSaved'    => __('Deinstallations-Einstellung gespeichert.', 'wm-suite-hub'),
            /* translators: %s: error message */
            'saveFailed'        => __('Fehler beim Speichern: %s', 'wm-suite-hub'),
            /* translators: 1: module slug, 2: message the module returned */
            'actionResult'      => __('Ergebnis für %1$s: %2$s', 'wm-suite-hub'),
            'actionSuccess'     => __('Aktion erfolgreich ausgeführt.', 'wm-suite-hub'),
            /* translators: %s: error message */
            'actionFailed'      => __('Fehler bei der Modulaktion: %s', 'wm-suite-hub'),
        ];
    }

    /**
     * Registers WordPress admin settings for data lifecycle preferences.
     *
     * @return void
     */
    public function register_settings(): void {
        if (function_exists('register_setting')) {
            register_setting('wm_suite_hub_settings', 'wm_suite_hub_delete_data_on_uninstall', [
                'type'              => 'boolean',
                'sanitize_callback' => static fn($val): bool => (bool) $val,
                'default'           => false,
            ]);
        }
    }

    /**
     * Registers native WP-CLI commands.
     *
     * @return void
     */
    private function register_cli_commands(): void {
        if (class_exists(WM_Hub_CLI::class)) {
            WM_Hub_CLI::register($this);
        }
        if (class_exists(WM_SDK_CLI::class)) {
            WM_SDK_CLI::register();
        }
    }

    /**
     * Initializes background services, Cron monitors and security headers.
     *
     * @return void
     */
    public function init_background_services(): void {
        SLA_Monitor::register_schedules();
    }

    /**
     * Returns the active Module_Registry instance.
     *
     * @return Module_Registry|null
     */
    public function get_registry(): ?Module_Registry {
        if ($this->registry === null) {
            $this->discover_modules();
        }
        return $this->registry;
    }

    /**
     * Returns the active Audit_Chainer instance.
     *
     * @return Audit_Chainer|null
     */
    public function get_audit_chainer(): ?Audit_Chainer {
        if ($this->audit_chainer === null) {
            $this->audit_chainer = new Audit_Chainer();
        }
        return $this->audit_chainer;
    }
}
