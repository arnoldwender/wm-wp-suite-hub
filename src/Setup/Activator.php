<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Setup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activator
 *
 * Handles lifecycle activation events for the WM Suite Hub.
 * Sets up the manage_wm_suite capability, the compliance officer role
 * and the append-only audit table.
 *
 * @package ArnoldWender\SuiteHub\Setup
 * @since   1.0.0
 */
final class Activator {

    private const SCHEMA_VERSION = 2;

    /**
     * Ensures schema, roles, and default options exist even after silent plugin updates.
     *
     * @return void
     */
    public static function maybe_upgrade(): void {
        if ((int) get_option('wm_suite_hub_db_version', 0) >= self::SCHEMA_VERSION) {
            return;
        }
        self::activate_site();
    }

    /**
     * Executes activation routines.
     *
     * @param bool $network_wide Whether the plugin was network-activated on Multisite.
     * @return void
     */
    public static function activate(bool $network_wide = false): void {
        if (
            $network_wide
            && function_exists('is_multisite')
            && is_multisite()
            && function_exists('get_sites')
            && function_exists('switch_to_blog')
            && function_exists('restore_current_blog')
        ) {
            $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
            foreach ($site_ids as $site_id) {
                switch_to_blog((int) $site_id);
                try {
                    self::activate_site();
                } finally {
                    restore_current_blog();
                }
            }
            return;
        }

        self::activate_site();
    }

    /**
     * Provisions roles, database tables, and initial options for the active site.
     *
     * @return void
     */
    private static function activate_site(): void {
        self::setup_roles_and_capabilities();
        self::setup_database_tables();
        self::setup_initial_options();
        update_option('wm_suite_hub_db_version', self::SCHEMA_VERSION, false);
    }

    /**
     * Registers the granular 'manage_wm_suite' capability and 'wm_compliance_officer' role.
     *
     * @return void
     */
    private static function setup_roles_and_capabilities(): void {
        $wp_roles = function_exists('wp_roles') ? wp_roles() : null;
        if ($wp_roles === null) {
            return;
        }

        // 1. Grant capability to Administrator
        $admin_role = $wp_roles->get_role('administrator');
        if ($admin_role !== null) {
            $admin_role->add_cap('manage_wm_suite');
        }

        // 2. Create isolated Compliance Officer (DPO) role
        if (function_exists('add_role')) {
            add_role(
                'wm_compliance_officer',
                __('Compliance Officer (DPO)', 'wm-suite-hub'),
                [
                    'read'            => true,
                    'manage_wm_suite' => true,
                ]
            );
        }
    }

    /**
     * Creates or updates the audit table in the WordPress database.
     *
     * @return void
     */
    private static function setup_database_tables(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'wm_suite_audit_log';
        $charset_collate = method_exists($wpdb, 'get_charset_collate')
            ? $wpdb->get_charset_collate()
            : 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            module_slug varchar(64) NOT NULL,
            event_type varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            previous_hash char(64) NOT NULL,
            current_hash char(64) NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_module_slug (module_slug),
            KEY idx_event_type (event_type),
            KEY idx_timestamp (timestamp),
            KEY idx_current_hash (current_hash)
        ) {$charset_collate};";

        if (!function_exists('dbDelta')) {
            $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (file_exists($upgrade_file)) {
                require_once $upgrade_file;
            }
        }
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }
    }

    /**
     * Sets default plugin configuration options.
     *
     * @return void
     */
    private static function setup_initial_options(): void {
        if (get_option('wm_suite_hub_version') === false) {
            add_option('wm_suite_hub_version', WM_SUITE_HUB_VERSION, '', 'no');
            add_option('wm_suite_hub_activated_at', current_time('mysql'), '', 'no');
        }
        if (get_option('wm_suite_hub_delete_data_on_uninstall') === false) {
            add_option('wm_suite_hub_delete_data_on_uninstall', false, '', 'no');
        }
    }
}
