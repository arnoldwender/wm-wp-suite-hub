<?php
/**
 * Interface WM_Package_Installer_Interface
 *
 * Strict contract defining atomic deployment, Ed25519 signature checks,
 * pre-flight environment validations, and safe filesystem rollbacks.
 *
 * @package ArnoldWender\SuiteHub\SharedKernel\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\SharedKernel\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WM_Package_Installer_Interface {

    /**
     * Executes pre-flight server environment checks before any filesystem modification.
     *
     * @param WM_Bundle_Interface $bundle
     * @return array<string, mixed> Check results with 'status', 'checks', and 'errors'.
     */
    public function pre_flight_check(WM_Bundle_Interface $bundle): array;

    /**
     * Validates cryptographic Ed25519 signature over a downloaded archive.
     *
     * @param string $zip_path
     * @param string $b64_signature
     * @param string $b64_public_key
     * @return bool
     * @throws \RuntimeException If cryptographic parameters are malformed.
     */
    public function verify_signature(string $zip_path, string $b64_signature, string $b64_public_key): bool;

    /**
     * Executes atomic installation of a package archive with quarantine staging and OPcache flush.
     *
     * @param string $plugin_slug
     * @param string $temp_zip_path
     * @return bool
     */
    public function execute_atomic_install(string $plugin_slug, string $temp_zip_path): bool;

    /**
     * Restores previous plugin state from /wp-content/wm-backups/ upon crash or fatal error.
     *
     * @param string $plugin_slug
     * @return bool
     */
    public function trigger_rollback(string $plugin_slug): bool;
}
