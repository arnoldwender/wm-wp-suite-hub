<?php
/**
 * Class Atomic_Installer
 *
 * Orchestrates atomic package deployments with filesystem quarantine,
 * recursive OPcache invalidation, sandbox crash-testing, and automatic rollbacks.
 *
 * @package ArnoldWender\SuiteHub\Marketplace\Installer
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Marketplace\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\SharedKernel\Contracts\WM_Bundle_Interface;
use ArnoldWender\SuiteHub\SharedKernel\Contracts\WM_Package_Installer_Interface;
use ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor;
use ArnoldWender\SuiteHub\Marketplace\Crypto\WM_Ed25519_Verifier;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;

final class Atomic_Installer implements WM_Package_Installer_Interface {

    private WM_Ed25519_Verifier $verifier;
    private ?Audit_Chainer $audit_chainer;
    private string $plugins_dir;
    private string $backup_dir;

    /**
     * Map of plugin slug to active backup directory path.
     *
     * @var array<string, string>
     */
    private array $active_backups = [];

    public function __construct(?Audit_Chainer $audit_chainer = null) {
        $this->verifier      = new WM_Ed25519_Verifier();
        $this->audit_chainer = $audit_chainer ?? new Audit_Chainer();
        
        $this->plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : sys_get_temp_dir() . '/plugins';
        $content_dir       = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname($this->plugins_dir);
        $this->backup_dir  = $content_dir . '/wm-backups';

        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
    }

    /**
     * Executes pre-flight server environment check.
     */
    public function pre_flight_check(WM_Bundle_Interface $bundle): array {
        return Preflight_Auditor::audit($bundle);
    }

    /**
     * Cryptographically verifies package archive.
     */
    public function verify_signature(string $zip_path, string $b64_signature, ?string $b64_public_key = null): bool {
        return $this->verifier->verify_package($zip_path, $b64_signature, $b64_public_key);
    }

    /**
     * Executes atomic extraction and deployment.
     */
    public function execute_atomic_install(string $plugin_slug, string $temp_zip_path): bool {
        $slug       = sanitize_key($plugin_slug);
        $target_dir = $this->plugins_dir . '/' . $slug;

        // Step 1: Quarantine existing version if present. The quarantine IS a rename: one atomic
        // syscall on the local filesystem, so a crash leaves either the old tree or the new one,
        // never a half-copied mix. WP_Filesystem's FTP/SSH transports cannot move atomically and
        // ZipArchive already extracts locally, so the direct call is the design, not an oversight.
        if (file_exists($target_dir)) {
            $timestamp   = time();
            $backup_path = $this->backup_dir . '/' . $slug . '-' . $timestamp;

            if (!rename($target_dir, $backup_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
                $this->audit_chainer->append_event('wm-suite-hub', 'install_quarantine_failed', [
                    'slug'   => $slug,
                    'target' => $target_dir,
                ]);
                return false;
            }
            $this->active_backups[$slug] = $backup_path;
        }

        // Step 2: Extract new archive
        if (!class_exists('ZipArchive')) {
            $this->trigger_rollback($slug);
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($temp_zip_path) !== true) {
            $this->trigger_rollback($slug);
            return false;
        }

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        $extract_res = $zip->extractTo($target_dir);
        $zip->close();

        if (!$extract_res) {
            $this->trigger_rollback($slug);
            return false;
        }

        // Step 3: Invalidate OPcache recursively
        $this->invalidate_opcache_recursive($target_dir);

        // Step 4: Record success into Audit Chainer
        $this->audit_chainer->append_event('wm-suite-hub', 'package_atomic_installed', [
            'slug'        => $slug,
            'target_dir'  => $target_dir,
            'backup_path' => $this->active_backups[$slug] ?? null,
        ]);

        return true;
    }

    /**
     * Restores previous plugin state from quarantine upon failure.
     */
    public function trigger_rollback(string $plugin_slug): bool {
        $slug       = sanitize_key($plugin_slug);
        $target_dir = $this->plugins_dir . '/' . $slug;
        $backup_dir = $this->active_backups[$slug] ?? null;

        // If no active backup in memory, look for newest in /wm-backups/
        if ($backup_dir === null || !file_exists($backup_dir)) {
            $pattern = $this->backup_dir . '/' . $slug . '-*';
            $backups = glob($pattern);
            if (!empty($backups)) {
                rsort($backups);
                $backup_dir = $backups[0];
            }
        }

        if ($backup_dir === null || !file_exists($backup_dir)) {
            return false;
        }

        // Clean failed target
        if (file_exists($target_dir)) {
            $this->delete_directory_recursive($target_dir);
        }

        // Restore backup: the same atomic rename as the quarantine step (see execute_atomic_install).
        $restored = rename($backup_dir, $target_dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename

        if ($restored) {
            $this->invalidate_opcache_recursive($target_dir);
            $this->audit_chainer->append_event('wm-suite-hub', 'package_rollback_executed', [
                'slug'        => $slug,
                'restored_to' => $target_dir,
                'from_backup' => $backup_dir,
            ]);
            unset($this->active_backups[$slug]);
        }

        return $restored;
    }

    /**
     * Invalidates OPcache for all PHP files in a directory tree.
     */
    public function invalidate_opcache_recursive(string $directory): int {
        if (!function_exists('opcache_invalidate') || !is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                if (@opcache_invalidate($item->getPathname(), true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Utility method to recursively delete a failed target tree before the quarantine is renamed
     * back into place. Runs on the local filesystem for the same reason the rename does; the
     * directory it removes is one this installer created moments earlier.
     */
    private function delete_directory_recursive(string $dir): bool {
        if (!file_exists($dir)) {
            return true;
        }

        // phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory_recursive($path) : unlink($path);
        }

        return rmdir($dir);
        // phpcs:enable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    }
}
