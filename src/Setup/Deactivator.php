<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Cron\SLA_Monitor;

/**
 * Class Deactivator
 *
 * Handles lifecycle deactivation events for the WM Suite Hub.
 * Flushes temporary runtime caches and unregisters background Cron hooks.
 *
 * @package ArnoldWender\SuiteHub\Setup
 * @since   1.0.0
 */
final class Deactivator {

    /**
     * Executes deactivation cleanup routines.
     *
     * @return void
     */
    public static function deactivate(): void {
        // Clear background cron schedules
        SLA_Monitor::clear_schedules();

        // Flush health matrix transients
        delete_transient('wm_suite_hub_health_matrix');
        delete_transient('wm_suite_hub_telemetry_cache');
    }
}
