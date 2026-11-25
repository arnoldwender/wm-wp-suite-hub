<?php
/**
 * WM Suite Compliance SDK Bridge
 *
 * Standalone, zero-dependency bridge allowing any WordPress plugin or theme
 * to interface safely with WM Suite Compliance Suite Hub.
 *
 * @package ArnoldWender\ComplianceSDK
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WM_Compliance_Bridge {

    /**
     * Checks whether the WM Suite Hub core plugin is active in the environment.
     *
     * @return bool
     */
    public static function is_hub_active(): bool {
        if ( ! function_exists( 'did_action' ) ) {
            return false;
        }
        return did_action( 'wm_suite_hub_loaded' ) > 0 || class_exists( 'ArnoldWender\SuiteHub\Core\Hub_Orchestrator' );
    }

    /**
     * Emits an immutable audit event to the GoBD HMAC-SHA256 ledger.
     *
     * @param string               $module_slug Originating plugin identifier.
     * @param string               $event_type  Action identifier.
     * @param string               $category    Category ('finance', 'privacy', 'security', 'general').
     * @param string               $severity    Severity ('info', 'warning', 'critical', 'emergency').
     * @param int                  $actor_id    WordPress user ID or 0 for system.
     * @param string               $resource_id Target resource identifier.
     * @param array<string, mixed> $metadata    Contextual metadata.
     */
    public static function log_event(
        string $module_slug,
        string $event_type,
        string $category = 'general',
        string $severity = 'info',
        int $actor_id = 0,
        string $resource_id = '',
        array $metadata = []
    ): void {
        if ( function_exists( 'do_action' ) && function_exists( 'has_action' ) ) {
            if ( has_action( 'wm_suite_hub_dispatch_audit_event' ) ) {
                do_action(
                    'wm_suite_hub_dispatch_audit_event',
                    $module_slug,
                    $event_type,
                    $category,
                    $severity,
                    $actor_id,
                    $resource_id,
                    $metadata
                );
            } elseif ( has_action( 'wm_compliance_record_audit_event' ) ) {
                do_action( 'wm_compliance_record_audit_event', [
                    'origin_module' => $module_slug,
                    'event_type'    => $event_type,
                    'severity'      => strtoupper( $severity ),
                    'actor_id'      => $actor_id > 0 ? (string) $actor_id : 'SYSTEM',
                    'context_data'  => array_merge( $metadata, [
                        'category'    => $category,
                        'resource_id' => $resource_id,
                    ] ),
                    'timestamp'     => time(),
                ] );
            }
        }
    }
}
