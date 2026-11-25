<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;

/**
 * Class SLA_Monitor
 *
 * Automates regulatory SLA compliance tracking (HinSchG 7-day acknowledgement,
 * DORA reporting deadlines, NIS2 incident timestamps) via WP-Cron.
 *
 * @package ArnoldWender\SuiteHub\Cron
 * @since   1.0.0
 */
final class SLA_Monitor {

    /**
     * Cron hook names.
     */
    public const HOURLY_HOOK = 'wm_suite_hub_hourly_sla_check';
    public const DAILY_HOOK  = 'wm_suite_hub_daily_maintenance';

    /**
     * Registers cron action handlers and schedules.
     *
     * @return void
     */
    public static function register_schedules(): void {
        add_action(self::HOURLY_HOOK, [self::class, 'execute_hourly_sla_check']);
        add_action(self::DAILY_HOOK, [self::class, 'execute_daily_maintenance']);

        if (!wp_next_scheduled(self::HOURLY_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOURLY_HOOK);
        }

        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            wp_schedule_event(time(), 'daily', self::DAILY_HOOK);
        }
    }

    /**
     * Clears all scheduled cron events upon plugin deactivation.
     *
     * @return void
     */
    public static function clear_schedules(): void {
        $timestamp_hourly = wp_next_scheduled(self::HOURLY_HOOK);
        if ($timestamp_hourly) {
            wp_unschedule_event($timestamp_hourly, self::HOURLY_HOOK);
        }

        $timestamp_daily = wp_next_scheduled(self::DAILY_HOOK);
        if ($timestamp_daily) {
            wp_unschedule_event($timestamp_daily, self::DAILY_HOOK);
        }
    }

    /**
     * Executes hourly SLA compliance checks.
     *
     * @return void
     */
    public static function execute_hourly_sla_check(): void {
        $chainer = new Audit_Chainer();

        // 1. Trigger spoke-specific SLA audits via filter
        $sla_warnings = apply_filters('wm_suite_check_sla_violations', []);

        if (is_array($sla_warnings) && !empty($sla_warnings)) {
            foreach ($sla_warnings as $warning) {
                $chainer->append_event(
                    $warning['module'] ?? 'core',
                    'sla_warning_alert',
                    $warning
                );
            }
        }
    }

    /**
     * Executes daily maintenance: purges stale transients and verifies chain.
     *
     * @return void
     */
    public static function execute_daily_maintenance(): void {
        Health_Matrix::flush_cache();
        
        $chainer = new Audit_Chainer();
        $verification = $chainer->verify_chain_integrity();

        if (!$verification['intact']) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[WM Suite Hub Security Alert] Cryptographic Audit Chain Failure: ' . $verification['details'] ); }
        }
    }
}
