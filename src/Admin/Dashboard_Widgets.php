<?php
/**
 * Class Dashboard_Widgets
 *
 * Registers and renders enterprise WordPress Admin Dashboard widgets for the WM Suite Hub.
 * Provides health telemetry, the audit chain check, the registered modules by regulation
 * and quick-action module controls directly on the WordPress Dashboard, with full Multi-Theme support
 * (WordPress Native, CyberEvents Enterprise, Obsidian Dark, High Contrast).
 *
 * @package ArnoldWender\SuiteHub\Admin
 * @since   1.3.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The widget banner draws its status icon with wm_suite_hub_icon() (see Admin_Menu for why it is required here).
require_once dirname( __DIR__ ) . '/Gutenberg/icons.php';

use ArnoldWender\SuiteHub\Core\Module_Registry;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;
use ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser;

final class Dashboard_Widgets {

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
	 * Constructor.
	 *
	 * @param Module_Registry $registry
	 * @param Audit_Chainer   $audit_chainer
	 */
	public function __construct( Module_Registry $registry, Audit_Chainer $audit_chainer ) {
		$this->registry      = $registry;
		$this->audit_chainer = $audit_chainer;
	}

	/**
	 * Hooks into WordPress admin dashboard setup and asset loading.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', [ $this, 'register_widgets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_widget_styles' ] );
	}

	/**
	 * Enqueues theme-aware widget styles on the dashboard index page.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_widget_styles( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_add_inline_style(
			'dashboard',
			'
			/* Base Widget Container */
			.wm-dash-widget-box.wm-hub-widget {
				padding: 14px;
				border-radius: 6px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				box-sizing: border-box;
				transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
			}
			.wm-hub-widget .wm-dash-banner {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 10px 14px;
				border-radius: 6px;
				margin-bottom: 14px;
			}
			.wm-hub-widget .wm-dash-banner-icon { font-size: 20px; line-height: 1; display: inline-flex; }
			.wm-hub-widget .wm-dash-banner-title { font-weight: 700; font-size: 0.88rem; }
			.wm-hub-widget .wm-dash-banner-sub { font-size: 0.76rem; margin-top: 2px; }

			.wm-hub-widget .wm-dash-kpi-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 10px;
				margin-bottom: 14px;
			}
			.wm-hub-widget .wm-dash-kpi-card {
				border-radius: 6px;
				padding: 10px 8px;
				text-align: center;
			}
			.wm-hub-widget .wm-dash-kpi-val {
				font-size: 1.35rem;
				font-weight: 800;
				line-height: 1.1;
				letter-spacing: -0.02em;
			}
			.wm-hub-widget .wm-dash-kpi-lbl {
				font-size: 0.68rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.05em;
				margin-top: 4px;
			}

			.wm-hub-widget .wm-dash-section-title {
				font-size: 0.80rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.05em;
				margin: 12px 0 8px 0;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}
			.wm-hub-widget .wm-dash-modules-list {
				margin: 0 0 14px 0;
				padding: 0;
				list-style: none;
			}
			.wm-hub-widget .wm-dash-module-item {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 7px 10px;
				border-radius: 5px;
				margin-bottom: 5px;
				font-size: 0.82rem;
			}
			.wm-hub-widget .wm-dash-module-name {
				font-weight: 600;
				display: flex;
				align-items: center;
				gap: 6px;
			}
			.wm-hub-widget .wm-dash-badge {
				display: inline-flex;
				align-items: center;
				padding: 2px 8px;
				border-radius: 9999px;
				font-size: 0.68rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.03em;
			}

			.wm-hub-widget .wm-dash-actions {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
				margin-top: 12px;
				padding-top: 12px;
			}
			.wm-hub-widget .wm-dash-btn {
				font-weight: 700;
				font-size: 0.78rem;
				padding: 6px 12px;
				border-radius: 4px;
				text-decoration: none;
				display: inline-flex;
				align-items: center;
				gap: 6px;
				cursor: pointer;
				border: 1px solid transparent;
				line-height: 1.4;
			}
			.wm-hub-widget .wm-dash-btn-sec {
				font-weight: 600;
				font-size: 0.78rem;
				padding: 6px 12px;
				border-radius: 4px;
				text-decoration: none;
				display: inline-flex;
				align-items: center;
				gap: 6px;
				cursor: pointer;
				line-height: 1.4;
			}

			/* ==========================================================
			   1. THEME: WordPress Native Classic (Default when wp-native)
			   ========================================================== */
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] {
				background: #ffffff;
				color: #1d2327;
				border: 1px solid #c3c4c7;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner {
				background: #f0f6fc;
				border: 1px solid #c8d8ea;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner.is-valid {
				background: #edfaef;
				border-color: #92d89e;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner.is-alert {
				background: #fcf0f1;
				border-color: #f8b9bb;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner-title { color: #1d2327; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner.is-valid .wm-dash-banner-title { color: #007017; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner.is-alert .wm-dash-banner-title { color: #b32d2e; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-banner-sub { color: #50575e; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-kpi-card {
				background: #f6f7f7;
				border: 1px solid #dcdcde;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-kpi-val { color: #2271b1; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-kpi-lbl { color: #646970; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-section-title { color: #1d2327; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-module-item {
				background: #f6f7f7;
				border: 1px solid #dcdcde;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-module-name { color: #1d2327; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-badge-green { background: #edfaef; color: #007017; border: 1px solid #92d89e; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-badge-cyan { background: #f0f6fc; color: #2271b1; border: 1px solid #c8d8ea; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-badge-amber { background: #fcf9e8; color: #996800; border: 1px solid #f0d164; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-badge-red { background: #fcf0f1; color: #b32d2e; border: 1px solid #f8b9bb; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-actions { border-top: 1px solid #dcdcde; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-btn {
				background: #2271b1;
				color: #ffffff;
				border: 1px solid #2271b1;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-btn:hover {
				background: #135e96;
				border-color: #135e96;
				color: #ffffff;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-btn-sec {
				background: #f6f7f7;
				color: #2271b1;
				border: 1px solid #c3c4c7;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="wp-native"] .wm-dash-btn-sec:hover {
				background: #f0f6fc;
				border-color: #2271b1;
				color: #135e96;
			}

			/* ==========================================================
			   2. THEME: CyberEvents Enterprise (Command Banner + Light Surface)
			   ========================================================== */
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] {
				background: #ffffff;
				color: #1d2327;
				border: 1px solid #e2e8f0;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-banner {
				background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
				border: 1px solid #334155;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-banner.is-valid { border-color: rgba(16, 185, 129, 0.4); background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-banner.is-alert { border-color: rgba(239, 68, 68, 0.4); background: linear-gradient(135deg, #7f1d1d 0%, #0f172a 100%); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-banner-title { color: #ffffff; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-banner-sub { color: #94a3b8; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-kpi-card {
				background: #f8fafc;
				border: 1px solid #e2e8f0;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-kpi-val { color: #0369a1; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-kpi-lbl { color: #64748b; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-section-title { color: #0f172a; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-module-item {
				background: #f8fafc;
				border: 1px solid #e2e8f0;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-module-name { color: #334155; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-badge-green { background: #e7f5ea; color: #007017; border: 1px solid #92d89e; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-badge-cyan { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-badge-amber { background: #fcf9e8; color: #996800; border: 1px solid #f0d164; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-badge-red { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-actions { border-top: 1px solid #e2e8f0; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-btn {
				background: linear-gradient(135deg, #0369a1, #075985);
				color: #ffffff;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-btn:hover {
				background: linear-gradient(135deg, #0369a1, #075985);
				color: #ffffff;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-btn-sec {
				background: #f8fafc;
				color: #334155;
				border: 1px solid #cbd5e1;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="cyber-events"] .wm-dash-btn-sec:hover {
				background: #f1f5f9;
				color: #0f172a;
			}

			/* ==========================================================
			   3. THEME: Obsidian Midnight Dark
			   ========================================================== */
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] {
				background: #040813;
				color: #e2e8f0;
				border: 1px solid rgba(255, 255, 255, 0.08);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-banner {
				background: rgba(15, 23, 42, 0.9);
				border: 1px solid rgba(0, 242, 254, 0.25);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-banner.is-valid { border-color: rgba(16, 185, 129, 0.4); background: rgba(6, 78, 59, 0.25); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-banner.is-alert { border-color: rgba(239, 68, 68, 0.4); background: rgba(127, 29, 29, 0.25); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-banner-title { color: #ffffff; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-banner-sub { color: #94a3b8; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-kpi-card {
				background: #090e1d;
				border: 1px solid rgba(255, 255, 255, 0.08);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-kpi-val { color: #00f2fe; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-kpi-lbl { color: #94a3b8; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-section-title { color: #ffffff; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-module-item {
				background: rgba(15, 23, 42, 0.6);
				border: 1px solid rgba(255, 255, 255, 0.04);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-module-name { color: #f1f5f9; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-badge-green { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-badge-cyan { background: rgba(0, 242, 254, 0.15); color: #00f2fe; border: 1px solid rgba(0, 242, 254, 0.3); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-badge-amber { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-badge-red { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-actions { border-top: 1px solid rgba(255, 255, 255, 0.08); }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-btn {
				background: linear-gradient(135deg, #00f2fe, #0077b6);
				color: #020617;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-btn:hover {
				background: linear-gradient(135deg, #38bdf8, #0284c7);
				color: #020617;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-btn-sec {
				background: rgba(255, 255, 255, 0.06);
				color: #e2e8f0;
				border: 1px solid rgba(255, 255, 255, 0.18);
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="obsidian-dark"] .wm-dash-btn-sec:hover {
				background: rgba(255, 255, 255, 0.12);
				color: #ffffff;
			}

			/* ==========================================================
			   4. THEME: High Contrast
			   ========================================================== */
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] {
				background: #000000;
				color: #ffffff;
				border: 2px solid #ffff00;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-banner {
				background: #000000;
				border: 2px solid #ffff00;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-banner.is-valid { border-color: #00ff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-banner.is-alert { border-color: #ff0000; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-banner-title { color: #ffff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-banner-sub { color: #ffffff; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-kpi-card {
				background: #000000;
				border: 2px solid #ffff00;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-kpi-val { color: #ffff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-kpi-lbl { color: #ffffff; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-section-title { color: #ffff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-module-item {
				background: #000000;
				border: 1px solid #ffffff;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-module-name { color: #ffffff; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-badge-green { background: #000000; color: #00ff00; border: 1px solid #00ff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-badge-cyan { background: #000000; color: #ffff00; border: 1px solid #ffff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-badge-amber { background: #000000; color: #ffaa00; border: 1px solid #ffaa00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-badge-red { background: #000000; color: #ff6666; border: 1px solid #ff6666; }

			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-actions { border-top: 2px solid #ffff00; }
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-btn {
				background: #ffff00;
				color: #000000;
				border: 2px solid #ffff00;
				font-weight: 900;
			}
			.wm-dash-widget-box.wm-hub-widget[data-wm-theme="high-contrast"] .wm-dash-btn-sec {
				background: #000000;
				color: #ffffff;
				border: 2px solid #ffffff;
			}
			'
		);
	}

	/**
	 * Registers Dashboard Widgets with WordPress.
	 *
	 * @return void
	 */
	public function register_widgets(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1. Master Suite Cockpit Widget
		wp_add_dashboard_widget(
			'wm_suite_hub_cockpit_widget',
			__( 'WM Suite — Enterprise Suite Cockpit', 'wm-suite-hub' ),
			[ $this, 'render_cockpit_widget' ],
			null,
			null,
			'normal',
			'high'
		);

		// 2. Regulatory & Legal Governance Radar Widget
		wp_add_dashboard_widget(
			'wm_suite_hub_governance_widget',
			__( 'WM Suite — Module nach Regelwerk', 'wm-suite-hub' ),
			[ $this, 'render_governance_widget' ],
			null,
			null,
			'side',
			'high'
		);
	}

	/**
	 * Renders the Master Cockpit Widget.
	 *
	 * @return void
	 */
	public function render_cockpit_widget(): void {
		$modules        = $this->registry->get_all_modules();
		$module_count   = count( $modules );
		$health_matrix  = Health_Matrix::get_matrix( $this->registry );
		$chain_verify   = $this->audit_chainer->verify_chain_integrity();
		$chain_valid    = $chain_verify['intact'] ?? false;
		$chain_length   = $chain_verify['total_checked'] ?? 0;
		$chain_break_at = (int) ( $chain_verify['broken_at_id'] ?? 0 );
		// An empty table verifies trivially and proves nothing, so it is neither green nor "intakt"; a broken chain is
		// amber (its cause may be a changed AUTH_SALT). Until 1.2.3 an empty table read "intakt" on green and a broken
		// one carried is-alert, a class no sheet styles.
		$chain_empty    = $chain_valid && 0 === (int) $chain_length;
		$banner_state   = $chain_empty ? '' : ( $chain_valid ? ' is-valid' : ' is-warning' );
		$bundles        = Manifest_Parser::load_default_manifest();
		$bundle_count   = count( $bundles );
		$active_theme   = function_exists( 'wm_get_suite_theme' ) ? wm_get_suite_theme() : 'cyber-events';

		?>
		<div class="wm-dash-widget-box wm-hub-widget" data-wm-theme="<?php echo esc_attr( $active_theme ); ?>" role="region" aria-label="<?php esc_attr_e( 'Enterprise Suite Cockpit', 'wm-suite-hub' ); ?>">
			<!-- Top Banner: audit chain status, computed on each visit -->
			<div class="wm-dash-banner<?php echo esc_attr( $banner_state ); ?>">
				<span class="wm-dash-banner-icon"><?php echo wp_kses( wm_suite_hub_icon( $chain_valid ? 'shield' : 'alert' ), wm_suite_hub_icon_allowed_html() ); ?></span>
				<div>
					<div class="wm-dash-banner-title">
						<?php
						if ( $chain_empty ) {
							esc_html_e( 'Die Audit-Tabelle ist leer.', 'wm-suite-hub' );
						} elseif ( $chain_valid ) {
							/* translators: %d: number of audit entries checked */
							$chain_text = __( 'Audit-Kette intakt: %d Einträge geprüft', 'wm-suite-hub' );
							echo esc_html( sprintf( $chain_text, (int) $chain_length ) );
						} else {
							// Until 1.2.3 this read "Manipulation erkannt!", also when only AUTH_SALT had changed.
							/* translators: %d: id of the first audit entry that does not verify */
							$chain_text = __( 'Audit-Kette unterbrochen bei Eintrag #%d', 'wm-suite-hub' );
							echo esc_html( sprintf( $chain_text, $chain_break_at ) );
						}
						?>
					</div>
					<div class="wm-dash-banner-sub"><?php esc_html_e( 'Jeder Eintrag ist mit HMAC-SHA256 an den vorherigen gebunden.', 'wm-suite-hub' ); ?></div>
				</div>
			</div>

			<!-- KPI 3-Column Grid -->
			<div class="wm-dash-kpi-grid">
				<div class="wm-dash-kpi-card">
					<div class="wm-dash-kpi-val"><?php echo esc_html( (string) $module_count ); ?></div>
					<div class="wm-dash-kpi-lbl"><?php esc_html_e( 'Aktive Module', 'wm-suite-hub' ); ?></div>
				</div>
				<div class="wm-dash-kpi-card">
					<div class="wm-dash-kpi-val"><?php echo esc_html( (string) $health_matrix['score'] ); ?>%</div>
					<div class="wm-dash-kpi-lbl"><?php esc_html_e( 'Health Score', 'wm-suite-hub' ); ?></div>
				</div>
				<div class="wm-dash-kpi-card">
					<div class="wm-dash-kpi-val"><?php echo esc_html( (string) $bundle_count ); ?></div>
					<div class="wm-dash-kpi-lbl"><?php esc_html_e( 'Solution Packs', 'wm-suite-hub' ); ?></div>
				</div>
			</div>

			<!-- Active Spoke Modules List -->
			<div class="wm-dash-section-title">
				<span><?php esc_html_e( 'Aktive WM Suite Spoke-Module', 'wm-suite-hub' ); ?></span>
				<span style="font-size:0.72rem; opacity: 0.85;"><?php
					/* translators: %d: number of active spoke modules */
					$provided_text = __( '%d Bereitgestellt', 'wm-suite-hub' );
					echo esc_html( sprintf( $provided_text, (int) $module_count ) );
					?></span>
			</div>
			<ul class="wm-dash-modules-list">
				<?php
				$sample_modules = array_slice( $modules, 0, 5, true );
				foreach ( $sample_modules as $slug => $mod ) :
					// Name and version come from the matrix, which read them behind its error boundary; until 1.2.3 a
					// module without them was shown as version 1.0.0.
					$name = (string) ( $health_matrix['modules'][ $slug ]['name'] ?? $slug );
					$ver  = (string) ( $health_matrix['modules'][ $slug ]['version'] ?? '' );
					// The dot was green for every module until 1.2.2; it now follows the health the module reports.
					$dot_status = (string) ( $health_matrix['modules'][ $slug ]['status'] ?? 'DEGRADED' );
					$dot_color  = 'HEALTHY' === $dot_status ? '#10b981' : ( 'ERROR' === $dot_status ? '#ef4444' : '#f59e0b' );
					?>
					<li class="wm-dash-module-item">
						<span class="wm-dash-module-name">
							<span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;" role="img" aria-label="<?php echo esc_attr( Health_Matrix::status_label( $dot_status ) ); ?>" title="<?php echo esc_attr( Health_Matrix::status_label( $dot_status ) ); ?>"></span>
							<?php echo esc_html( $name ); ?>
						</span>
						<?php if ( '' !== $ver ) : ?>
							<span class="wm-dash-badge wm-badge-cyan">v<?php echo esc_html( $ver ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<!-- Quick Actions -->
			<div class="wm-dash-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wm-suite-hub' ) ); ?>" class="wm-dash-btn">
					<span><?php esc_html_e( 'Suite Cockpit öffnen', 'wm-suite-hub' ); ?></span> <span>→</span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wm-suite-hub-marketplace' ) ); ?>" class="wm-dash-btn-sec">
					<span><?php esc_html_e( 'Solution Packs', 'wm-suite-hub' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wm-suite-hub-audit' ) ); ?>" class="wm-dash-btn-sec">
					<span><?php esc_html_e( 'Audit-Log', 'wm-suite-hub' ); ?></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the registered modules by regulation.
	 *
	 * Until 1.2.2 this widget printed a fixed table ("100% Konform", "SBOM v1.6 Aktiv", "6/6 Bestanden") whether
	 * the six plugins were installed or not, and a "download SBOM" button that opened the cockpit. Each row now
	 * says whether the plugin that covers the topic is registered and which health it reports itself. It is not
	 * an assessment of conformity, and the widget says so.
	 *
	 * @return void
	 */
	public function render_governance_widget(): void {
		// Topic => slug of the WM Suite plugin that covers it.
		$topics       = [
			'BFSG'              => 'wm-bfsg-compliance-dashboard',
			'CRA'               => 'wm-cra-sbom-beacon',
			'HinSchG'           => 'wm-hinschg-whistleblower',
			'DORA'              => 'wm-dora-ict-register',
			'DSGVO Art. 17'     => 'wm-dsgvo-privacy-eraser',
			'Peppol / EN 16931' => 'wm-peppol-as4-gateway',
		];
		$rows         = [];
		$active_count = 0;
		foreach ( $topics as $topic => $slug ) {
			$module = $this->registry->get_module( $slug );
			if ( null === $module ) {
				$rows[] = [ 'topic' => $topic, 'name' => $slug, 'status' => __( 'Nicht aktiv', 'wm-suite-hub' ), 'badge' => 'wm-badge-cyan' ];
				continue;
			}
			$active_count++;
			// Same normalisation and error boundary as Module_Registry::get_health_summary().
			try {
				$name   = (string) $module->get_name();
				$status = strtoupper( (string) $module->get_health_status() );
			} catch ( \Throwable $e ) {
				$name   = $slug;
				$status = 'ERROR';
			}
			if ( ! in_array( $status, [ 'HEALTHY', 'DEGRADED', 'ERROR', 'NEEDS_CONFIG' ], true ) ) {
				$status = 'DEGRADED';
			}
			$rows[] = [
				'topic'  => $topic,
				'name'   => $name,
				// The translated label; until 1.2.3 the badge printed the raw enum (HEALTHY, NEEDS_CONFIG …).
				'status' => Health_Matrix::status_label( $status ),
				'badge'  => 'HEALTHY' === $status ? 'wm-badge-green' : ( 'ERROR' === $status ? 'wm-badge-red' : 'wm-badge-amber' ),
			];
		}
		$topic_count = count( $topics );
		if ( $active_count === $topic_count ) {
			/* translators: 1: number of active plugins, 2: number of plugins listed */
			$count_text = sprintf( __( 'Vollständig aktiv (%1$d/%2$d)', 'wm-suite-hub' ), $active_count, $topic_count );
		} elseif ( $active_count > 0 ) {
			/* translators: 1: number of active plugins, 2: number of plugins listed */
			$count_text = sprintf( __( 'Teilweise aktiv (%1$d/%2$d)', 'wm-suite-hub' ), $active_count, $topic_count );
		} else {
			/* translators: %d: number of plugins listed */
			$count_text = sprintf( __( '%d Module verfügbar', 'wm-suite-hub' ), $topic_count );
		}
		$active_theme = function_exists( 'wm_get_suite_theme' ) ? wm_get_suite_theme() : 'cyber-events';

		?>
		<div class="wm-dash-widget-box wm-hub-widget" data-wm-theme="<?php echo esc_attr( $active_theme ); ?>" role="region" aria-label="<?php esc_attr_e( 'WM Suite — Module nach Regelwerk', 'wm-suite-hub' ); ?>">
			<div class="wm-dash-section-title" style="margin-top:0 !important;">
				<span><?php esc_html_e( 'Zustand der zuständigen Module', 'wm-suite-hub' ); ?></span>
				<span style="font-size:0.72rem; opacity: 0.85;"><?php echo esc_html( $count_text ); ?></span>
			</div>
			<ul class="wm-dash-modules-list">
				<?php foreach ( $rows as $row ) : ?>
					<li class="wm-dash-module-item">
						<span class="wm-dash-module-name"><?php echo esc_html( $row['topic'] . ' · ' . $row['name'] ); ?></span>
						<span class="wm-dash-badge <?php echo esc_attr( $row['badge'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p style="font-size:0.72rem; opacity: 0.85; margin:0;"><?php esc_html_e( 'Zeigt, welches WM-Suite-Plugin aktiv ist und welchen Zustand es meldet. Keine Konformitätsbewertung.', 'wm-suite-hub' ); ?></p>
		</div>
		<?php
	}
}
