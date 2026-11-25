<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WM_Plugin_Module_Interface
 *
 * Strict polymorphic contract for all WM Suite satellite plugins (Spokes).
 * Guarantees predictable inter-plugin discovery, immutable state queries, 
 * CRA/CycloneDX SBOM compilation, and unified health monitoring.
 *
 * @package ArnoldWender\SuiteHub\Contracts
 * @since   1.0.0
 */
interface WM_Plugin_Module_Interface {

    /**
     * Unique identifier for the module (Slug, e.g., 'wm-prototype-nda-gate').
     *
     * @return string
     */
    public function get_module_slug(): string;

    /**
     * Human-readable display name of the module.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Semantic version string of the module (e.g., '1.4.2').
     *
     * @return string
     */
    public function get_version(): string;

    /**
     * Short functional description of the module's compliance/security role.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Current operational and compliance health status.
     *
     * Valid returns:
     * - 'HEALTHY': Normal operation, zero compliance alerts.
     * - 'DEGRADED': Operational with warnings or pending actions.
     * - 'ERROR': Critical failure, interception failure, or breach.
     * - 'NEEDS_CONFIG': Plugin active but mandatory legal/API configuration is missing.
     *
     * @return string
     */
    public function get_health_status(): string;

    /**
     * Provides component metadata for the CycloneDX v1.6+ Software Bill of Materials (SBOM)
     * as mandated by EU Cyber Resilience Act (CRA Art. 14) and BSI TR-03183-2.
     *
     * @return array<string, mixed>
     */
    public function get_sbom_data(): array;

    /**
     * Declares operational inter-dependencies within the WM Suite suite.
     * Example: ['wm-activity-sentinel', 'wm-zero-cdn-asset-localizer']
     *
     * @return array<int, string>
     */
    public function get_dependencies(): array;

    /**
     * URL to the plugin's dedicated administrative or configuration page.
     *
     * @return string|null
     */
    public function get_settings_url(): ?string;

    /**
     * Real-time compliance and telemetry key metrics for the Cockpit Dashboard.
     *
     * @return array<string, mixed>
     */
    public function get_telemetry_metrics(): array;

    /**
     * Executes an administrative or maintenance action requested via Hub / WP-CLI.
     *
     * @param string               $action Name of the action (e.g., 'purge_cache', 'reverify_chain').
     * @param array<string, mixed> $params Optional parameters for execution.
     * @return array<string, mixed> Result payload with 'success' (bool) and 'message' (string).
     */
    public function execute_action(string $action, array $params = []): array;
}
