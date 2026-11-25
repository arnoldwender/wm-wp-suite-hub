<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Telemetry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Core\Module_Registry;

/**
 * Class Health_Matrix
 *
 * Compiles and caches the overall fleet health score, domain breakdowns,
 * and operational telemetry metrics. Implements strict Transients API caching
 * to protect TTFB and prevent wp_options autoload bloat.
 *
 * @package ArnoldWender\SuiteHub\Telemetry
 * @since   1.0.0
 */
final class Health_Matrix {

    /**
     * Transient cache key for health matrix data.
     */
    private const CACHE_KEY = 'wm_suite_hub_health_matrix';

    /**
     * Cache expiration time in seconds (15 minutes).
     */
    private const CACHE_TTL = 900; // 15 * MINUTE_IN_SECONDS

    /**
     * Retrieves the compiled health matrix, pulling from transient cache or recalculating.
     *
     * @param Module_Registry $registry      Active module registry.
     * @param bool            $force_refresh Flag to bypass transient cache.
     * @return array<string, mixed>
     */
    public static function get_matrix(Module_Registry $registry, bool $force_refresh = false): array {
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $raw_summary = $registry->get_health_summary();
        $total       = (int) $raw_summary['total'];
        $healthy     = (int) $raw_summary['healthy'];
        $degraded    = (int) $raw_summary['degraded'];
        $errors      = (int) $raw_summary['error'];
        $unconfigured= (int) $raw_summary['needs_config'];

        // Weighted health of the registered modules (0 - 100): healthy 100, degraded 60, needs config 30,
        // error 0. With no module registered there is nothing to score. Until 1.2.2 the divisor was
        // max(1, total) and also went into counts['total'], so an empty hub showed "1 registered" and HEALTHY.
        $score = $total > 0
            ? (int) round((($healthy * 100) + ($degraded * 60) + ($unconfigured * 30)) / $total)
            : 0;

        // Classify global system status; an empty registry is not a healthy one.
        $global_status = 'HEALTHY';
        if ($errors > 0) {
            $global_status = 'CRITICAL';
        } elseif ($total === 0 || $degraded > 0 || $unconfigured > 0) {
            $global_status = 'WARNING';
        }

        $matrix = [
            'timestamp'     => current_time('mysql'),
            'score'         => $score,
            'global_status' => $global_status,
            'counts'        => [
                'total'        => $total,
                'healthy'      => $healthy,
                'degraded'     => $degraded,
                'error'        => $errors,
                'needs_config' => $unconfigured,
            ],
            'modules'       => $raw_summary['modules'],
            'environment'   => [
                'php_version'       => PHP_VERSION,
                'wp_version'        => get_bloginfo('version'),
                'is_ssl'            => is_ssl(),
                'has_object_cache'  => wp_using_ext_object_cache(),
            ],
        ];

        // Store transient with explicit non-zero TTL (Rule: Never use 0 expiration)
        set_transient(self::CACHE_KEY, $matrix, self::CACHE_TTL);

        return $matrix;
    }

    /**
     * The translated label of a module status (HEALTHY, DEGRADED, ERROR, NEEDS_CONFIG) or of the global
     * status (HEALTHY, WARNING, CRITICAL). Until 1.2.3 the cockpit and the widgets printed the raw enum.
     *
     * @param string $status Status as the registry or the matrix reports it.
     * @return string
     */
    public static function status_label(string $status): string {
        return match (strtoupper($status)) {
            'HEALTHY'      => __('Gesund', 'wm-suite-hub'),
            'DEGRADED'     => __('Eingeschränkt', 'wm-suite-hub'),
            'NEEDS_CONFIG' => __('Nicht konfiguriert', 'wm-suite-hub'),
            'ERROR'        => __('Fehler', 'wm-suite-hub'),
            'WARNING'      => __('Warnung', 'wm-suite-hub'),
            'CRITICAL'     => __('Kritisch', 'wm-suite-hub'),
            default        => __('Unbekannt', 'wm-suite-hub'),
        };
    }

    /**
     * Explicitly purges the health matrix transient cache.
     *
     * @return bool
     */
    public static function flush_cache(): bool {
        return delete_transient(self::CACHE_KEY);
    }
}
