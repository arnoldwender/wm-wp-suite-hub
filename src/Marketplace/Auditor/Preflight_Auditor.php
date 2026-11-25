<?php
/**
 * Class Preflight_Auditor
 *
 * Checks the server a bundle would be installed on: PHP version, the sodium extension, memory
 * limit, write access to the plugin and backup directories, and OPcache. Installing is not wired
 * in this version, so the check serves the dry run; it changes nothing on disk.
 *
 * @package ArnoldWender\SuiteHub\Marketplace\Auditor
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Marketplace\Auditor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\SharedKernel\Contracts\WM_Bundle_Interface;

final class Preflight_Auditor {

    /**
     * Runs full pre-flight audit for a target bundle or standalone environment.
     *
     * @param WM_Bundle_Interface|null $bundle
     * @return array{passed: bool, checks: array<string, array<string, mixed>>, errors: array<int, string>}
     */
    public static function audit(?WM_Bundle_Interface $bundle = null): array {
        $checks = [];
        $errors = [];
        $passed = true;

        // 1. PHP Version Check
        $req_php    = $bundle !== null ? $bundle->get_required_php_version() : '8.2.0';
        $actual_php = PHP_VERSION;
        $php_ok     = version_compare($actual_php, $req_php, '>=');
        $checks['php_version'] = [
            'name'     => 'PHP Version Runtime',
            'required' => '>=' . $req_php,
            'actual'   => $actual_php,
            'passed'   => $php_ok,
            'message'  => $php_ok ? 'PHP Version entspricht den Anforderungen.' : sprintf('Erfordert PHP %s+, aktuell ist %s.', $req_php, $actual_php),
        ];
        if (!$php_ok) {
            $errors[] = $checks['php_version']['message'];
            $passed   = false;
        }

        // 2. Libsodium Cryptographic Extension Check
        $sodium_ok = extension_loaded('sodium') && function_exists('sodium_crypto_sign_verify_detached');
        $checks['libsodium'] = [
            'name'     => 'Libsodium (Ed25519) Extension',
            'required' => 'Aktiv & Verfügbar',
            'actual'   => $sodium_ok ? 'Aktiv' : 'Nicht geladen',
            'passed'   => $sodium_ok,
            'message'  => $sodium_ok ? 'Libsodium für Ed25519-Signaturprüfung aktiv.' : 'PHP-Erweiterung "sodium" fehlt. Signaturprüfung unmöglich.',
        ];
        if (!$sodium_ok) {
            $errors[] = $checks['libsodium']['message'];
            $passed   = false;
        }

        // 3. Memory Limit Check
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = self::parse_size($memory_limit);
        $min_memory   = 128 * 1024 * 1024; // 128MB
        $memory_ok    = $memory_bytes === -1 || $memory_bytes >= $min_memory;
        $checks['memory_limit'] = [
            'name'     => 'PHP Memory Limit',
            'required' => '>= 128M',
            'actual'   => $memory_limit,
            'passed'   => $memory_ok,
            'message'  => $memory_ok ? 'Speicherlimit ausreichend.' : sprintf('Speicherlimit zu niedrig (%s). Empfohlen: 256M.', $memory_limit),
        ];
        if (!$memory_ok) {
            $errors[] = $checks['memory_limit']['message'];
            $passed   = false;
        }

        // 4. FileSystem Write Permissions Check
        $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : sys_get_temp_dir();
        $backup_dir  = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : sys_get_temp_dir()) . '/wm-backups';
        
        // wp_is_writable() is Windows-safe. A backup directory that does not exist yet is judged by
        // its parent: until 1.2.2 this check created wp-content/wm-backups on every dry run.
        $fs_writable     = wp_is_writable($plugins_dir);
        $backup_writable = file_exists($backup_dir) ? wp_is_writable($backup_dir) : wp_is_writable(dirname($backup_dir));
        $write_ok        = $fs_writable && $backup_writable;

        $checks['filesystem_writable'] = [
            'name'     => 'Dateisystem-Schreibrechte',
            'required' => 'Schreibbar für Plugins & wm-backups',
            'actual'   => $write_ok ? 'Schreibbar' : 'Eingeschränkt',
            'passed'   => $write_ok,
            'message'  => $write_ok ? 'Plugin- und Backup-Verzeichnis sind schreibbar.' : 'Keine Schreibrechte im Plugin- oder Backup-Verzeichnis.',
        ];
        if (!$write_ok) {
            $errors[] = $checks['filesystem_writable']['message'];
            $passed   = false;
        }

        // 5. OPcache Invalidation Support
        $opcache_enabled = function_exists('opcache_invalidate') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
        $checks['opcache_support'] = [
            'name'     => 'OPcache Invalidation Support',
            'required' => 'Funktional oder Deaktiviert',
            'actual'   => function_exists('opcache_invalidate') ? 'Verfügbar' : 'Nicht verfügbar',
            'passed'   => true, // Non-fatal advisory check
            'message'  => function_exists('opcache_invalidate') ? 'Bytecode-Cache kann nach Installation invalidiert werden.' : 'opcache_invalidate() steht nicht zur Verfügung.',
        ];

        return [
            'passed' => $passed,
            'checks' => $checks,
            'errors' => $errors,
        ];
    }

    /**
     * Converts human PHP ini sizes (e.g. '256M', '1G') to integer bytes.
     *
     * @param string|int $size
     * @return int
     */
    public static function parse_size($size): int {
        $size = trim((string) $size);
        if ($size === '-1') {
            return -1;
        }
        $unit = strtolower(substr($size, -1));
        $val  = (int) $size;
        switch ($unit) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
}
