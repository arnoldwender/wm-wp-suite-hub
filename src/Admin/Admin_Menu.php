<?php
/**
 * Class Admin_Menu
 *
 * Registers the unified top-level WM Enterprise Cockpit in the WordPress Admin sidebar
 * and dynamically consolidates all active WM Suite function plugins into categorized submenus,
 * keeping the root admin menu clean, structured, and enterprise-grade.
 *
 * @package ArnoldWender\SuiteHub\Admin
 * @since   1.2.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The menu labels and the cockpit views draw their icons with wm_suite_hub_icon(). Block_Manager loads
// the helper on every request already; requiring it here keeps the menu independent of that order.
require_once dirname( __DIR__ ) . '/Gutenberg/icons.php';

use ArnoldWender\SuiteHub\Core\Module_Registry;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;

final class Admin_Menu {

    /**
     * Module Registry instance.
     *
     * @var Module_Registry
     */
    private Module_Registry $registry;

    /**
     * Audit Chainer instance.
     *
     * @var Audit_Chainer
     */
    private Audit_Chainer $audit_chainer;

    /**
     * The WM Suite entry to mark on a spoke sub-page this request, set by adopt_spoke_subpages().
     *
     * @var string|null
     */
    private ?string $adopted_submenu_file = null;

    /**
     * Enterprise Module Categorization Matrix.
     *
     * `icon` names a shape of wm_suite_hub_icon(); the label stays plain text. Until 1.2.1 every header and
     * label started with an emoji, which a screen reader read out before each menu entry.
     */
    private const CATEGORIES = [
        'security' => [
            'header'  => 'SICHERHEIT & LEGAL',
            'icon'    => 'shield',
            'modules' => [
                'wm-activity-sentinel' => [
                    'title'      => 'Activity Sentinel',
                    'menu_title' => 'Activity Sentinel',
                    'icon'       => 'shield',
                    'page'       => 'wm-activity-sentinel',
                ],
                'wm-hinschg-whistleblower' => [
                    'title'      => 'HinSchG Whistleblower',
                    'menu_title' => 'HinSchG Whistleblower',
                    'icon'       => 'megaphone',
                    'page'       => 'wm-hinschg-desk',
                ],
                'wm-prototype-nda-gate' => [
                    'title'      => 'Prototype NDA Gate',
                    'menu_title' => 'Prototype NDA Gate',
                    'icon'       => 'lock',
                    'page'       => 'wm-prototype-nda-gate',
                ],
                'wm-kanzlei-ai-sanitizer' => [
                    'title'      => 'Kanzlei AI Sanitizer',
                    'menu_title' => 'Kanzlei AI Sanitizer',
                    'icon'       => 'scale',
                    'page'       => 'wm-kanzlei-sanitizer',
                ],
                'wm-cra-sbom-beacon' => [
                    'title'      => 'CRA SBOM Beacon',
                    'menu_title' => 'CRA SBOM Beacon',
                    'icon'       => 'document',
                    'page'       => 'wm-cra-beacon',
                ],
            ],
        ],
        'a11y' => [
            'header'  => 'BARRIEREFREIHEIT (BFSG)',
            'icon'    => 'eye',
            'modules' => [
                'wm-a11y-sensory-governor' => [
                    'title'      => 'A11y Sensory Governor',
                    'menu_title' => 'Sensory Governor',
                    'icon'       => 'eye',
                    'page'       => 'wm-sensory-governor',
                ],
                'wm-bfsg-ast-remediator' => [
                    'title'      => 'BFSG AST Remediator',
                    'menu_title' => 'BFSG AST Remediator',
                    'icon'       => 'bolt',
                    'page'       => 'wm-bfsg-remediator',
                ],
                'wm-bfsg-compliance-dashboard' => [
                    'title'      => 'BFSG Dashboard',
                    'menu_title' => 'BFSG Dashboard',
                    'icon'       => 'clipboard',
                    'page'       => 'wm-bfsg-dashboard',
                ],
                'wm-zero-cdn-asset-localizer' => [
                    'title'      => 'Zero-CDN Asset Localizer',
                    'menu_title' => 'Zero-CDN Localizer',
                    'icon'       => 'globe',
                    'page'       => 'wm-zero-cdn',
                ],
                'wm-social-care-accessibility-hub' => [
                    'title'      => 'Social Care Accessibility Hub',
                    'menu_title' => 'Social Care A11y',
                    'icon'       => 'accessibility',
                    'page'       => 'wm-social-care-accessibility-hub',
                ],
            ],
        ],
        'privacy' => [
            'header'  => 'DATENSCHUTZ (DSGVO)',
            'icon'    => 'lock',
            'modules' => [
                'wm-sovereign-consent-vault' => [
                    'title'      => 'Sovereign Consent Vault',
                    'menu_title' => 'Consent Vault',
                    'icon'       => 'key',
                    'page'       => 'wm-consent-vault',
                ],
                'wm-dsgvo-privacy-eraser' => [
                    'title'      => 'DSGVO Privacy Eraser',
                    'menu_title' => 'Privacy Eraser (Art. 17)',
                    'icon'       => 'trash',
                    'page'       => 'wm-dsgvo-eraser',
                ],
                'wm-dsgvo-newsletter-suite' => [
                    'title'      => 'DSGVO Newsletter Suite',
                    'menu_title' => 'Newsletter Suite',
                    'icon'       => 'mail',
                    'page'       => 'wm-dsgvo-newsletter-suite',
                ],
                'wm-edu-privacy-portal' => [
                    'title'      => 'Edu Privacy Portal',
                    'menu_title' => 'Einwilligungen',
                    'icon'       => 'book',
                    'page'       => 'wm-edu-consents',
                ],
            ],
        ],
        'governance' => [
            'header'  => 'CORPORATE GOVERNANCE',
            'icon'    => 'landmark',
            'modules' => [
                'wm-dora-ict-register' => [
                    'title'      => 'DORA ICT Register',
                    'menu_title' => 'DORA ICT Register',
                    'icon'       => 'landmark',
                    'page'       => 'wm-dora-register',
                ],
                'wm-lksg-supply-auditor' => [
                    'title'      => 'LkSG Supply Auditor',
                    'menu_title' => 'LkSG Supply Auditor',
                    'icon'       => 'globe',
                    'page'       => 'wm-lksg-auditor',
                ],
            ],
        ],
        'commerce' => [
            'header'  => 'EVENTS & PUBLISHING',
            'icon'    => 'bolt',
            'modules' => [
                'wm-cyber-events-core' => [
                    'title'      => 'Cyber Events Core',
                    'menu_title' => 'Cyber Events Core',
                    'icon'       => 'ticket',
                    'page'       => 'wm-cyber-events-core',
                ],
                'wm-peppol-as4-gateway' => [
                    'title'      => 'Peppol AS4 Gateway',
                    'menu_title' => 'Peppol AS4 Gateway',
                    'icon'       => 'euro',
                    'page'       => 'wm-peppol-gateway',
                ],
                'wm-modularscale' => [
                    'title'      => 'Modular Scale Typografie',
                    'menu_title' => 'Modular Scale',
                    'icon'       => 'ruler',
                    'page'       => 'wm-modular-scale',
                ],
                'wm-newsticker' => [
                    'title'      => 'Newsticker Live',
                    'menu_title' => 'Newsticker (Live)',
                    'icon'       => 'megaphone',
                    'page'       => 'wm-newsticker',
                ],
                'wm-k9-bh-exam-planner' => [
                    'title'      => 'K9 BH Exam Planner',
                    'menu_title' => 'BH Exam Planner',
                    'icon'       => 'clipboard',
                    'page'       => 'wm-k9-planner',
                ],
            ],
        ],
    ];

    /**
     * A menu label: the decorative icon, then the escaped text.
     *
     * WordPress prints a submenu title as given, so the label may carry markup; the SVG comes from the
     * fixed icon map and the text is escaped here.
     *
     * @param string $icon  Shape name of wm_suite_hub_icon().
     * @param string $text  Plain label text.
     * @return string
     */
    public static function menu_label(string $icon, string $text): string {
        return wm_suite_hub_icon($icon) . ' ' . esc_html($text);
    }

    /**
     * Every admin page slug the hub dispatches to a spoke (the `page` values of CATEGORIES).
     * The asset enqueue matches the current page against this list: the hook suffix is not a
     * reliable signal, because a spoke that registered the same slug keeps "toplevel_page_<slug>"
     * after the hub removes its menu (measured 2026-09-14).
     *
     * @return array<int, string>
     */
    public static function spoke_page_slugs(): array {
        $slugs = [];
        foreach (self::CATEGORIES as $category) {
            foreach ($category['modules'] as $meta) {
                $slugs[] = $meta['page'];
            }
        }
        return $slugs;
    }

    /**
     * Admin_Menu constructor.
     *
     * @param Module_Registry $registry
     * @param Audit_Chainer   $audit_chainer
     */
    public function __construct(Module_Registry $registry, Audit_Chainer $audit_chainer) {
        $this->registry      = $registry;
        $this->audit_chainer = $audit_chainer;

        // Consolidate standalone spoke menus at late priority
        add_action('admin_menu', [$this, 'consolidate_standalone_spoke_menus'], 999);
        add_action('admin_head', [$this, 'inject_sidebar_category_styles']);
        add_filter('parent_file', [$this, 'adopt_spoke_subpages']);
        add_filter('submenu_file', [$this, 'mark_adopted_spoke_entry']);
    }

    /**
     * Registers the unified top-level WM Suite menu and categorized submenus.
     *
     * @return void
     */
    public function register(): void {
        $capability = 'manage_wm_suite';

        // 1. Top-Level Parent Menu: WM Suite
        add_menu_page(
            __('WM Enterprise Suite', 'wm-suite-hub'),
            __('WM Suite', 'wm-suite-hub'),
            $capability,
            'wm-suite-hub',
            [$this, 'render_cockpit'],
            'dashicons-shield-alt',
            3
        );

        // 2. Core Submenus: Hub Cockpit, Solution Packs, Audit. The last two open the cockpit at their section
        // (wm-cockpit-vanilla.js). Until 1.2.3 a "CRA & SBOM Hub" entry opened the same cockpit, which has no
        // SBOM view: the SBOM is written by `wp wm-hub sbom`.
        add_submenu_page(
            'wm-suite-hub',
            __('WM Suite — Cockpit & Status', 'wm-suite-hub'),
            self::menu_label('chart', __('Cockpit & Status', 'wm-suite-hub')),
            $capability,
            'wm-suite-hub',
            [$this, 'render_cockpit']
        );

        // Until 1.2.2 this entry was called a marketplace; nothing can be bought or installed from it.
        add_submenu_page(
            'wm-suite-hub',
            __('WM Suite — Solution Packs', 'wm-suite-hub'),
            self::menu_label('package', __('Solution Packs', 'wm-suite-hub')),
            $capability,
            'wm-suite-hub-marketplace',
            [$this, 'render_cockpit']
        );

        add_submenu_page(
            'wm-suite-hub',
            __('WM Suite — Audit-Protokoll', 'wm-suite-hub'),
            self::menu_label('chain', __('Audit-Log', 'wm-suite-hub')),
            $capability,
            'wm-suite-hub-audit',
            [$this, 'render_cockpit']
        );

        // 3. Dynamically Register Categorized Submenus for all Active Spokes
        $active_modules = $this->registry->get_all_modules();

        foreach (self::CATEGORIES as $cat_key => $category) {
            $matching_active = [];
            foreach ($category['modules'] as $spoke_slug => $meta) {
                if (isset($active_modules[$spoke_slug])) {
                    $matching_active[$spoke_slug] = $meta;
                }
            }

            if (empty($matching_active)) {
                continue;
            }

            // Register category section header
            add_submenu_page(
                'wm-suite-hub',
                $category['header'],
                sprintf('<span class="wm-menu-cat-header">%s</span>', self::menu_label($category['icon'], $category['header'])),
                $capability,
                'wm-suite-hub-cat-' . $cat_key,
                [$this, 'render_cockpit']
            );

            // Register each active spoke module subpage
            foreach ($matching_active as $spoke_slug => $meta) {
                $page_slug = $meta['page'];

                add_submenu_page(
                    'wm-suite-hub',
                    $meta['title'],
                    self::menu_label($meta['icon'], $meta['menu_title']),
                    'manage_options',
                    $page_slug,
                    [$this, 'render_spoke_dispatcher']
                );
            }
        }
    }

    /**
     * Universal Spoke Dispatcher — renders the active spoke dashboard or redirects safely.
     *
     * @return void
     */
    public function render_spoke_dispatcher(): void {
        $current_page = sanitize_key($_GET['page'] ?? '');

        // 1. Specific Spoke Handlers with full dependency injection
        switch ($current_page) {
            case 'wm-bfsg-compliance-dashboard':
            case 'wm-bfsg-dashboard':
                if (class_exists(\WMCyber\BFSG\Dashboard\Admin\AdminDashboard::class)) {
                    \WMCyber\BFSG\Dashboard\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-bfsg-ast-remediator':
            case 'wm-bfsg-remediator':
                if (class_exists(\WMCyber\BFSG\Remediator\Admin\AdminDashboard::class)) {
                    \WMCyber\BFSG\Remediator\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-sovereign-consent-vault':
            case 'wm-consent-vault':
                if (class_exists(\WMCyber\Consent\Vault\Admin\AdminDashboard::class)) {
                    \WMCyber\Consent\Vault\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-zero-cdn-asset-localizer':
            case 'wm-zero-cdn':
                if (class_exists(\WMCyber\ZeroCDN\Localizer\Admin\AdminDashboard::class)) {
                    \WMCyber\ZeroCDN\Localizer\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-dsgvo-privacy-eraser':
            case 'wm-dsgvo-eraser':
                if (class_exists(\WMCyber\DSGVO\Eraser\Admin\AdminDashboard::class)) {
                    \WMCyber\DSGVO\Eraser\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-a11y-sensory-governor':
            case 'wm-sensory-governor':
                if (class_exists(\WMCyber\A11y\Governor\Admin\AdminDashboard::class)) {
                    \WMCyber\A11y\Governor\Admin\AdminDashboard::instance()->render_dashboard();
                    return;
                }
                break;

            case 'wm-modularscale':
            case 'wm-modular-scale':
                if (function_exists('wmmsp_settings_page')) {
                    wmmsp_settings_page();
                    return;
                }
                break;

            case 'wm-dsgvo-newsletter-suite':
            case 'wm-newsletter-suite':
                if (class_exists(\WMCyber\Newsletter\Core\Plugin::class)) {
                    $plugin = \WMCyber\Newsletter\Core\Plugin::instance();
                    $admin = new \WMCyber\Newsletter\Admin\AdminDashboard(
                        $plugin->getDoiRepository(),
                        $plugin->getSettingsManager(),
                        $plugin->getSyncQueue()
                    );
                    $admin->render_dashboard();
                    return;
                }
                break;

            case 'wm-edu-privacy-portal':
            case 'wm-edu-consents':
                if (class_exists(\WM_Edu_Admin::class)) {
                    \WM_Edu_Admin::page_overview();
                    return;
                }
                break;

            case 'wm-social-care-accessibility-hub':
                if (class_exists(\ArnoldWender\SocialCareA11y\Admin\Admin_Dashboard::class)) {
                    \ArnoldWender\SocialCareA11y\Admin\Admin_Dashboard::render_page();
                    return;
                }
                break;

            case 'wm-k9-bh-exam-planner':
            case 'wm-k9-planner':
                if (class_exists(\WM_K9_Admin_Dashboard::class)) {
                    \WM_K9_Admin_Dashboard::render_page();
                    return;
                }
                break;

            case 'wm-cyber-events-core':
            case 'wm-cyber-events':
            case 'cybevents-dashboard':
                if (class_exists(\CyberEvents_Admin::class)) {
                    \CyberEvents_Admin::render_dashboard_page();
                    return;
                }
                break;

            case 'wm-activity-sentinel':
            case 'wm-sentinel-stream':
                if (class_exists(\WM_Sentinel_Admin::class)) {
                    (new \WM_Sentinel_Admin())->render_stream_page();
                    return;
                }
                break;

            case 'wm-prototype-nda-gate':
            case 'wm-nda-dashboard':
                if (class_exists(\WM_NDA_Admin::class)) {
                    (new \WM_NDA_Admin())->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-newsticker':
                if (class_exists(\WM_Newsticker::class)) {
                    \WM_Newsticker::get_instance()->render_admin_docs_page();
                    return;
                }
                break;

            case 'wm-peppol-as4-gateway':
            case 'wm-peppol-gateway':
                if (class_exists(\WMCyber\Peppol\Core\Plugin::class)) {
                    $plugin = \WMCyber\Peppol\Core\Plugin::instance();
                    $admin = new \WMCyber\Peppol\Admin\AdminDashboard(
                        $plugin->get_ubl_generator()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-cra-sbom-beacon':
            case 'wm-cra-beacon':
                if (class_exists(\WMCyber\CRA\Core\Plugin::class)) {
                    $plugin = \WMCyber\CRA\Core\Plugin::instance();
                    $admin = new \WMCyber\CRA\Admin\AdminDashboard(
                        $plugin->get_sbom_generator(),
                        $plugin->get_security_txt(),
                        $plugin->get_csaf_publisher()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-dora-ict-register':
            case 'wm-dora-register':
                if (class_exists(\WMCyber\DORA\Core\Plugin::class)) {
                    $plugin = \WMCyber\DORA\Core\Plugin::instance();
                    $admin = new \WMCyber\DORA\Admin\AdminDashboard(
                        $plugin->get_exporter(),
                        $plugin->get_repository()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-hinschg-whistleblower':
            case 'wm-hinschg-desk':
                if (class_exists(\WMCyber\HinSchG\Core\Plugin::class)) {
                    $plugin = \WMCyber\HinSchG\Core\Plugin::instance();
                    $admin = new \WMCyber\HinSchG\Admin\AdminDashboard(
                        $plugin->get_vault(),
                        $plugin->get_sla_manager()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-kanzlei-ai-sanitizer':
            case 'wm-kanzlei-sanitizer':
                if (class_exists(\WMCyber\Kanzlei\Core\Plugin::class)) {
                    $plugin = \WMCyber\Kanzlei\Core\Plugin::instance();
                    $admin = new \WMCyber\Kanzlei\Admin\AdminDashboard(
                        $plugin->get_ner_engine(),
                        $plugin->get_token_vault()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;

            case 'wm-lksg-supply-auditor':
            case 'wm-lksg-auditor':
                if (class_exists(\WMCyber\LkSG\Core\Plugin::class)) {
                    $plugin = \WMCyber\LkSG\Core\Plugin::instance();
                    $admin = new \WMCyber\LkSG\Admin\AdminDashboard(
                        $plugin->get_risk_calculator(),
                        $plugin->get_bafa_exporter()
                    );
                    $admin->render_dashboard_page();
                    return;
                }
                break;
        }

        // 2. Generic hook fallback if registered in WordPress
        global $_registered_pages;
        if (isset($_registered_pages['toplevel_page_' . $current_page]) && is_callable($_registered_pages['toplevel_page_' . $current_page])) {
            call_user_func($_registered_pages['toplevel_page_' . $current_page]);
            return;
        }

        // 3. Fallback to Cockpit view
        $this->render_cockpit();
    }

    /**
     * The top-level menu slugs each spoke registers on its own, keyed by the spoke slug of CATEGORIES.
     */
    private const ROOT_MENU_SLUGS = [
        'wm-modularscale'              => ['wm-modularscale', 'wm-modular-scale'],
        'wm-newsticker'                => ['wm-newsticker'],
        'wm-cyber-events-core'         => ['wm-cyber-events-core', 'cybevents-dashboard'],
        'wm-activity-sentinel'         => ['wm-activity-sentinel', 'wm-sentinel-stream'],
        'wm-hinschg-whistleblower'     => ['wm-hinschg-whistleblower', 'wm-hinschg-desk'],
        'wm-prototype-nda-gate'        => ['wm-prototype-nda-gate', 'wm-nda-dashboard'],
        'wm-kanzlei-ai-sanitizer'      => ['wm-kanzlei-ai-sanitizer', 'wm-kanzlei-sanitizer'],
        'wm-zero-cdn-asset-localizer'  => ['wm-zero-cdn-asset-localizer', 'wm-zero-cdn'],
        'wm-a11y-sensory-governor'     => ['wm-a11y-sensory-governor', 'wm-sensory-governor'],
        'wm-bfsg-ast-remediator'       => ['wm-bfsg-ast-remediator', 'wm-bfsg-remediator'],
        'wm-bfsg-compliance-dashboard' => ['wm-bfsg-compliance-dashboard', 'wm-bfsg-dashboard'],
        'wm-sovereign-consent-vault'   => ['wm-sovereign-consent-vault', 'wm-consent-vault'],
        'wm-dsgvo-privacy-eraser'      => ['wm-dsgvo-privacy-eraser', 'wm-dsgvo-eraser'],
        'wm-dsgvo-newsletter-suite'    => ['wm-dsgvo-newsletter-suite', 'wm-newsletter-suite'],
        'wm-dora-ict-register'         => ['wm-dora-ict-register', 'wm-dora-register'],
        'wm-lksg-supply-auditor'       => ['wm-lksg-supply-auditor', 'wm-lksg-auditor'],
        'wm-peppol-as4-gateway'        => ['wm-peppol-as4-gateway', 'wm-peppol-gateway'],
        'wm-cra-sbom-beacon'           => ['wm-cra-sbom-beacon', 'wm-cra-beacon'],
        'wm-edu-privacy-portal'        => ['wm-edu-privacy-portal', 'wm-edu-consents'],
        'wm-k9-bh-exam-planner'        => ['wm-k9-bh-exam-planner', 'wm-k9-planner'],
        'wm-social-care-accessibility-hub' => ['wm-social-care-accessibility-hub'],
    ];

    /**
     * Removes the standalone top-level menu of every spoke that register() lists under WM Suite.
     *
     * Only those: until 1.2.0 the menus of all 18 spokes were removed whether they had registered or not,
     * while register() re-adds an entry only for registered ones — a spoke that does not register (one built
     * against an older hub contract, or whose adapter failed) was left with no menu entry at all.
     *
     * @return void
     */
    public function consolidate_standalone_spoke_menus(): void {
        $registered = $this->registry->get_all_modules();

        foreach (self::ROOT_MENU_SLUGS as $spoke_slug => $root_slugs) {
            if (!isset($registered[$spoke_slug])) {
                continue;
            }
            foreach ($root_slugs as $slug) {
                remove_menu_page($slug);
            }
        }
    }

    /**
     * Keeps WM Suite open, with the spoke's entry marked, on a spoke's own sub-pages (`parent_file` filter).
     *
     * consolidate_standalone_spoke_menus() removes a registered spoke's top-level menu, but the spoke's other
     * pages (Activity Sentinel: integrity audit, file integrity, export, settings, handbook) keep that removed
     * menu as their parent. Until 1.2.4 WordPress therefore highlighted nothing on them and WM Suite stayed closed.
     *
     * Returning 'wm-suite-hub' alone does not hold: menu-header.php calls get_admin_page_parent() right after this
     * filter, and that function sets the parent again from $submenu. It maps a parent through
     * $_wp_real_parent_file, so the removed root is mapped there. Doing it here, inside menu-header.php, comes after
     * admin.php has resolved the page hook with the original parent; mapping it during admin_menu would rename the
     * hook ("wm-suite_page_…") and the page would no longer load.
     *
     * @param mixed $parent_file Parent slug WordPress resolved for the current page.
     * @return mixed
     */
    public function adopt_spoke_subpages($parent_file) {
        if (!is_string($parent_file) || '' === $parent_file) {
            return $parent_file;
        }

        $registered = $this->registry->get_all_modules();
        foreach (self::ROOT_MENU_SLUGS as $spoke_slug => $root_slugs) {
            if (!isset($registered[$spoke_slug]) || !in_array($parent_file, $root_slugs, true)) {
                continue;
            }
            foreach (self::CATEGORIES as $category) {
                if (isset($category['modules'][$spoke_slug]['page'])) {
                    $this->adopted_submenu_file = $category['modules'][$spoke_slug]['page'];
                    $GLOBALS['_wp_real_parent_file'][$parent_file] = 'wm-suite-hub';
                    return 'wm-suite-hub';
                }
            }
        }

        return $parent_file;
    }

    /**
     * Marks the spoke's WM Suite entry on a page adopt_spoke_subpages() moved (`submenu_file` filter).
     *
     * @param mixed $submenu_file Submenu slug WordPress would mark.
     * @return mixed
     */
    public function mark_adopted_spoke_entry($submenu_file) {
        return $this->adopted_submenu_file ?? $submenu_file;
    }

    /**
     * Injects subtle styling for submenu category headers in the WordPress admin sidebar.
     *
     * @return void
     */
    public function inject_sidebar_category_styles(): void {
        ?>
        <style>
            #adminmenu #toplevel_page_wm-suite-hub .wp-submenu .wm-menu-cat-header {
                display: block;
                font-size: 9.5px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #94a3b8;
                padding: 6px 0 2px 0;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                margin-top: 4px;
                pointer-events: none;
                cursor: default;
            }
            #adminmenu #toplevel_page_wm-suite-hub .wp-submenu li:has(.wm-menu-cat-header) {
                pointer-events: none;
                cursor: default;
            }
            #adminmenu #toplevel_page_wm-suite-hub .wp-submenu li:has(.wm-menu-cat-header):hover {
                background: transparent;
            }
            /* Menu icons (wm_suite_hub_icon): 1em wide, so they follow the menu's own type size; the sidebar
               sits outside every [data-wm-theme] element, where the family tokens do not reach. */
            #adminmenu #toplevel_page_wm-suite-hub .wp-submenu .wm-block-icon {
                vertical-align: middle;
            }
        </style>
        <?php
    }

    /**
     * Renders the Cockpit HTML template.
     *
     * @return void
     */
    public function render_cockpit(): void {
        if (!current_user_can('manage_wm_suite')) {
            wp_die(esc_html__('Sie haben keine ausreichenden Berechtigungen für den Zugriff auf diese Seite.', 'wm-suite-hub'));
        }

        $matrix = Health_Matrix::get_matrix($this->registry);
        require_once __DIR__ . '/Views/cockpit-view.php';
    }
}
