<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Core\Module_Registry;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class API_Controller
 *
 * Exposes secure REST endpoints for the Cockpit SPA interface, WP-CLI bridges,
 * and automated telemetry monitoring.
 *
 * Namespace: /wp-json/wm-hub/v1/
 *
 * @package ArnoldWender\SuiteHub\API
 * @since   1.0.0
 */
final class API_Controller {

    /**
     * REST namespace identifier.
     */
    private const REST_NAMESPACE = 'wm-hub/v1';

    /**
     * Module Registry reference.
     *
     * @var Module_Registry
     */
    private Module_Registry $registry;

    /**
     * Audit Chainer reference.
     *
     * @var Audit_Chainer
     */
    private Audit_Chainer $audit_chainer;

    /**
     * API_Controller constructor.
     *
     * @param Module_Registry $registry
     * @param Audit_Chainer   $audit_chainer
     */
    public function __construct(Module_Registry $registry, Audit_Chainer $audit_chainer) {
        $this->registry      = $registry;
        $this->audit_chainer = $audit_chainer;
    }

    /**
     * Registers all endpoints under the wm-hub/v1 namespace.
     *
     * @return void
     */
    public function register_routes(): void {
        // 1. GET /fleet/status
        register_rest_route(self::REST_NAMESPACE, '/fleet/status', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_fleet_status'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'force_refresh' => [
                        'type'              => 'boolean',
                        'default'           => false,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ],
        ]);

        // 2. GET /fleet/metrics
        register_rest_route(self::REST_NAMESPACE, '/fleet/metrics', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_fleet_metrics'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 3. GET /audit/logs
        register_rest_route(self::REST_NAMESPACE, '/audit/logs', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_audit_logs'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'limit' => [
                        'type'              => 'integer',
                        'default'           => 50,
                        'minimum'           => 1,
                        'maximum'           => 200,
                        'sanitize_callback' => 'absint',
                    ],
                    'module' => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);

        // 4. GET /audit/verify
        register_rest_route(self::REST_NAMESPACE, '/audit/verify', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'verify_audit_chain'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 6. POST & GET /settings/theme
        register_rest_route(self::REST_NAMESPACE, '/settings/theme', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_theme_setting'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'theme' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'scope' => [
                        'type'              => 'string',
                        'default'           => 'global',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_theme_setting'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 6b. POST & GET /settings/lifecycle
        register_rest_route(self::REST_NAMESPACE, '/settings/lifecycle', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_lifecycle_setting'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'delete_data_on_uninstall' => [
                        'type'              => 'boolean',
                        'required'          => true,
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_lifecycle_setting'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 7. GET /marketplace/bundles
        register_rest_route(self::REST_NAMESPACE, '/marketplace/bundles', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_marketplace_bundles'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 8. POST /marketplace/preflight
        register_rest_route(self::REST_NAMESPACE, '/marketplace/preflight', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'run_preflight_audit'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'bundle_id' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ]);

        // 9. POST /marketplace/install-bundle
        register_rest_route(self::REST_NAMESPACE, '/marketplace/install-bundle', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'install_marketplace_bundle'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'bundle_id' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'dry_run' => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);

        // 10. GET /marketplace/telemetry
        register_rest_route(self::REST_NAMESPACE, '/marketplace/telemetry', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_marketplace_telemetry'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);

        // 11. POST /module/(?P<slug>[a-zA-Z0-9_-]+)/action
        register_rest_route(self::REST_NAMESPACE, '/module/(?P<slug>[a-zA-Z0-9_-]+)/action', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'execute_module_action'],
                'permission_callback' => [$this, 'check_permissions'],
                'args'                => [
                    'action' => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'params' => [
                        'type'     => 'object',
                        'required' => false,
                        'default'  => [],
                    ],
                ],
            ],
        ]);

        // 6. POST /cache/flush
        register_rest_route(self::REST_NAMESPACE, '/cache/flush', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'flush_cache'],
                'permission_callback' => [$this, 'check_permissions'],
            ],
        ]);
    }

    /**
     * Verifies caller has the 'manage_wm_suite' capability.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function check_permissions(WP_REST_Request $request): true|WP_Error {
        if (!current_user_can('manage_wm_suite')) {
            return new WP_Error(
                'rest_forbidden_compliance',
                __('Zugriff verweigert. Unzureichende Berechtigungen für das WM Suite Cockpit.', 'wm-suite-hub'),
                ['status' => rest_authorization_required_code()]
            );
        }
        return true;
    }

    /**
     * Endpoint handler: GET /fleet/status
     */
    public function get_fleet_status(WP_REST_Request $request): WP_REST_Response {
        $force_refresh = (bool) $request->get_param('force_refresh');
        $matrix        = Health_Matrix::get_matrix($this->registry, $force_refresh);

        return rest_ensure_response([
            'success' => true,
            'data'    => $matrix,
        ]);
    }

    /**
     * Endpoint handler: GET /fleet/metrics
     */
    public function get_fleet_metrics(WP_REST_Request $request): WP_REST_Response {
        $modules = $this->registry->get_all_modules();
        $metrics = [];

        foreach ($modules as $slug => $module) {
            try {
                $metrics[$slug] = $module->get_telemetry_metrics();
            } catch (\Throwable $e) {
                $metrics[$slug] = ['error' => $e->getMessage()];
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data'    => $metrics,
        ]);
    }

    /**
     * Endpoint handler: GET /audit/logs
     */
    public function get_audit_logs(WP_REST_Request $request): WP_REST_Response {
        $limit  = (int) $request->get_param('limit');
        $module = $request->get_param('module');

        $logs = $this->audit_chainer->get_recent_logs($limit, is_string($module) ? $module : null);

        return rest_ensure_response([
            'success' => true,
            'count'   => count($logs),
            'data'    => $logs,
        ]);
    }

    /**
     * Endpoint handler: GET /audit/verify
     */
    public function verify_audit_chain(WP_REST_Request $request): WP_REST_Response {
        $verification = $this->audit_chainer->verify_chain_integrity();

        return rest_ensure_response([
            'success' => true,
            'data'    => $verification,
        ]);
    }

    /**
     * Endpoint handler: POST /module/{slug}/action
     */
    public function execute_module_action(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $slug   = sanitize_key((string) $request->get_param('slug'));
        $action = sanitize_key((string) $request->get_param('action'));
        $params = (array) $request->get_param('params');

        $module = $this->registry->get_module($slug);
        if ($module === null) {
            /* translators: %s: module slug */
            $not_found = __('Modul "%s" ist nicht registriert oder inaktiv.', 'wm-suite-hub');
            return new WP_Error(
                'rest_module_not_found',
                sprintf($not_found, $slug),
                ['status' => 404]
            );
        }

        try {
            $result = $module->execute_action($action, $params);
            
            // Log administrative action in audit chain
            $this->audit_chainer->append_event(
                $slug,
                'admin_action_executed',
                [
                    'action' => $action,
                    'result' => $result,
                    'user'   => get_current_user_id(),
                ],
                get_current_user_id()
            );

            return rest_ensure_response([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            /* translators: %s: exception message */
            $failed = __('Ausführung der Modulaktion fehlgeschlagen: %s', 'wm-suite-hub');
            return new WP_Error(
                'rest_module_action_failed',
                sprintf($failed, $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Endpoint handler: POST /cache/flush
     */
    public function flush_cache(WP_REST_Request $request): WP_REST_Response {
        $flushed = Health_Matrix::flush_cache();

        return rest_ensure_response([
            'success' => $flushed,
            'message' => __('Telemetrie-Zwischenspeicher erfolgreich geleert.', 'wm-suite-hub'),
        ]);
    }

    /**
     * Updates the active theme setting.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_theme_setting(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $theme = sanitize_key((string) $request->get_param('theme'));
        $scope = sanitize_key((string) ($request->get_param('scope') ?: 'global'));

        $valid_themes = ['cyber-events', 'wp-native', 'obsidian-dark', 'high-contrast'];
        if (!in_array($theme, $valid_themes, true)) {
            return new WP_Error(
                'rest_invalid_theme',
                __('Ungültiges Theme ausgewählt.', 'wm-suite-hub'),
                ['status' => 400]
            );
        }

        if ($scope === 'user' && is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'wm_suite_theme', $theme);
        } else {
            update_option('wm_suite_theme', $theme, false);
        }

        // Log to audit chain
        $this->audit_chainer->append_event(
            'wm-suite-hub',
            'theme_updated',
            ['theme' => $theme, 'scope' => $scope],
            get_current_user_id()
        );

        return rest_ensure_response([
            'success' => true,
            'theme'   => $theme,
            'scope'   => $scope,
            'message' => __('Theme erfolgreich aktualisiert.', 'wm-suite-hub'),
        ]);
    }

    /**
     * Retrieves current theme configuration.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_theme_setting(WP_REST_Request $request): WP_REST_Response {
        $active_theme = function_exists('wm_get_suite_theme') ? wm_get_suite_theme() : 'cyber-events';
        return rest_ensure_response([
            'active_theme'             => $active_theme,
            'delete_data_on_uninstall' => (bool) get_option('wm_suite_hub_delete_data_on_uninstall', false),
            'available_themes'         => [
                'cyber-events'  => __('CyberEvents Enterprise (Standard)', 'wm-suite-hub'),
                'wp-native'     => __('WordPress Native Classic', 'wm-suite-hub'),
                'obsidian-dark' => __('Obsidian Midnight Dark', 'wm-suite-hub'),
                'high-contrast' => __('High-Contrast', 'wm-suite-hub'),
            ],
        ]);
    }

    /**
     * Updates the opt-in uninstall data purge setting (`wm_suite_hub_delete_data_on_uninstall`).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_lifecycle_setting(WP_REST_Request $request): WP_REST_Response {
        $delete_on_uninstall = (bool) $request->get_param('delete_data_on_uninstall');
        update_option('wm_suite_hub_delete_data_on_uninstall', $delete_on_uninstall ? 1 : 0, false);

        $this->audit_chainer->append_event(
            'wm-suite-hub',
            'lifecycle_setting_updated',
            ['delete_data_on_uninstall' => $delete_on_uninstall],
            get_current_user_id()
        );

        return rest_ensure_response([
            'success'                  => true,
            'delete_data_on_uninstall' => $delete_on_uninstall,
            'message'                  => __('Deinstallations-Einstellung aktualisiert.', 'wm-suite-hub'),
        ]);
    }

    /**
     * Retrieves the opt-in uninstall data purge setting.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_lifecycle_setting(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'delete_data_on_uninstall' => (bool) get_option('wm_suite_hub_delete_data_on_uninstall', false),
        ]);
    }

    /**
     * Retrieves available bundles and their package installation status.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_marketplace_bundles(WP_REST_Request $request): WP_REST_Response {
        $bundles = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();
        $response_bundles = [];

        foreach ($bundles as $id => $bundle) {
            $bundle_data = $bundle->to_array();
            $installed_count = 0;

            foreach ($bundle_data['packages'] as &$pkg) {
                $slug = $pkg['slug'];
                $is_registered = $this->registry->has_module($slug);
                $pkg_dir = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : '') . '/' . $slug;
                $is_on_disk = file_exists($pkg_dir);

                $pkg['installed'] = $is_on_disk;
                $pkg['active']    = $is_registered;
                if ($is_on_disk) {
                    $installed_count++;
                }
            }
            unset($pkg);

            $bundle_data['installed_count'] = $installed_count;
            $bundle_data['is_fully_installed'] = ($installed_count === count($bundle_data['packages']));
            $bundle_data['resolved_order'] = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($bundle->get_dependency_graph());

            $response_bundles[] = $bundle_data;
        }

        return rest_ensure_response([
            'bundles' => $response_bundles,
            'count'   => count($response_bundles),
        ]);
    }

    /**
     * Runs pre-flight server environment checks for a bundle.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function run_preflight_audit(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $bundle_id = sanitize_key((string) $request->get_param('bundle_id'));
        $bundles = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();

        if (!isset($bundles[$bundle_id])) {
            return new WP_Error('rest_bundle_not_found', __('Bundle nicht gefunden.', 'wm-suite-hub'), ['status' => 404]);
        }

        $bundle = $bundles[$bundle_id];
        $audit  = \ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor::audit($bundle);
        $resolved_order = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($bundle->get_dependency_graph());

        return rest_ensure_response([
            'bundle_id'        => $bundle_id,
            'suite_name'       => $bundle->get_suite_name(),
            'preflight'        => $audit,
            'resolved_order'   => $resolved_order,
            // Without a single edge the order is the listed one; the dialog says so.
            'has_dependencies' => $bundle->has_dependencies(),
        ]);
    }

    /**
     * Resolves a bundle's install order after the pre-flight audit (dry_run=true); without
     * dry_run it answers 501 and writes nothing, because installing is not wired (see below).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function install_marketplace_bundle(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $bundle_id = sanitize_key((string) $request->get_param('bundle_id'));
        $dry_run   = (bool) $request->get_param('dry_run');
        $bundles   = \ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser::load_default_manifest();

        if (!isset($bundles[$bundle_id])) {
            return new WP_Error('rest_bundle_not_found', __('Bundle nicht gefunden.', 'wm-suite-hub'), ['status' => 404]);
        }

        $bundle = $bundles[$bundle_id];
        $audit  = \ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor::audit($bundle);

        if (!$audit['passed']) {
            return new WP_Error(
                'rest_preflight_failed',
                __('Pre-Flight Überprüfung fehlgeschlagen.', 'wm-suite-hub'),
                ['status' => 422, 'errors' => $audit['errors']]
            );
        }

        $resolved_order = \ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver::resolve_order($bundle->get_dependency_graph());

        if ($dry_run) {
            /* translators: %d: number of packages in the resolved install order */
            $dry_run_text = __('Dry-Run erfolgreich: %d Pakete geordnet aufgelöst.', 'wm-suite-hub');
            return rest_ensure_response([
                'success'        => true,
                'dry_run'        => true,
                'bundle_id'      => $bundle_id,
                'resolved_order' => $resolved_order,
                'message'        => sprintf($dry_run_text, count($resolved_order)),
            ]);
        }

        // The real install path (download_uri → Ed25519 signature → Atomic_Installer) is not
        // wired: the signing root key is still a placeholder (fleet audit 2026-09-13, D9). Until
        // it is, this endpoint must not answer "installed" nor seal bundle_installed into the
        // GoBD chain for an install that never happened (§6.2 #3). Dry-run above stays available.
        return new WP_Error(
            'rest_install_not_available',
            __('Die Installation von Solution Packs ist in dieser Version noch nicht verfügbar. Nutzen Sie dry_run=true für die Auflösung der Reihenfolge.', 'wm-suite-hub'),
            [
                'status'         => 501,
                'bundle_id'      => $bundle_id,
                'resolved_order' => $resolved_order,
            ]
        );
    }

    /**
     * Returns the deduplication counters of this request. A REST request enqueues no assets, so
     * they read 0 here; nothing in this plugin measures timing.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_marketplace_telemetry(WP_REST_Request $request): WP_REST_Response {
        $deduplicator = \ArnoldWender\SuiteHub\SharedKernel\Deduplication\Asset_Deduplicator::get_instance();
        return rest_ensure_response([
            'telemetry' => $deduplicator->get_telemetry(),
        ]);
    }
}


