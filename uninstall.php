<?php
/**
 * Fired when the WM Suite Hub plugin is uninstalled.
 *
 * Opt-in data purge (D-UNINSTALL): preserves the HMAC-SHA256 audit log table,
 * custom roles/capabilities, and settings by default unless the administrator
 * explicitly opted into full data removal (`wm_suite_hub_delete_data_on_uninstall`).
 *
 * @package ArnoldWender\SuiteHub
 * @since   1.0.0
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit immediately
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Default: preserve audit chain, capabilities, and options unless explicitly opted in.
if (!(bool) get_option('wm_suite_hub_delete_data_on_uninstall', false)) {
    return;
}

// 1. Remove custom capability from all roles
$wp_roles = wp_roles();
if ($wp_roles !== null) {
    foreach ($wp_roles->roles as $role_slug => $role_data) {
        $role = $wp_roles->get_role($role_slug);
        if ($role !== null && $role->has_cap('manage_wm_suite')) {
            $role->remove_cap('manage_wm_suite');
        }
    }

    // 2. Remove custom compliance officer role
    remove_role('wm_compliance_officer');
}

// 3. Delete registered options and transients
delete_option('wm_suite_hub_version');
delete_option('wm_suite_hub_db_version');
delete_option('wm_suite_hub_activated_at');
delete_option('wm_suite_hub_delete_data_on_uninstall');
// Written by POST /settings/theme with scope=global, and autoloaded on every request —
// left behind it is loaded forever by a plugin that no longer exists.
delete_option('wm_suite_theme');
delete_transient('wm_suite_hub_health_matrix');
delete_transient('wm_suite_hub_telemetry_cache');

// The same key exists per user when the theme was set with scope=user.
delete_metadata('user', 0, 'wm_suite_theme', '', true);

// 4. Clean up audit log table when user explicitly opted into full purge
global $wpdb;
$table_name = $wpdb->prefix . 'wm_suite_audit_log';
$wpdb->query("DROP TABLE IF EXISTS {$table_name};");
