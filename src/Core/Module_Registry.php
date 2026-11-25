<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface;
use Throwable;

/**
 * Class Module_Registry
 *
 * Immutable, fault-tolerant service registry for WM Suite satellite plugins.
 * Utilizes PHP 8.2+ readonly class semantics to prevent runtime property mutation.
 * Implements strict Error Boundary protection (Goonies & Jedi Rules) to isolate
 * satellite plugin exceptions from crashing WordPress core or wp-admin.
 *
 * @package ArnoldWender\SuiteHub\Core
 * @since   1.0.0
 */
final readonly class Module_Registry {

    /**
     * Immutable map of validated satellite modules keyed by slug.
     *
     * @var array<string, WM_Plugin_Module_Interface>
     */
    private array $modules;

    /**
     * Module_Registry constructor.
     *
     * Collects all registered modules via WordPress filter hooks
     * and performs strict interface validation.
     */
    public function __construct() {
        $candidates_a = (array) apply_filters('wm_register_suite_module', []);
        $candidates_b = (array) apply_filters('wm_suite_hub_registered_modules', []);
        $raw_candidates = array_merge($candidates_a, $candidates_b);

        $validated = [];

        if (is_array($raw_candidates)) {
            foreach ($raw_candidates as $candidate) {
                try {
                    if ($candidate instanceof WM_Plugin_Module_Interface) {
                        $slug = sanitize_key($candidate->get_module_slug());
                        if (!empty($slug)) {
                            $validated[$slug] = $candidate;
                        }
                    } else {
                        $this->log_security_warning(
                            is_object($candidate) ? get_class($candidate) : gettype($candidate)
                        );
                    }
                } catch (Throwable $e) {
                    // Goonies & Jedi Rule: Never let a third-party or spoke exception break wp-admin
                    $err_msg = sprintf('[WM Suite Hub] Exception caught during module registration: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine());
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( $err_msg ); }
                }
            }
        }

        $this->modules = $validated;
    }

    /**
     * Retrieves a single registered module by its unique slug.
     *
     * @param string $slug Module identifier.
     * @return WM_Plugin_Module_Interface|null
     */
    public function get_module(string $slug): ?WM_Plugin_Module_Interface {
        return $this->modules[$slug] ?? null;
    }

    /**
     * Returns all validated modules registered in the Hub.
     *
     * @return array<string, WM_Plugin_Module_Interface>
     */
    public function get_all_modules(): array {
        return $this->modules;
    }

    /**
     * Checks if a module is registered and active.
     *
     * @param string $slug Module identifier.
     * @return bool
     */
    public function has_module(string $slug): bool {
        return isset($this->modules[$slug]);
    }

    /**
     * Returns the total count of registered modules.
     *
     * @return int
     */
    public function count(): int {
        return count($this->modules);
    }

    /**
     * Generates a high-level fleet health summary matrix across all registered spokes.
     *
     * @return array<string, mixed>
     */
    public function get_health_summary(): array {
        $summary = [
            'total'        => 0,
            'healthy'      => 0,
            'degraded'     => 0,
            'error'        => 0,
            'needs_config' => 0,
            'modules'      => [],
        ];

        foreach ($this->modules as $slug => $module) {
            try {
                $status = strtoupper((string) $module->get_health_status());
                if (!in_array($status, ['HEALTHY', 'DEGRADED', 'ERROR', 'NEEDS_CONFIG'], true)) {
                    $status = 'DEGRADED';
                }

                $summary['total']++;
                match ($status) {
                    'HEALTHY'      => $summary['healthy']++,
                    'DEGRADED'     => $summary['degraded']++,
                    'ERROR'        => $summary['error']++,
                    'NEEDS_CONFIG' => $summary['needs_config']++,
                    default        => $summary['degraded']++,
                };

                $summary['modules'][$slug] = [
                    'name'         => $module->get_name(),
                    'version'      => $module->get_version(),
                    'description'  => $module->get_description(),
                    'status'       => $status,
                    'settings_url' => $module->get_settings_url(),
                    'dependencies' => $module->get_dependencies(),
                ];
            } catch (Throwable $e) {
                $summary['total']++;
                $summary['error']++;
                $summary['modules'][$slug] = [
                    'name'        => $slug,
                    'version'     => 'UNKNOWN',
                    'description' => 'Error polling module health status.',
                    'status'      => 'ERROR',
                    'error_msg'   => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Logs an unauthorized or non-conformant registration attempt.
     *
     * @param string $identifier Type or class name of the non-conformant candidate.
     * @return void
     */
    private function log_security_warning(string $identifier): void {
        $warn_msg = sprintf('[WM Suite Hub Security] Candidate "%s" attempted registration without implementing WM_Plugin_Module_Interface.', $identifier);
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( $warn_msg ); }
    }
}
