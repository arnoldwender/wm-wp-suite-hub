<?php
declare(strict_types=1);

/**
 * WM Suite Hub — Standalone Test Suite
 *
 * Runs comprehensive unit and integration verification tests across all core components:
 * - Module_Registry (Polymorphic registration & error boundary isolation)
 * - Audit_Chainer (HMAC-SHA256 Merkle chain verification & tampering detection)
 * - Health_Matrix (Compliance scoring & caching invariants)
 * - SBOM_Generator (CycloneDX v1.6 schema validation)
 *
 * @package ArnoldWender\SuiteHub\Tests
 */

// Mock WordPress functions if running outside full WP bootstrap
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('WM_SUITE_HUB_VERSION')) {
    define('WM_SUITE_HUB_VERSION', '1.2.6');
}
if (!defined('WM_SUITE_HUB_PATH')) {
    define('WM_SUITE_HUB_PATH', dirname(__DIR__) . '/');
}
if (!defined('WM_SUITE_HUB_URL')) {
    define('WM_SUITE_HUB_URL', 'http://localhost:8080/wp-content/plugins/wm-suite-hub/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!class_exists('WP_CLI_Command')) {
    class WP_CLI_Command {}
}

if (!class_exists('WP_CLI')) {
    // Records what a command prints, so a test can tell a command that fails from one that reports
    // success (the real error() exits; this one returns and the command's own `return` follows).
    class WP_CLI {
        /** @var array<int, array{0: string, 1: string}> */
        public static array $log = [];
        public static function line(string $msg): void { self::$log[] = ['line', $msg]; }
        public static function log(string $msg): void { self::$log[] = ['log', $msg]; }
        public static function success(string $msg): void { self::$log[] = ['success', $msg]; }
        public static function error(string $msg): void { self::$log[] = ['error', $msg]; }
        public static function colorize(string $msg): string { return $msg; }
    }
}

// Global mocks
global $mock_filters, $mock_actions, $mock_transients, $mock_db_rows;
$mock_filters    = [];
$mock_actions    = [];
$mock_transients = [];
$mock_db_rows    = [];

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        global $mock_filters;
        if (isset($mock_filters[$tag])) {
            foreach ($mock_filters[$tag] as $callback) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        global $mock_actions;
        $mock_actions[$tag][] = $callback;
    }
}

if (!function_exists('has_action')) {
    function has_action(string $tag, $callback = false): bool {
        global $mock_actions;
        return !empty($mock_actions[$tag]);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void {
        global $mock_actions;
        if (isset($mock_actions[$tag])) {
            foreach ($mock_actions[$tag] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return (bool) ($GLOBALS['mock_is_admin'] ?? false);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10): void {
        global $mock_filters;
        $mock_filters[$tag][] = $callback;
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $tag, $callback = false): bool {
        global $mock_filters;
        return !empty($mock_filters[$tag]);
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string {
        return 'test-salt-secret-key-1234567890abcdef';
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type): string {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return trim(strip_tags($str));
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses')) {
    // Standalone stand-in for wp_kses(): keeps only the ELEMENTS the allowlist names, which is what
    // the block templates need in order to render here. It does not filter attributes — the real
    // function does, and that half is measured where WordPress runs: tools/svg-kses-dom-probe.py
    // and the local WordPress install check that all 18 icons survive wp_kses() with wm_suite_hub_icon_allowed_html().
    function wp_kses(string $content, array $allowed_html): string {
        $tags = implode('', array_map(static fn(string $tag): string => '<' . $tag . '>', array_keys($allowed_html)));
        return strip_tags($content, $tags);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $content): string {
        return strip_tags($content, '<div><span><p><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><section><article><button><svg><path><circle><rect><line><polyline>');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $text, string $domain = 'default'): void {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

// Filesystem helpers the pre-flight auditor and the installer use since 2026-09-14 (Plugin Check
// refuses raw mkdir/is_writable); the doubles do what WordPress core does on a direct filesystem.
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool {
        return is_dir($target) || @mkdir($target, 0775, true);
    }
}

if (!function_exists('wp_is_writable')) {
    function wp_is_writable(string $path): bool {
        return is_writable($path);
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'http://localhost:8080' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string {
        return 'http://localhost:8080/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value = false, $url = ''): string {
        if (is_array($key)) {
            $params = $key;
            $target = $value ?: '';
        } else {
            $params = [$key => $value];
            $target = $url ?: '';
        }
        $query = http_build_query($params);
        return $target . (str_contains($target, '?') ? '&' : '?') . $query;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0): string {
        return json_encode($data, $options);
    }
}



if (!function_exists('get_transient')) {
    function get_transient(string $transient) {
        global $mock_transients;
        return $mock_transients[$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool {
        global $mock_transients;
        $mock_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool {
        global $mock_transients;
        unset($mock_transients[$transient]);
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string {
        return $show === 'version' ? '6.7.1' : 'WM Suite Test Site';
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool { return true; }
}

if (!function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache(): bool { return false; }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'http://localhost:8080/wp-content/plugins/wm-suite-hub/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void {}
}

global $mock_options, $mock_option_autoloads, $mock_user_meta, $mock_switched_blogs, $mock_restored_blogs, $mock_dbdelta_sql, $mock_roles_added, $mock_blog_stack;
$mock_options          = [];
$mock_option_autoloads = [];
$mock_user_meta        = [];
$mock_switched_blogs   = [];
$mock_restored_blogs   = 0;
$mock_dbdelta_sql      = [];
$mock_roles_added      = [];
$mock_blog_stack       = [];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        global $mock_options;
        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, $value = '', string $deprecated = '', $autoload = null): bool {
        global $mock_options, $mock_option_autoloads;
        if (array_key_exists($option, $mock_options)) {
            return false;
        }
        $mock_options[$option]          = $value;
        $mock_option_autoloads[$option] = $autoload;
        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool {
        global $mock_options, $mock_option_autoloads;
        $mock_options[$option]          = $value;
        $mock_option_autoloads[$option] = $autoload;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        global $mock_options, $mock_option_autoloads;
        unset($mock_options[$option], $mock_option_autoloads[$option]);
        return true;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('is_plugin_active_for_network')) {
    function is_plugin_active_for_network(string $plugin): bool {
        return (bool) ($GLOBALS['mock_network_active'] ?? false);
    }
}

if (!function_exists('switch_to_blog')) {
    function switch_to_blog(int $new_blog_id): bool {
        global $wpdb, $mock_switched_blogs, $mock_blog_stack;
        $mock_blog_stack[]     = $wpdb->prefix;
        $mock_switched_blogs[] = $new_blog_id;
        $wpdb->prefix          = 'wp_' . $new_blog_id . '_';
        return true;
    }
}

if (!function_exists('restore_current_blog')) {
    function restore_current_blog(): bool {
        global $wpdb, $mock_restored_blogs, $mock_blog_stack;
        $mock_restored_blogs++;
        if ($mock_blog_stack !== []) {
            $wpdb->prefix = (string) array_pop($mock_blog_stack);
        } else {
            $wpdb->prefix = 'wp_';
        }
        return true;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($queries = '', bool $execute = true): array {
        global $mock_dbdelta_sql;
        $mock_dbdelta_sql[] = is_array($queries) ? implode("\n", $queries) : (string) $queries;
        return [];
    }
}

if (!function_exists('add_role')) {
    function add_role(string $role, string $display_name, array $capabilities = []) {
        global $mock_roles_added;
        $mock_roles_added[$role] = $capabilities;
        return null;
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles() {
        return $GLOBALS['mock_wp_roles'] ?? null;
    }
}

if (!function_exists('remove_role')) {
    function remove_role(string $role): void {}
}

if (!function_exists('delete_metadata')) {
    function delete_metadata(string $meta_type, int $object_id, string $meta_key, $meta_value = '', bool $delete_all = false): bool {
        return true;
    }
}

global $mock_registered_scripts, $mock_script_translations, $mock_registered_blocks_meta, $mock_block_bindings;
$mock_registered_scripts     = [];
$mock_script_translations    = [];
$mock_registered_blocks_meta = [];
$mock_block_bindings         = [];

if (!function_exists('wp_register_script')) {
    function wp_register_script(string $handle, string $src, array $deps = [], $ver = false, $in_footer = false): bool {
        global $mock_registered_scripts;
        $mock_registered_scripts[$handle] = [
            'src'       => $src,
            'deps'      => $deps,
            'ver'       => $ver,
            'in_footer' => $in_footer,
        ];
        return true;
    }
}

if (!function_exists('wp_set_script_translations')) {
    function wp_set_script_translations(string $handle, string $domain = 'default', string $path = ''): bool {
        global $mock_script_translations;
        $mock_script_translations[$handle] = ['domain' => $domain, 'path' => $path];
        return true;
    }
}

if (!function_exists('register_block_type_from_metadata')) {
    function register_block_type_from_metadata(string $file_or_folder, array $args = []) {
        global $mock_registered_blocks_meta;
        $mock_registered_blocks_meta[$file_or_folder] = $args;
        return true;
    }
}

if (!function_exists('register_block_bindings_source')) {
    function register_block_bindings_source(string $source_name, array $source_properties): bool {
        global $mock_block_bindings;
        $mock_block_bindings[$source_name] = $source_properties;
        return true;
    }
}

if (!class_exists('GenerateBlocks_Register_Dynamic_Tag')) {
    class GenerateBlocks_Register_Dynamic_Tag {
        public static array $registered = [];
        public function __construct(array $args) {
            $tag = (string) ($args['tag'] ?? '');
            if ($tag !== '') {
                self::$registered[$tag] = $args;
            }
        }
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 1;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false) {
        global $mock_user_meta;
        return $mock_user_meta[$user_id][$key] ?? ($single ? '' : []);
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $key, $value): bool {
        global $mock_user_meta;
        $mock_user_meta[$user_id][$key] = $value;
        return true;
    }
}

// Mock wpdb
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public array $rows = [];
        private int $auto_inc = 1;

        public function insert(string $table, array $data, array $format = []): int {
            $data['id'] = $this->auto_inc++;
            $this->rows[] = $data;
            return 1;
        }

        // Enough of wpdb::prepare() for the hub's queries: %d and %s in order, strings quoted.
        public function prepare(string $query, ...$args): string {
            $args = (count($args) === 1 && is_array($args[0])) ? $args[0] : $args;
            $i = 0;
            return (string) preg_replace_callback('/%[ds]/', static function (array $m) use ($args, &$i): string {
                $value = $args[$i++] ?? '';
                return $m[0] === '%d' ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
            }, $query);
        }

        public array $results_queries = [];

        public function get_results(string $query, string $output = 'OBJECT'): array {
            $this->results_queries[] = $query;
            // Keyset page of the chain verification: WHERE id > N ORDER BY id ASC LIMIT M.
            if (preg_match('/WHERE id > (\d+) ORDER BY id ASC LIMIT (\d+)/', $query, $m)) {
                $after = (int) $m[1];
                $page  = array_values(array_filter($this->rows, static fn(array $r): bool => (int) $r['id'] > $after));
                usort($page, static fn(array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
                return array_slice($page, 0, (int) $m[2]);
            }
            return $this->rows;
        }

        public function get_var(string $query): ?string {
            if (empty($this->rows)) return null;
            $last = end($this->rows);
            return $last['current_hash'] ?? null;
        }

        public array $queries = [];

        public function query(string $query): int {
            $this->queries[] = $query;
            return 1;
        }
    }
}

global $wpdb;
$wpdb = new wpdb();

// Autoload plugin classes
spl_autoload_register(function (string $class): void {
    $prefix = 'ArnoldWender\\SuiteHub\\';
    $base_dir = dirname(__DIR__) . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    $sdk_prefix = 'ArnoldWender\\ComplianceSDK\\';
    $sdk_base_dir = dirname(__DIR__) . '/packages/compliance-sdk/src/';
    $sdk_len = strlen($sdk_prefix);
    if (strncmp($sdk_prefix, $class, $sdk_len) === 0) {
        $relative_class = substr($class, $sdk_len);
        $file = $sdk_base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/../src/Contracts/WM_Plugin_Module_Interface.php';
require_once __DIR__ . '/../src/Core/Module_Registry.php';
require_once __DIR__ . '/../src/Telemetry/Audit_Chainer.php';
require_once __DIR__ . '/../src/Telemetry/Health_Matrix.php';
require_once __DIR__ . '/../src/Compliance/SBOM_Generator.php';
require_once __DIR__ . '/../src/Adapters/Sample_Spoke_Adapter.php';

use ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface;
use ArnoldWender\SuiteHub\Core\Module_Registry;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use ArnoldWender\SuiteHub\Telemetry\Health_Matrix;
use ArnoldWender\SuiteHub\Compliance\SBOM_Generator;
use ArnoldWender\SuiteHub\Adapters\Sample_Spoke_Adapter;

// Test Execution Harness
$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "[\033[32mPASS\033[0m] {$name}\n";
        $passed++;
    } else {
        echo "[\033[31mFAIL\033[0m] {$name}: {$details}\n";
        $failed++;
    }
}

echo "\n============================================================\n";
echo " WM SUITE SUITE HUB — UNIT & INTEGRATION TEST SUITE\n";
echo "============================================================\n\n";

// TEST 1: Service Discovery & Module Registration
add_filter('wm_register_suite_module', function(array $modules): array {
    $modules[] = new Sample_Spoke_Adapter(
        'wm-prototype-nda-gate',
        'WM Prototype NDA Gate',
        '1.2.0',
        'Click-wrap NDA compliance',
        'HEALTHY'
    );
    $modules[] = new Sample_Spoke_Adapter(
        'wm-zero-cdn-asset-localizer',
        'WM Zero-CDN Localizer',
        '1.0.4',
        'Local font & asset manager',
        'HEALTHY'
    );
    // Invalid object to test error boundary
    $modules[] = new stdClass();
    return $modules;
});

$registry = new Module_Registry();

assert_test(
    'Module_Registry loads valid spokes and rejects invalid candidates',
    $registry->count() === 2 && $registry->has_module('wm-prototype-nda-gate') && $registry->has_module('wm-zero-cdn-asset-localizer'),
    'Expected 2 valid modules'
);

assert_test(
    'Module_Registry correctly queries module metadata',
    $registry->get_module('wm-prototype-nda-gate')->get_version() === '1.2.0',
    'Version mismatch'
);

// TEST 2: Health Matrix Scoring
$matrix = Health_Matrix::get_matrix($registry, true);
assert_test(
    'Health_Matrix computes 100% score for healthy modules',
    $matrix['score'] === 100 && $matrix['global_status'] === 'HEALTHY',
    'Expected score 100'
);

// TEST 2b (2026-11-21): a hub with no registered module is not healthy. Until 1.2.2 the divisor was
// max(1, total) and went into counts['total'] as well, so an empty hub reported "1 registered", score 0
// and HEALTHY in the cockpit and the dashboard widget.
$saved_module_filters_hm = [$mock_filters['wm_register_suite_module'] ?? [], $mock_filters['wm_suite_hub_registered_modules'] ?? []];
$mock_filters['wm_register_suite_module']        = [];
$mock_filters['wm_suite_hub_registered_modules'] = [];
$empty_registry = new Module_Registry();
[$mock_filters['wm_register_suite_module'], $mock_filters['wm_suite_hub_registered_modules']] = $saved_module_filters_hm;
$empty_matrix = Health_Matrix::get_matrix($empty_registry, true);
Health_Matrix::flush_cache();
assert_test(
    'Health_Matrix reports an empty registry as 0 modules, score 0 and WARNING, never as one healthy module',
    $empty_registry->count() === 0
        && $empty_matrix['counts']['total'] === 0
        && $empty_matrix['score'] === 0
        && $empty_matrix['global_status'] === 'WARNING',
    sprintf('total=%d score=%d status=%s', $empty_matrix['counts']['total'], $empty_matrix['score'], $empty_matrix['global_status'])
);

// TEST 3: Cryptographic Audit Chainer
$chainer = new Audit_Chainer();
$hash1 = $chainer->append_event('wm-prototype-nda-gate', 'nda_signed', ['user' => 1, 'term_v' => '1.0']);
$hash2 = $chainer->append_event('wm-zero-cdn-asset-localizer', 'cdn_blocked', ['host' => 'fonts.googleapis.com']);

assert_test(
    'Audit_Chainer seals events with valid 64-char SHA-256 HMAC',
    strlen($hash1) === 64 && strlen($hash2) === 64 && $hash1 !== $hash2,
    'Hash generation failed'
);

$verification = $chainer->verify_chain_integrity();
assert_test(
    'Audit_Chainer verifies unbroken chain integrity',
    $verification['intact'] === true && $verification['total_checked'] === 2,
    $verification['details']
);

// Tamper simulation
$wpdb->rows[1]['payload'] = '{"tampered":true}';
$tamper_verification = $chainer->verify_chain_integrity();
assert_test(
    'Audit_Chainer successfully detects database tampering',
    $tamper_verification['intact'] === false && $tamper_verification['broken_at_id'] === 2,
    'Failed to detect tamper'
);
// Restore row
$wpdb->rows[1]['payload'] = json_encode(['host' => 'fonts.googleapis.com']);

// TEST 3b (2026-09-17): the chain is verified with hash_equals(), not with !==.
// This is a SOURCE check on purpose — hash_equals() and !== return the same booleans, so no
// behavioural assertion can tell them apart. It exists because the CHANGELOG has claimed
// "Enforced hash_equals() verification on all HMAC-SHA256 signature chains" since the 1.2.0 entry
// while the file carried neither call; the claim is now true and cannot silently stop being true.
$chainer_src = (string) file_get_contents(dirname(__DIR__) . '/src/Telemetry/Audit_Chainer.php');
// The window is the body of verify_chain_integrity() and nothing else: bounded at the next method,
// so a hash_equals() somewhere further down the file cannot satisfy this check on its behalf.
$verify_body = (string) strstr($chainer_src, 'public function verify_chain_integrity');
$verify_body = (string) substr($verify_body, 0, (int) strpos($verify_body, 'public function get_recent_logs'));
// Counts CALLS ('!hash_equals(' — both comparisons are negated), never the prose: the method
// carries a comment naming hash_equals(), and counting that too is what this check first did.
$verify_calls = substr_count($verify_body, '!hash_equals(');
assert_test(
    'Audit_Chainer compares both digests with hash_equals(), never with !==',
    $verify_calls === 2
        && strpos($verify_body, '!== $row_curr_hash') === false
        && strpos($verify_body, '!== $row_prev_hash') === false,
    'verify_chain_integrity() compares digests with a plain operator: ' . $verify_calls . ' hash_equals() call(s) found, 2 expected'
);

// TEST 4: CycloneDX v1.6+ SBOM Generation
$sbom = SBOM_Generator::generate_cyclonedx_array($registry);
assert_test(
    'SBOM_Generator outputs valid CycloneDX v1.6 structure',
    $sbom['bomFormat'] === 'CycloneDX' && $sbom['specVersion'] === '1.6' && count($sbom['components']) === 3,
    'CycloneDX structure invalid'
);

// TEST 4b: the SBOM states a licence it can stand behind, or none at all.
// Until 2026-09-19 this generator hard-coded ['id' => 'Proprietary'] for the hub AND as the default
// for every spoke, while the plugin header, readme.txt, composer.json, the README badge and LICENSE
// of all 24 plugins say GPLv2 or later — and the endpoint that serves this document needs no
// authentication. A compliance SBOM is the one artefact whose job is to state the licence.
// CycloneDX also allows `id` XOR `name`, and `Proprietary` is not an SPDX identifier.
$sbom_licence_fields = [];
$sbom_id_and_name    = 0;
foreach ($sbom['components'] as $sbom_component) {
    foreach ($sbom_component['licenses'] ?? [] as $sbom_entry) {
        $sbom_licence_fields[] = ($sbom_entry['license']['id'] ?? '') . ' ' . ($sbom_entry['license']['name'] ?? '');
        if (isset($sbom_entry['license']['id'], $sbom_entry['license']['name'])) {
            $sbom_id_and_name++;
        }
    }
}
$sbom_licences_flat = implode(' | ', $sbom_licence_fields);
assert_test(
    'the SBOM declares no proprietary licence and never id together with name',
    !str_contains($sbom_licences_flat, 'Proprietary') && $sbom_id_and_name === 0,
    'licences declared: ' . ($sbom_licences_flat === '' ? '(none)' : $sbom_licences_flat)
        . '; entries with id and name at once: ' . $sbom_id_and_name
);

// The assertion above only sees the licences the registered spokes DECLARE, and both sample
// adapters declare one — so the generator's own default for a spoke that declares nothing is never
// exercised by it. That default was `Proprietary` and is the case this fix exists for, so it is
// checked where it lives, the same way Audit_Chainer's comparison operator is checked above.
$sbom_generator_src = (string) file_get_contents(__DIR__ . '/../src/Compliance/SBOM_Generator.php');
$sbom_generator_code = implode("\n", array_filter(
    explode("\n", $sbom_generator_src),
    static fn(string $line): bool => !str_starts_with(ltrim($line), '//') && !str_starts_with(ltrim($line), '*')
));
assert_test(
    'SBOM_Generator hard-codes no licence: it reads its own header and omits what it cannot know',
    !str_contains($sbom_generator_code, "'Proprietary'")
        && str_contains($sbom_generator_code, 'own_licenses()'),
    'a literal licence is still hard-coded in SBOM_Generator.php'
);

// TEST 4c (2026-11-21): the SBOM names the tool that wrote it. Until 1.2.2 metadata.tools named
// "WM Suite SBOM Beacon", another plugin of the fleet that took no part in it.
$sbom_tool = $sbom['metadata']['tools']['components'][0] ?? [];
assert_test(
    'the SBOM names wm-suite-hub, at the running version, as the tool that generated it',
    ($sbom_tool['name'] ?? '') === 'wm-suite-hub' && ($sbom_tool['version'] ?? '') === WM_SUITE_HUB_VERSION,
    'tool: ' . json_encode($sbom_tool)
);

// TEST 5: Module Action Execution
$nda_module = $registry->get_module('wm-prototype-nda-gate');
$action_res = $nda_module->execute_action('ping');
assert_test(
    'Spoke Adapter execute_action succeeds on self-test',
    $action_res['success'] === true && str_contains($action_res['message'], 'Pong'),
    'Self-test failed'
);

// Mock WP_Styles & WP_Scripts for Deduplication Test
// `registered` holds handle => object with ->src, as in WordPress; the deduplicator reads a dropped
// file's size from it (TEST 31).
if (!class_exists('WP_Styles')) {
    class WP_Styles {
        public array $queue = [];
        public array $registered = [];
    }
}
if (!class_exists('WP_Scripts')) {
    class WP_Scripts {
        public array $queue = [];
        public array $registered = [];
    }
}

if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(string $handle): void {
        global $wp_styles;
        $key = array_search($handle, $wp_styles->queue, true);
        if ($key !== false) {
            unset($wp_styles->queue[$key]);
        }
    }
}
if (!function_exists('wp_deregister_style')) {
    function wp_deregister_style(string $handle): void {}
}
if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(string $handle): void {
        global $wp_scripts;
        $key = array_search($handle, $wp_scripts->queue, true);
        if ($key !== false) {
            unset($wp_scripts->queue[$key]);
        }
    }
}
if (!function_exists('wp_style_is')) {
    function wp_style_is(string $handle, string $status = 'enqueued'): bool {
        global $wp_styles;
        return in_array($handle, $wp_styles->queue ?? [], true);
    }
}
if (!function_exists('wp_script_is')) {
    function wp_script_is(string $handle, string $status = 'enqueued'): bool {
        global $wp_scripts;
        return in_array($handle, $wp_scripts->queue ?? [], true);
    }
}
if (!function_exists('wp_deregister_script')) {
    function wp_deregister_script(string $handle): void {}
}
// TEST 6: Multi-Theme Engine & Defaults
require_once __DIR__ . '/../wm-suite-hub.php';
$default_theme = function_exists('wm_get_suite_theme') ? wm_get_suite_theme() : '';
assert_test(
    'Theme Engine defaults to cyber-events (CyberEvents Enterprise)',
    $default_theme === 'cyber-events',
    "Expected 'cyber-events', got '{$default_theme}'"
);

// Test filter override
add_filter('wm_suite_active_theme', fn() => 'wp-native');
$filtered_theme = wm_get_suite_theme();
assert_test(
    'Theme Engine respects wm_suite_active_theme filter',
    $filtered_theme === 'wp-native',
    "Expected 'wp-native', got '{$filtered_theme}'"
);

// TEST 7: Shared Kernel Bundle Definitions & Manifest Parser (Sprint 1)
require_once __DIR__ . '/../src/SharedKernel/Contracts/WM_Bundle_Interface.php';
require_once __DIR__ . '/../src/SharedKernel/Contracts/WM_Package_Installer_Interface.php';
require_once __DIR__ . '/../src/SharedKernel/Bundles/Bundle_Definition.php';
require_once __DIR__ . '/../src/SharedKernel/Bundles/Manifest_Parser.php';
require_once __DIR__ . '/../src/SharedKernel/Deduplication/Asset_Deduplicator.php';

use ArnoldWender\SuiteHub\SharedKernel\Bundles\Bundle_Definition;
use ArnoldWender\SuiteHub\SharedKernel\Bundles\Manifest_Parser;
use ArnoldWender\SuiteHub\SharedKernel\Deduplication\Asset_Deduplicator;

$sample_json = json_encode([
    'bundles' => [
        [
            'bundle_id'       => 'security-legal',
            'suite_name'      => 'Security & Legal Defense Suite',
            'description'     => 'Enterprise Legal & Cybersecurity Suite',
            'required_php'    => '8.2.0',
            'compliance_tags' => ['CRA', 'BFSG_2025'],
            'packages'        => [
                [
                    'slug'               => 'wm-prototype-nda-gate',
                    'name'               => 'WM Prototype NDA Gate',
                    'version_constraint' => '>=1.2.0',
                    'dependencies'       => [],
                ],
                [
                    'slug'               => 'wm-activity-sentinel',
                    'name'               => 'WM Activity Sentinel',
                    'version_constraint' => '>=1.0.0',
                    'dependencies'       => ['wm-prototype-nda-gate'],
                ]
            ]
        ]
    ]
]);

$parsed_bundles = Manifest_Parser::parse_json($sample_json);
assert_test(
    'Manifest_Parser successfully instantiates Bundle_Definition',
    isset($parsed_bundles['security-legal']) && $parsed_bundles['security-legal']->get_suite_name() === 'Security & Legal Defense Suite',
    'Bundle parsing failed'
);

$dep_graph = $parsed_bundles['security-legal']->get_dependency_graph();
assert_test(
    'Bundle_Definition extracts correct dependency graph',
    isset($dep_graph['wm-activity-sentinel']) && $dep_graph['wm-activity-sentinel'] === ['wm-prototype-nda-gate'],
    'Dependency graph extraction failed'
);

// TEST 7b (2026-11-21): a pack says whether it has a dependency at all. No plugin of the fleet declares
// one, so the bundled packs have none and the view says the order is free instead of presenting the
// listed order as a computed install sequence.
$edgeless_bundles = Manifest_Parser::parse_json((string) json_encode(['bundles' => [[
    'bundle_id' => 'edgeless',
    'packages'  => [['slug' => 'wm-a'], ['slug' => 'wm-b', 'dependencies' => []]],
]]]));
assert_test(
    'Bundle_Definition::has_dependencies() is true with one declared dependency and false without any',
    $parsed_bundles['security-legal']->has_dependencies() === true
        && isset($edgeless_bundles['edgeless'])
        && $edgeless_bundles['edgeless']->has_dependencies() === false
        && !array_key_exists('bfsg_compliant', $parsed_bundles['security-legal']->to_array()),
    'has_dependencies() or the dropped bfsg_compliant flag misbehaves'
);

// TEST 8: Zero-Duplication Asset Engine (Sprint 1)
global $wp_styles, $wp_scripts;
$wp_styles = new WP_Styles();
$wp_styles->queue = ['wm-tokens-shared', 'custom-theme-style', 'wm-obsidian-glass'];

$wp_scripts = new WP_Scripts();
$wp_scripts->queue = ['wm-vanilla-toast', 'app-main-js', 'wm-suite-hub-cockpit'];

$deduplicator = Asset_Deduplicator::get_instance();
$deduplicator->deduplicate_assets();
$telemetry = $deduplicator->get_telemetry();

assert_test(
    'Asset_Deduplicator drops the duplicate token sheet when its canonical is enqueued',
    !in_array('wm-tokens-shared', $wp_styles->queue, true) && in_array('wm-obsidian-glass', $wp_styles->queue, true),
    'queue: ' . implode(',', $wp_styles->queue)
);

// TEST 8b (2026-09-14): a legacy per-plugin copy of the admin sheet is dropped ONLY when the
// family sheet is on the page. Measured on wm-a11y-sensory-governor under the hub: dropping the
// copy without the canonical left the dashboard with no stylesheet at all.
$wp_styles->queue = ['wm-sensory-admin-obsidian', 'custom-theme-style'];
$deduplicator->deduplicate_assets();
assert_test(
    'Asset_Deduplicator keeps the legacy admin-obsidian copy when wm-admin-ui is not enqueued',
    in_array('wm-sensory-admin-obsidian', $wp_styles->queue, true),
    'queue: ' . implode(',', $wp_styles->queue)
);
$wp_styles->queue = ['wm-admin-tokens', 'wm-admin-ui', 'wm-sensory-admin-obsidian', 'custom-theme-style'];
$deduplicator->deduplicate_assets();
assert_test(
    'Asset_Deduplicator drops the legacy admin-obsidian copy once wm-admin-ui is enqueued',
    !in_array('wm-sensory-admin-obsidian', $wp_styles->queue, true) && in_array('wm-admin-ui', $wp_styles->queue, true) && in_array('custom-theme-style', $wp_styles->queue, true),
    'queue: ' . implode(',', $wp_styles->queue)
);

// TEST 9: DAG Topological Sort & Cycle Detection (Sprint 2)
require_once __DIR__ . '/../src/Marketplace/DAG/DAG_Resolver.php';
require_once __DIR__ . '/../src/Marketplace/Auditor/Preflight_Auditor.php';

use ArnoldWender\SuiteHub\Marketplace\DAG\DAG_Resolver;
use ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor;

$valid_graph = [
    'wm-activity-sentinel'     => ['wm-prototype-nda-gate'],
    'wm-prototype-nda-gate'    => ['wm-cra-sbom-beacon'],
    'wm-cra-sbom-beacon'       => [],
    'wm-hinschg-whistleblower' => ['wm-activity-sentinel'],
];

$resolved_seq = DAG_Resolver::resolve_order($valid_graph);
$expected_seq = [
    'wm-cra-sbom-beacon',
    'wm-prototype-nda-gate',
    'wm-activity-sentinel',
    'wm-hinschg-whistleblower',
];

assert_test(
    "DAG_Resolver resolves linear sequence correctly using Kahn's algorithm",
    $resolved_seq === $expected_seq,
    sprintf('Expected sequence: %s, got: %s', implode(' -> ', $expected_seq), implode(' -> ', $resolved_seq))
);

// Circular dependency cycle test
$cyclic_graph = [
    'pkg-a' => ['pkg-b'],
    'pkg-b' => ['pkg-c'],
    'pkg-c' => ['pkg-a'],
];

$cycle_caught = false;
try {
    DAG_Resolver::resolve_order($cyclic_graph);
} catch (\DomainException $e) {
    $cycle_caught = true;
}

assert_test(
    'DAG_Resolver successfully detects and aborts on circular dependencies',
    $cycle_caught === true,
    'Failed to detect cycle'
);

// TEST 10: Preflight Auditor Environmental Checks (Sprint 2)
$audit_result = Preflight_Auditor::audit($parsed_bundles['security-legal']);
assert_test(
    'Preflight_Auditor evaluates runtime environment correctly',
    isset($audit_result['checks']['php_version']) && isset($audit_result['checks']['libsodium']),
    'Preflight audit checks missing'
);

// TEST 11: Ed25519 Paranoic Cryptographic Verification (Sprint 3)
require_once __DIR__ . '/../src/Marketplace/Crypto/WM_Ed25519_Verifier.php';
use ArnoldWender\SuiteHub\Marketplace\Crypto\WM_Ed25519_Verifier;

// Generate ephemeral keypair and sign a mock zip payload
$keypair   = WM_Ed25519_Verifier::generate_keypair();
$test_file = tempnam(sys_get_temp_dir(), 'wm_test_pkg_') . '.zip';
file_put_contents($test_file, 'PK\x03\x04...mock_binary_data_wm_plugin...');

$signature = WM_Ed25519_Verifier::sign_file($test_file, $keypair['secret_key']);
$verifier  = new WM_Ed25519_Verifier();

$sig_valid = $verifier->verify_package($test_file, $signature, $keypair['public_key']);
assert_test(
    'WM_Ed25519_Verifier validates authentic package binary signature',
    $sig_valid === true,
    'Valid signature rejected'
);

// Tamper test: Alter 1 byte in the file
file_put_contents($test_file, 'PK\x03\x04...mock_binary_data_TAMPERED...');
$tamper_valid = $verifier->verify_package($test_file, $signature, $keypair['public_key']);
assert_test(
    'WM_Ed25519_Verifier strictly rejects tampered package archive',
    $tamper_valid === false,
    'Tampered file unexpectedly passed'
);

// TEST 11b (2026-11-21): no signing key exists (D9), so without one the verifier refuses to run. Until
// 1.2.2 the default "official root key" was a 64-character placeholder that decoded to 48 bytes.
$no_key_refused = false;
try {
    $verifier->verify_package($test_file, $signature);
} catch (\RuntimeException $e) {
    $no_key_refused = str_contains($e->getMessage(), 'No Ed25519 public key');
}
assert_test(
    'WM_Ed25519_Verifier ships no placeholder key and refuses to verify without a configured one',
    WM_Ed25519_Verifier::DEFAULT_MASTER_PUBLIC_KEY === ''
        && WM_Ed25519_Verifier::get_master_public_key() === ''
        && $no_key_refused,
    'a default key is set or verify_package() ran without one'
);
@unlink($test_file);

// TEST 12: Atomic Installer & Rollback Quarantine (Sprint 3)
require_once __DIR__ . '/../src/Marketplace/Installer/Atomic_Installer.php';
use ArnoldWender\SuiteHub\Marketplace\Installer\Atomic_Installer;

$installer = new Atomic_Installer($chainer);

// Create a mock zip archive for installation
$mock_zip_path = tempnam(sys_get_temp_dir(), 'wm_install_') . '.zip';
$zip = new \ZipArchive();
$zip->open($mock_zip_path, \ZipArchive::CREATE);
$zip->addFromString('index.php', '<?php // Version 2.0.0');
$zip->close();

// Create fake active plugin directory
$test_plugin_dir = sys_get_temp_dir() . '/plugins/wm-test-atomic';
if (!file_exists($test_plugin_dir)) {
    @mkdir($test_plugin_dir, 0755, true);
}
file_put_contents($test_plugin_dir . '/index.php', '<?php // Version 1.0.0 (Legacy)');

$install_success = $installer->execute_atomic_install('wm-test-atomic', $mock_zip_path);
assert_test(
    'Atomic_Installer extracts archive and stages quarantine backup',
    $install_success === true && file_exists($test_plugin_dir . '/index.php'),
    'Atomic install failed'
);

// Verify rollback restores legacy version
$rollback_success = $installer->trigger_rollback('wm-test-atomic');
$restored_content = file_get_contents($test_plugin_dir . '/index.php');
assert_test(
    'Atomic_Installer triggers safe rollback restoring quarantined backup',
    $rollback_success === true && str_contains($restored_content, 'Version 1.0.0'),
    'Rollback failed to restore legacy state'
);

@unlink($mock_zip_path);

// Mock REST Classes for Sprint 4 Test
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params = [];
        private array $headers = [];
        private string $body = '';
        public function __construct(string $method = 'GET', string $route = '') {}
        public function set_param(string $key, $value): void { $this->params[$key] = $value; }
        public function get_param(string $key) { return $this->params[$key] ?? null; }
        public function set_header(string $key, string $value): void { $this->headers[strtolower($key)] = $value; }
        public function get_header(string $key): ?string { return $this->headers[strtolower($key)] ?? null; }
        public function set_body(string $body): void { $this->body = $body; }
        public function get_body(): string { return $this->body; }
        public function get_json_params(): array {
            return !empty($this->body) ? (json_decode($this->body, true) ?? []) : $this->params;
        }
    }
}
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public mixed $data;
        public int $status;
        public function __construct($data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const READABLE   = 'GET';
        public const CREATABLE  = 'POST';
        public const EDITABLE   = 'POST, PUT, PATCH';
        public const DELETABLE  = 'DELETE';
        public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;
        public function __construct(string $code = '', string $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = is_array($data) ? $data : ['status' => $data];
        }
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response): WP_REST_Response {
        if ($response instanceof WP_REST_Response) return $response;
        return new WP_REST_Response($response);
    }
}
// Controllable from the tests: $GLOBALS['mock_nonce_valid'] = false simulates a missing/stale nonce.
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1): bool { return (bool) ($GLOBALS['mock_nonce_valid'] ?? true); }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
}
if (!function_exists('wp_privacy_anonymize_ip')) {
    function wp_privacy_anonymize_ip(string $ip): string {
        return str_contains($ip, ':') ? $ip : preg_replace('/\.\d+$/', '.0', $ip);
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string { return 'test-nonce'; }
}
// Controllable from the tests: $GLOBALS['mock_user_can'] = false is a visitor, who may not edit posts.
if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool { return (bool) ($GLOBALS['mock_user_can'] ?? true); }
}
// The shortcodes of the spoke plugins some blocks show. $GLOBALS['mock_shortcodes'] maps a tag to the markup
// its plugin prints; a tag missing from the map does not exist, as on a site without that plugin.
if (!function_exists('shortcode_exists')) {
    function shortcode_exists(string $tag): bool { return array_key_exists($tag, $GLOBALS['mock_shortcodes'] ?? []); }
}
if (!function_exists('do_shortcode')) {
    function do_shortcode(string $content, bool $ignore_html = false): string {
        return (string) preg_replace_callback(
            '/\[([a-z0-9_]+)\]/',
            static fn(array $m): string => (string) (($GLOBALS['mock_shortcodes'] ?? [])[$m[1]] ?? $m[0]),
            $content
        );
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string { return strip_tags(trim($str)); }
}
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string { return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)); }
}
if (!function_exists('is_admin')) {
    function is_admin(): bool { return false; }
}
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string { return $text; }
}

// TEST 13: Marketplace REST API Controller (Sprint 4)
require_once __DIR__ . '/../src/API/API_Controller.php';
use ArnoldWender\SuiteHub\API\API_Controller;

$api_ctrl = new API_Controller($registry, $chainer);

// 1. Test get_marketplace_bundles
$req_bundles = new WP_REST_Request('GET', '/wm-hub/v1/marketplace/bundles');
$res_bundles = $api_ctrl->get_marketplace_bundles($req_bundles);
$bundles_data = $res_bundles->get_data();

assert_test(
    'API_Controller get_marketplace_bundles returns solution packs with resolved DAG order',
    isset($bundles_data['bundles']) && count($bundles_data['bundles']) >= 4 && isset($bundles_data['bundles'][0]['resolved_order']),
    'Failed to retrieve marketplace bundles'
);

// 2. Test run_preflight_audit
$req_preflight = new WP_REST_Request('POST', '/wm-hub/v1/marketplace/preflight');
$req_preflight->set_param('bundle_id', 'security-legal');
$res_preflight = $api_ctrl->run_preflight_audit($req_preflight);
$preflight_data = $res_preflight->get_data();

assert_test(
    'API_Controller run_preflight_audit validates environment for security-legal',
    isset($preflight_data['preflight']['passed']) && isset($preflight_data['resolved_order']),
    'Preflight audit endpoint failed'
);

// The dialog labels the order "computed" only when a package requires another; the route says which.
assert_test(
    'API_Controller run_preflight_audit reports whether the pack declares any dependency',
    array_key_exists('has_dependencies', $preflight_data)
        && $preflight_data['has_dependencies'] === Manifest_Parser::load_default_manifest()['security-legal']->has_dependencies(),
    'has_dependencies missing or not taken from the bundle'
);

// 3. Test install_marketplace_bundle (Dry-Run)
$req_install = new WP_REST_Request('POST', '/wm-hub/v1/marketplace/install-bundle');
$req_install->set_param('bundle_id', 'security-legal');
$req_install->set_param('dry_run', true);
$res_install = $api_ctrl->install_marketplace_bundle($req_install);
$install_data = $res_install->get_data();

assert_test(
    'API_Controller install_marketplace_bundle executes safe Dry-Run',
    $install_data['success'] === true && $install_data['dry_run'] === true && count($install_data['resolved_order']) === 5,
    'Dry-Run installation failed'
);

// 4. A real install request must not answer "success" for an install that never happened, and must
//    not seal a bundle_installed event into the GoBD chain (fleet audit 2026-09-13 §6.2 #3, P0-4).
$rows_before_install = count($wpdb->rows);
$req_real_install = new WP_REST_Request('POST', '/wm-hub/v1/marketplace/install-bundle');
$req_real_install->set_param('bundle_id', 'security-legal');
$req_real_install->set_param('dry_run', false);
$res_real_install = $api_ctrl->install_marketplace_bundle($req_real_install);
$sealed_bundle_events = array_filter($wpdb->rows, static fn(array $r): bool => ($r['event_type'] ?? '') === 'bundle_installed');
assert_test(
    'API_Controller install_marketplace_bundle without dry_run neither claims success nor seals bundle_installed',
    $res_real_install instanceof WP_Error
    && (int) ($res_real_install->data['status'] ?? 0) === 501
    && count($wpdb->rows) === $rows_before_install
    && count($sealed_bundle_events) === 0,
    'Simulated install was reported as success or sealed into the audit chain'
);

// TEST 14: Enterprise Borlabs Cookie Bridge (v2.x & v3.x Enterprise)
require_once __DIR__ . '/../src/Bridges/Borlabs_Cookie_Bridge.php';
use ArnoldWender\SuiteHub\Bridges\Borlabs_Cookie_Bridge;

$bridge = Borlabs_Cookie_Bridge::get_instance($chainer);

// 1. 1.2.3: the bridge no longer pretends to configure Borlabs. Borlabs Cookie 3.4 has neither
// borlabsCookie/package/register nor borlabsCookie/contentBlocker/modify, and the script_loader_tag
// rewrite targeted handles no plugin registers with an attribute Borlabs does not read.
$bridge_src_14 = (string) file_get_contents(__DIR__ . '/../src/Bridges/Borlabs_Cookie_Bridge.php');
assert_test(
    'Borlabs_Cookie_Bridge hooks no Borlabs filter that does not exist and rewrites no script tag',
    !str_contains($bridge_src_14, "add_filter('borlabsCookie/")
    && !str_contains($bridge_src_14, "add_filter('script_loader_tag'")
    && !method_exists($bridge, 'register_borlabs_v3_packages')
    && !method_exists($bridge, 'get_registered_spokes'),
    'The dead Borlabs package/content-blocker hooks or the script rewrite are back'
);

// 2. The decision is read from the borlabs-cookie cookie in the shape Borlabs 3.4 writes it
// ({group: [services]}); its events carry an empty detail, so the body has no consents.
$rows_before_cookie_sync = count($wpdb->rows);
$_COOKIE['borlabs-cookie'] = json_encode([
    'consents'   => ['essential' => ['borlabs-cookie'], 'statistics' => ['matomo'], 'external-media' => ['googlemaps', 'youtube']],
    'domainPath' => 'example.test/',
    'expires'    => 'Sat, 21 Nov 2027 10:00:00 GMT',
    'uid'        => 'abcd1234-efgh5678-ijkl9012-mnop3456',
    'v3'         => true,
    'version'    => 1,
]);
$req_consent = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$req_consent->set_body(json_encode([
    'uuid'            => '550e8400-e29b-41d4-a716-446655440000',
    'borlabs_version' => '3.4',
]));
$res_consent      = $bridge->handle_consent_sync($req_consent);
$consent_res_data = $res_consent->get_data();
$sealed_consent   = end($wpdb->rows);
$sealed_payload   = is_array($sealed_consent) ? json_decode((string) ($sealed_consent['payload'] ?? $sealed_consent['event_data'] ?? ''), true) : null;
$sealed_json      = is_array($sealed_consent) ? json_encode($sealed_consent) : '';
assert_test(
    'consent-sync records the groups and services of the Borlabs cookie and Borlabs\' consent uid',
    ($consent_res_data['success'] ?? false) === true
    && ($consent_res_data['sealed'] ?? false) === true
    && !isset($consent_res_data['integrity'])
    && count($wpdb->rows) === $rows_before_cookie_sync + 1
    && str_contains((string) $sealed_json, 'googlemaps')
    && str_contains((string) $sealed_json, 'matomo')
    && str_contains((string) $sealed_json, 'abcd1234-efgh5678-ijkl9012-mnop3456'),
    'The sealed entry lacks the visitor\'s decision: ' . substr((string) $sealed_json, 0, 400)
);
unset($_COOKIE['borlabs-cookie']);

// 3. No readable decision, no entry: until 1.2.3 every saved decision became an entry with empty consents.
$rows_before_empty_sync = count($wpdb->rows);
$req_empty = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$req_empty->set_body(json_encode(['uuid' => '550e8400-e29b-41d4-a716-446655440000', 'consents' => new stdClass()]));
$res_empty = $bridge->handle_consent_sync($req_empty);
assert_test(
    'consent-sync writes nothing and answers 400 when no consent decision can be read',
    $res_empty->get_status() === 400 && count($wpdb->rows) === $rows_before_empty_sync,
    'An entry without consent data was written'
);

// 4. Without a cookie the body is still read (flags stay scalars), so the route keeps one test path.
$req_consent_body = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$req_consent_body->set_body(json_encode([
    'uuid'     => '550e8400-e29b-41d4-a716-446655440000',
    'consents' => ['essential' => true, 'marketing' => false],
]));
$res_consent_body = $bridge->handle_consent_sync($req_consent_body);
assert_test(
    'consent-sync falls back to the body consents only when the request carries no Borlabs cookie',
    ($res_consent_body->get_data()['success'] ?? false) === true,
    'Body consents were refused'
);

// 5. A failure to write keeps its reason on the server (the response used to append the exception text).
assert_test(
    'consent-sync does not return the exception message to the anonymous visitor',
    !str_contains($bridge_src_14, "' . \$e->getMessage(),")
    && str_contains($bridge_src_14, "__('Die Einwilligung konnte nicht protokolliert werden.', 'wm-suite-hub')"),
    'The 500 response carries the exception text again'
);

// 5. The public route must carry a control (fleet audit 2026-09-13 §6.2 #2, P0-3): REST nonce + rate-limit.
$bridge_source = (string) file_get_contents(__DIR__ . '/../src/Bridges/Borlabs_Cookie_Bridge.php');
assert_test(
    'consent-sync route is no longer registered with __return_true',
    !preg_match('/consent-sync.*?__return_true/s', $bridge_source),
    'permission_callback is still __return_true'
);

$has_perm_method = method_exists($bridge, 'check_consent_sync_permission');
$GLOBALS['mock_nonce_valid'] = false;
$req_no_nonce = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$perm_no_nonce = $has_perm_method ? $bridge->check_consent_sync_permission($req_no_nonce) : null;
assert_test(
    'consent-sync rejects a request without a valid REST nonce (403)',
    $perm_no_nonce instanceof WP_Error && (int) ($perm_no_nonce->data['status'] ?? 0) === 403,
    'Request without nonce was accepted'
);

$GLOBALS['mock_nonce_valid'] = true;
$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
$req_with_nonce = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$req_with_nonce->set_header('X-WP-Nonce', 'test-nonce');
$accepted = 0;
$limited  = null;
for ($i = 0; $i < 12; $i++) {
    $verdict = $has_perm_method ? $bridge->check_consent_sync_permission($req_with_nonce) : null;
    if ($verdict === true) {
        $accepted++;
    } elseif ($verdict instanceof WP_Error) {
        $limited = $verdict;
        break;
    }
}
assert_test(
    'consent-sync accepts 10 requests per minute from one client and answers 429 on the 11th',
    $accepted === 10 && $limited instanceof WP_Error && (int) ($limited->data['status'] ?? 0) === 429,
    sprintf('accepted=%d, limited=%s', $accepted, $limited instanceof WP_Error ? (string) ($limited->data['status'] ?? '?') : 'none')
);

// 6. What reaches the audit table is shaped: a group keeps a flat list of service names, a flag stays a
// scalar, nothing deeper survives, everything is bounded, and the visitor IP is anonymised.
$_SERVER['REMOTE_ADDR']     = '203.0.113.77';
$_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 600);
$oversized_consents = ['essential' => true, 'nested' => ['a' => ['b' => 1]], 'long' => str_repeat('x', 500), 'statistics' => ['matomo', ['deep' => 1], str_repeat('s', 300)]];
for ($i = 0; $i < 80; $i++) {
    $oversized_consents['k' . $i] = true;
}
$req_shape = new WP_REST_Request('POST', '/wm-suite/v1/consent-sync');
$req_shape->set_body(json_encode(['uuid' => 'shape-test', 'consents' => $oversized_consents]));
$bridge->handle_consent_sync($req_shape);
$shape_row     = end($wpdb->rows);
$shape_payload = json_decode((string) ($shape_row['payload'] ?? '{}'), true);
assert_test(
    'consent-sync stores an anonymised IP, a bounded user agent and at most 50 groups or flags, nothing nested',
    ($shape_row['ip_address'] ?? '') === '203.0.113.0'
    && strlen((string) ($shape_payload['user_agent'] ?? '')) <= 255
    && !isset($shape_payload['consents']['nested'])
    && strlen((string) ($shape_payload['consents']['long'] ?? '')) <= 64
    && ($shape_payload['consents']['statistics'][0] ?? '') === 'matomo'
    && count($shape_payload['consents']['statistics'] ?? []) === 2
    && strlen((string) ($shape_payload['consents']['statistics'][1] ?? '')) <= 64
    && count($shape_payload['consents'] ?? []) <= 50,
    sprintf('ip=%s ua_len=%d consents=%d nested=%s', $shape_row['ip_address'] ?? '?', strlen((string) ($shape_payload['user_agent'] ?? '')), count($shape_payload['consents'] ?? []), isset($shape_payload['consents']['nested']) ? 'yes' : 'no')
);
unset($_SERVER['HTTP_USER_AGENT']);

// -----------------------------------------------------------------------------
// SPRINT 6: ENTERPRISE GUTENBERG BLOCK ECOSYSTEM (Block API v3 & SSR)
// -----------------------------------------------------------------------------
echo "\n--- Sprint 6: Enterprise Gutenberg Block Ecosystem ---\n";

require_once __DIR__ . '/../src/Gutenberg/Block_Manager.php';
require_once __DIR__ . '/../src/Gutenberg/Zero_Duplication_Asset_Engine.php';

$block_mgr = \ArnoldWender\SuiteHub\Gutenberg\Block_Manager::get_instance();
$all_blocks = $block_mgr->get_registered_blocks();

// 1. Test Block Taxonomy Completeness
assert_test(
    'Block_Manager maintains exactly 16 enterprise Gutenberg blocks across 4 domains',
    count($all_blocks) === 16 && isset($all_blocks['wm/event-grid'], $all_blocks['wm/peppol-id-verifier'], $all_blocks['wm/whistleblower-wizard'], $all_blocks['wm/a11y-contrast-switch']),
    'Block count or taxonomy missing'
);

// 2. Test Block Category Registration
$categories = $block_mgr->register_block_category([], null);
assert_test(
    'Block_Manager register_block_category prepends wm-suite category',
    !empty($categories) && $categories[0]['slug'] === 'wm-suite' && $categories[0]['title'] === 'WM Enterprise Suite',
    'Category registration failed'
);

// 3. Test block.json schema validity for all 16 blocks
$all_valid_json = true;
$blocks_dir = dirname(__DIR__) . '/src/Gutenberg/Blocks';
foreach ($all_blocks as $b_name => $d_name) {
    $j_path = $blocks_dir . '/' . $d_name . '/block.json';
    if (!file_exists($j_path)) {
        $all_valid_json = false;
        break;
    }
    $decoded = json_decode((string) file_get_contents($j_path), true);
    if (!is_array($decoded) || ($decoded['name'] ?? '') !== $b_name || ($decoded['apiVersion'] ?? 0) !== 3) {
        $all_valid_json = false;
        break;
    }
}
assert_test(
    'All 16 Gutenberg blocks have valid block.json with apiVersion 3',
    $all_valid_json,
    'block.json schema check failed'
);

// 4. Test Server-Side Rendering (SSR) for key blocks
$html_event = $block_mgr->render_block_ssr('event-card', 'wm/event-card', ['title' => 'Cyber Resilience Act Summit', 'price' => '299,00 €', 'capacityRemaining' => 8], '');
assert_test(
    'Block_Manager renders wm/event-card SSR with title, price and capacity and omits empty meta spans',
    str_contains($html_event, '<article class="wm-block-container wm-event-card">')
        && str_contains($html_event, 'Cyber Resilience Act Summit')
        && str_contains($html_event, '299,00 €')
        && str_contains($html_event, '8 freie Plätze')
        && !str_contains($html_event, 'wm-event-card__badge'),
    'Event card render failed: ' . $html_event
);

$html_event_title_only = $block_mgr->render_block_ssr('event-card', 'wm/event-card', ['title' => 'NIS2 Briefing'], '');
assert_test(
    'Block_Manager renders wm/event-card with title only without fabricated meta or capacity',
    str_contains($html_event_title_only, 'NIS2 Briefing')
        && !str_contains($html_event_title_only, 'wm-event-card__meta')
        && !str_contains($html_event_title_only, 'wm-event-card__capacity')
        && !str_contains($html_event_title_only, 'Ausgebucht')
        && !str_contains($html_event_title_only, '249,00 €'),
    'Title-only event card render failed: ' . $html_event_title_only
);

$html_event_unconf = $block_mgr->render_block_ssr('event-card', 'wm/event-card', [], '');
$event_card_meta   = json_decode((string) file_get_contents($blocks_dir . '/event-card/block.json'), true);
assert_test(
    'wm/event-card defaults are empty/-1 and unconfigured SSR emits neutral notice with zero fake sample defaults',
    is_array($event_card_meta)
        && ($event_card_meta['attributes']['title']['default'] ?? null) === ''
        && ($event_card_meta['attributes']['price']['default'] ?? null) === ''
        && ($event_card_meta['attributes']['capacityRemaining']['default'] ?? null) === -1
        && str_contains($html_event_unconf, 'Veranstaltungskarte noch nicht konfiguriert')
        && !str_contains($html_event_unconf, 'Enterprise Workshop')
        && !str_contains($html_event_unconf, '2026-10-15')
        && !str_contains($html_event_unconf, '249,00 €')
        && !str_contains($html_event_unconf, '12 freie Plätze')
        && !str_contains($html_event_unconf, 'Halle (Saale)'),
    'Unconfigured event-card leaked fake defaults: ' . $html_event_unconf
);

$html_lksg_unconf = $block_mgr->render_block_ssr('lksg-esg-scorecard', 'wm/lksg-esg-scorecard', [], '');
$lksg_meta        = json_decode((string) file_get_contents($blocks_dir . '/lksg-esg-scorecard/block.json'), true);
assert_test(
    'wm/lksg-esg-scorecard defaults are -1 and unconfigured SSR renders only the fill-in notice, with zero fake percentages',
    is_array($lksg_meta)
        && ($lksg_meta['attributes']['humanRightsScore']['default'] ?? null) === -1
        && ($lksg_meta['attributes']['environmentScore']['default'] ?? null) === -1
        && ($lksg_meta['attributes']['governanceScore']['default'] ?? null) === -1
        && str_contains($html_lksg_unconf, 'Noch keine geprüften LkSG-Kennzahlen eingetragen')
        && str_contains($html_lksg_unconf, 'wm-block-notice')
        && !str_contains($html_lksg_unconf, '%')
        && !str_contains($html_lksg_unconf, 'Menschenrechte'),
    'Unconfigured lksg-esg-scorecard leaked scores or tiles: ' . $html_lksg_unconf
);

$html_lksg_conf = $block_mgr->render_block_ssr('lksg-esg-scorecard', 'wm/lksg-esg-scorecard', [
    'humanRightsScore' => 92,
    'environmentScore' => 88,
    'governanceScore'  => 140,
], '');
// Until 1.2.2 the typed numbers were captioned "Transparenzbericht über Tier-1 und Tier-2 Auditprüfungen
// gemäß §§ 4-10 LkSG", an audit report the block knows nothing about.
assert_test(
    'wm/lksg-esg-scorecard renders the typed percentages clamped to 0..100 and claims no audit behind them',
    str_contains($html_lksg_conf, '92%')
        && str_contains($html_lksg_conf, '88%')
        && str_contains($html_lksg_conf, '100%')
        && !str_contains($html_lksg_conf, '140')
        && !str_contains($html_lksg_conf, 'Transparenzbericht')
        && !str_contains($html_lksg_conf, 'Audit')
        && !str_contains($html_lksg_conf, 'wm-block-notice'),
    'Configured lksg-esg-scorecard render failed: ' . $html_lksg_conf
);

$html_grid_empty = $block_mgr->render_block_ssr('event-grid', 'wm/event-grid', [], '');
assert_test(
    'wm/event-grid without WM Cyber Events Core tells the editor that plugin is missing and shows no sample events',
    str_contains($html_grid_empty, 'Ohne aktives Plugin „WM Cyber Events Core“ zeigt dieser Block Besuchern nichts an.')
        && !str_contains($html_grid_empty, 'wm-event-grid')
        && !str_contains($html_grid_empty, '15. Sep 2026')
        && !str_contains($html_grid_empty, '24 freie Plätze')
        && !str_contains($html_grid_empty, 'Cyber Resilience Act (CRA) Briefing')
        && !str_contains($html_grid_empty, 'B2B E-Rechnung 2027 Workshop'),
    'Unconfigured event-grid render: ' . $html_grid_empty
);

// TEST 6b (2026-11-21): a block that lacks its plugin or its settings shows the editor what is missing and a
// visitor nothing. Until 1.2.2 several of these printed their fill-in notice, a demo text or invented content
// on the public page. The editor path is the REST block renderer, which runs as the editing user.
$incomplete_blocks = [
    'whistleblower-wizard'   => [],
    'bfsg-statement-embed'   => [],
    'privacy-center'         => [],
    'consent-badge'          => [],
    'privacy-eraser-form'    => [],
    'cra-security-status'    => ['version' => '1.2.2'],
    'event-booking-cta'      => ['buttonText' => 'Tickets'],
    'lksg-esg-scorecard'     => [],
    'script-placeholder'     => ['serviceName' => 'Video'],
    'newsticker-banner'      => ['badgeText' => 'NEU'],
    'event-card'             => ['price' => '10 €'],
    'event-grid'             => [],
    'modular-type-container' => [],
];
$GLOBALS['mock_shortcodes'] = [];
$visible_to_visitor = [];
$silent_to_editor   = [];
foreach ($incomplete_blocks as $inc_dir => $inc_attrs) {
    $GLOBALS['mock_user_can'] = false;
    $as_visitor = trim($block_mgr->render_block_ssr($inc_dir, 'wm/' . $inc_dir, $inc_attrs, ''));
    $GLOBALS['mock_user_can'] = true;
    $as_editor = $block_mgr->render_block_ssr($inc_dir, 'wm/' . $inc_dir, $inc_attrs, '');
    if ('' !== $as_visitor) {
        $visible_to_visitor[] = $inc_dir;
    }
    if (!str_contains($as_editor, 'class="wm-block-container wm-block-notice"')) {
        $silent_to_editor[] = $inc_dir;
    }
}
unset($GLOBALS['mock_user_can']);
assert_test(
    'all 13 blocks that lack their plugin or settings render nothing for visitors and a notice for editors',
    count($incomplete_blocks) === 13 && $visible_to_visitor === [] && $silent_to_editor === [],
    'visible to visitors: ' . ($visible_to_visitor === [] ? 'none' : implode(', ', $visible_to_visitor))
        . ' | no notice for editors: ' . ($silent_to_editor === [] ? 'none' : implode(', ', $silent_to_editor))
);

// TEST 6c (2026-11-21): the four blocks that stand for another plugin show that plugin's own output. Until
// 1.2.2 each drew a form, switches or a statement of its own that stored, sent or verified nothing.
$GLOBALS['mock_shortcodes'] = [
    'wm_hinschg_portal'       => '<form class="probe-hinschg-portal"><select name="category"></select></form>',
    'wm_dsgvo_erasure_portal' => '<form class="probe-eraser-form"><input type="email" name="email"></form>',
    'wm_bfsg_statement'       => '<section class="probe-bfsg-statement"><h2>Erklärung zur Barrierefreiheit</h2></section>',
    'wm_consent_settings'     => '<button type="button" class="probe-consent-button" data-wm-consent-open>Einstellungen</button>',
];
$html_wizard_live  = $block_mgr->render_block_ssr('whistleblower-wizard', 'wm/whistleblower-wizard', [], '');
$html_eraser_live  = $block_mgr->render_block_ssr('privacy-eraser-form', 'wm/privacy-eraser-form', [], '');
$html_bfsg_live    = $block_mgr->render_block_ssr('bfsg-statement-embed', 'wm/bfsg-statement-embed', [], '');
$html_privacy_live = $block_mgr->render_block_ssr('privacy-center', 'wm/privacy-center', [], '');
$html_badge_live   = $block_mgr->render_block_ssr('consent-badge', 'wm/consent-badge', [], '');
$GLOBALS['mock_shortcodes'] = [];
assert_test(
    'whistleblower, eraser, BFSG statement and privacy blocks print the spoke plugin\'s output and no form of their own',
    str_contains($html_wizard_live, 'id="whistleblower-wizard"')
        && str_contains($html_wizard_live, '<form class="probe-hinschg-portal"><select name="category">')
        && !str_contains($html_wizard_live, 'wm-token-box')
        && !str_contains($html_wizard_live, 'wm-wizard-progress')
        && str_contains($html_eraser_live, '<input type="email" name="email">')
        && substr_count($html_eraser_live, '<form') === 1
        && str_contains($html_bfsg_live, 'probe-bfsg-statement')
        && !str_contains($html_bfsg_live, 'EN 301 549')
        && str_contains($html_privacy_live, 'id="wm-privacy-preferences"')
        && str_contains($html_privacy_live, 'Datenschutz-Einstellungen')
        && str_contains($html_privacy_live, 'data-wm-consent-open')
        && !str_contains($html_privacy_live, 'type="checkbox"')
        && str_contains($html_badge_live, 'href="#wm-consent-settings"')
        && str_contains($html_badge_live, 'data-wm-consent-open')
        && !str_contains($html_badge_live, 'Versiegelt'),
    'delegating render failed: ' . $html_wizard_live . ' | ' . $html_privacy_live . ' | ' . $html_badge_live
);

// TEST 6d (2026-11-21): configured, the link and download blocks go where the editor said and claim nothing.
$html_card_default = $block_mgr->render_block_ssr('whistleblower-card', 'wm/whistleblower-card', [], '');
$html_card_custom  = $block_mgr->render_block_ssr('whistleblower-card', 'wm/whistleblower-card', ['companyName' => 'Muster GmbH', 'channelUrl' => 'https://example.com/meldestelle'], '');
$html_cta_conf     = $block_mgr->render_block_ssr('event-booking-cta', 'wm/event-booking-cta', ['bookingUrl' => 'https://example.com/tickets', 'taxNote' => 'Preise inkl. MwSt.'], '');
$html_cra_conf     = $block_mgr->render_block_ssr('cra-security-status', 'wm/cra-security-status', ['sbomUrl' => 'https://example.com/sbom.json', 'version' => '1.2.2'], '');
$html_holder_conf  = $block_mgr->render_block_ssr('script-placeholder', 'wm/script-placeholder', ['targetElementId' => 'wm-video-embed'], '');
assert_test(
    'whistleblower card, booking CTA, SBOM box and placeholder link where the editor said and state no conformity',
    str_contains($html_card_default, 'href="#whistleblower-wizard"')
        && !str_contains($html_card_default, '<h3')
        && str_contains($html_card_custom, 'href="https://example.com/meldestelle"')
        && str_contains($html_card_custom, 'Muster GmbH')
        && !str_contains($html_card_custom . $html_card_default, 'RSA')
        && !str_contains($html_card_custom . $html_card_default, 'check-status')
        && !str_contains($html_card_custom . $html_card_default, 'konform')
        && str_contains($html_cta_conf, 'href="https://example.com/tickets"')
        && str_contains($html_cta_conf, 'Zur Buchung')
        && str_contains($html_cta_conf, 'Preise inkl. MwSt.')
        && !str_contains($html_cta_conf, '/kasse/')
        && !str_contains($html_cta_conf, 'zzgl.')
        && str_contains($html_cra_conf, 'href="https://example.com/sbom.json"')
        && str_contains($html_cra_conf, 'Software-Stückliste (SBOM) · 1.2.2')
        && !str_contains($html_cra_conf, 'BSI')
        && !str_contains($html_cra_conf, 'auditiert')
        && str_contains($html_holder_conf, 'data-unblock-target="wm-video-embed"')
        && str_contains($html_holder_conf, 'Inhalt einmalig laden')
        && !str_contains($html_holder_conf, 'blockiert'),
    'configured render failed: ' . $html_card_custom . ' | ' . $html_cta_conf . ' | ' . $html_cra_conf . ' | ' . $html_holder_conf
);

// The event card books nothing itself: the button needs a booking page, and a sold-out card has none.
$html_card_booking = $block_mgr->render_block_ssr('event-card', 'wm/event-card', ['title' => 'NIS2 Briefing', 'capacityRemaining' => 3, 'bookingUrl' => 'https://example.com/nis2'], '');
$html_card_soldout = $block_mgr->render_block_ssr('event-card', 'wm/event-card', ['title' => 'NIS2 Briefing', 'capacityRemaining' => 0, 'bookingUrl' => 'https://example.com/nis2'], '');
assert_test(
    'wm/event-card links its button to the booking page entered, hides it when sold out and offers no waiting list',
    str_contains($html_card_booking, 'href="https://example.com/nis2"')
        && str_contains($html_card_booking, 'Nur noch 3 Plätze')
        && str_contains($html_card_soldout, 'Ausgebucht')
        && !str_contains($html_card_soldout, 'href=')
        && !str_contains($html_event_title_only, 'href=')
        && !str_contains($html_card_booking . $html_card_soldout . $html_event, 'Warteliste')
        && !str_contains($html_card_booking . $html_card_soldout . $html_event, '#book-event'),
    'event-card button render failed: ' . $html_card_booking . ' | ' . $html_card_soldout
);

if (!class_exists('CyberEvents_Database')) {
    class CyberEvents_Database {
        public static function get_active_events(): array {
            return [
                (object) [
                    'id'           => 10,
                    'title'        => 'Live CyberEvents Konferenz',
                    'start_date'   => '2027-03-10 09:00:00',
                    'location'     => 'Halle (Saale)',
                    'max_capacity' => 80,
                    'is_paid'      => 1,
                    'ticket_price' => '199.00',
                    'wp_post_id'   => 0,
                ],
                (object) [
                    'id'           => 11,
                    'title'        => 'Zweites Event Jenseits Limit',
                    'start_date'   => '2027-04-12 10:00:00',
                    'location'     => 'Leipzig',
                    'max_capacity' => 40,
                    'is_paid'      => 0,
                    'ticket_price' => '0.00',
                    'wp_post_id'   => 0,
                ],
            ];
        }
    }
}
$html_grid_live = $block_mgr->render_block_ssr('event-grid', 'wm/event-grid', ['limit' => 1], '');
assert_test(
    'wm/event-grid queries CyberEvents_Database::get_active_events() when available and respects limit',
    str_contains($html_grid_live, 'Live CyberEvents Konferenz')
        && str_contains($html_grid_live, '2027-03-10')
        && str_contains($html_grid_live, '199,00 €')
        && str_contains($html_grid_live, 'Max. 80 Plätze')
        && !str_contains($html_grid_live, 'Zweites Event Jenseits Limit'),
    'Live CyberEvents_Database event-grid render failed: ' . $html_grid_live
);

// An event without a page of its own (wp_post_id 0) gets no button: until 1.2.2 it linked to #booking,
// an anchor nothing defined, under "Jetzt verbindlich buchen".
assert_test(
    'wm/event-grid offers no button for an event without its own page, and never a binding booking',
    !str_contains($html_grid_live, 'href=')
        && !str_contains($html_grid_live, '#booking')
        && !str_contains($html_grid_live, 'verbindlich'),
    'event-grid linked an event without a page: ' . $html_grid_live
);

$html_peppol = $block_mgr->render_block_ssr('peppol-id-verifier', 'wm/peppol-id-verifier', [], '');
assert_test(
    'Block_Manager renders wm/peppol-id-verifier as a format check that says it looks nothing up',
    str_contains($html_peppol, 'wm-peppol-verifier')
        && str_contains($html_peppol, 'pattern="^[0-9]{4}:[A-Za-z0-9\-_.]+$"')
        && str_contains($html_peppol, 'prüft dieser Block nicht')
        && !str_contains($html_peppol, 'XRechnung')
        && !str_contains($html_peppol, 'EN 16931'),
    'Peppol verifier render failed: ' . $html_peppol
);

$html_a11y = $block_mgr->render_block_ssr('a11y-contrast-switch', 'wm/a11y-contrast-switch', [], '');
assert_test(
    'Block_Manager renders wm/a11y-contrast-switch with the contrast and reading-font buttons',
    str_contains($html_a11y, 'data-wm-a11y-action="toggle-contrast"') && str_contains($html_a11y, 'data-wm-a11y-action="toggle-dyslexic"'),
    'A11y toolbar render failed'
);

$html_ticker = $block_mgr->render_block_ssr('newsticker-banner', 'wm/newsticker-banner', ['badgeText' => 'UPDATE', 'tickerText' => 'Sommerfest am Freitag'], '');
assert_test(
    'Block_Manager renders wm/newsticker-banner with the text entered and its pause button',
    str_contains($html_ticker, 'wm-newsticker-pause-btn')
        && str_contains($html_ticker, 'Sommerfest am Freitag')
        && str_contains($html_ticker, 'UPDATE'),
    'Newsticker render failed'
);

echo "\n--- Sprint 7: Third-Party Developer SDK & Extensibility Pipeline ---\n";

// 1. Test Developer_SDK_Bridge singleton & hook registration
$sdk_bridge = \ArnoldWender\SuiteHub\Compliance\Developer_SDK_Bridge::get_instance();
assert_test(
    'Developer_SDK_Bridge initializes and registers global hooks',
    $sdk_bridge instanceof \ArnoldWender\SuiteHub\Compliance\Developer_SDK_Bridge,
    'Bridge instance failed'
);

// 2. Test Audit Event Ingestion via do_action('wm_compliance_record_audit_event')
$audit_initial_count = count($wpdb->rows);
do_action('wm_compliance_record_audit_event', [
    'event_type'    => 'ecommerce.order_paid',
    'severity'      => 'INFO',
    'actor_id'      => 'customer-409',
    'context_data'  => ['order_id' => 8812, 'total' => 350.00, 'password' => 'supersecret123'],
    'origin_module' => 'woocommerce-b2b-bridge',
]);
$audit_new_count = count($wpdb->rows);
$latest_row = end($wpdb->rows);
$latest_payload = json_decode($latest_row['payload'], true);

assert_test(
    'Developer_SDK_Bridge ingests third-party audit events and redacts secret keys',
    $audit_new_count > $audit_initial_count 
    && ($latest_payload['event_type'] ?? '') === 'ecommerce.order_paid'
    && ($latest_payload['context_data']['password'] ?? '') === '[REDACTED_SECRET]'
    && ($latest_payload['origin_module'] ?? '') === 'woocommerce-b2b-bridge',
    'Audit ingestion or secret redaction failed'
);

// 3. Test PII Sanitization for LLM Prompts (German IBAN, VAT ID, Email, Luhn-valid Credit Card, Phone)
$raw_prompt = 'Kunde Max mit IBAN DE89370400440532013000, USt-IdNr DE123456789, Email max@mustermann.de, Telefon +49 345 1234567, Aktenzeichen 4000 0000 0000 0001 und Visa 4532 0150 0000 0007 bat um Prüfung.';
$sanitized_prompt = apply_filters('wm_compliance_sanitize_prompt_pii', $raw_prompt);

assert_test(
    'Developer_SDK_Bridge sanitizes German IBAN, USt-IdNr, Email, Phone and Luhn-valid Credit Card while preserving non-Luhn numbers',
    str_contains($sanitized_prompt, '[ANONYMIZED_IBAN_DE]')
    && str_contains($sanitized_prompt, '[ANONYMIZED_UST_IDNR]')
    && str_contains($sanitized_prompt, '[ANONYMIZED_EMAIL]')
    && str_contains($sanitized_prompt, '[ANONYMIZED_PHONE]')
    && str_contains($sanitized_prompt, '[ANONYMIZED_CREDIT_CARD]')
    && str_contains($sanitized_prompt, '4000 0000 0000 0001')
    && !str_contains($sanitized_prompt, '4532 0150 0000 0007')
    && !str_contains($sanitized_prompt, 'DE89370400440532013000')
    && !str_contains($sanitized_prompt, 'max@mustermann.de'),
    'PII Sanitization failed: ' . $sanitized_prompt
);

// 3b. Test Sovereign Consent Vault bridge priority in wm_compliance_has_consent.
// The cookie has the shape Borlabs Cookie 3.4 writes ({group: [services]}). Until 1.2.3 the bridge looked for
// the category among the values, and this test fed it the list ['marketing'] that no Borlabs version writes.
$_COOKIE['borlabs-cookie'] = json_encode([
    'consents' => ['essential' => ['borlabs-cookie'], 'marketing' => ['facebook-pixel']],
    'uid'      => 'abcd1234-efgh5678-ijkl9012-mnop3456',
    'v3'       => true,
]);
assert_test(
    'Developer_SDK_Bridge falls back to Borlabs cookie when Sovereign Consent Vault is inactive',
    true === apply_filters('wm_compliance_has_consent', false, 'marketing')
    && true === apply_filters('wm_compliance_has_consent', false, 'facebook-pixel')
    && false === apply_filters('wm_compliance_has_consent', false, 'external_media')
    && false === apply_filters('wm_compliance_has_consent', false, 'statistics')
);
$_COOKIE['borlabs-cookie'] = json_encode(['consents' => ['essential' => ['borlabs-cookie'], 'external-media' => ['googlemaps']]]);
$external_media_by_alias = apply_filters('wm_compliance_has_consent', false, 'external_media');
$_COOKIE['borlabs-cookie'] = json_encode(['consents' => ['essential' => ['borlabs-cookie'], 'marketing' => ['facebook-pixel']]]);
assert_test(
    'Developer_SDK_Bridge reads external_media as the Borlabs group external-media',
    true === $external_media_by_alias
);

$GLOBALS['mock_sovereign_consent'] = ['external_media' => true, 'marketing' => false];
if (!function_exists('wm_consent_has')) {
    function wm_consent_has(string $cat): bool {
        return (bool) ($GLOBALS['mock_sovereign_consent'][$cat] ?? false);
    }
}
assert_test(
    'Developer_SDK_Bridge queries wm-sovereign-consent-vault (wm_consent_has) ahead of legacy Borlabs cookie',
    true === apply_filters('wm_compliance_has_consent', false, 'external_media')
    && false === apply_filters('wm_compliance_has_consent', true, 'marketing')
);
unset($_COOKIE['borlabs-cookie']);

// 4. Test 2-Click Blocked Embed Placeholder Generation (SVG lock icon, zero emoji, CSS-safe URL)
$placeholder_html = apply_filters('wm_compliance_render_blocked_placeholder', '', [
    'service_name' => 'OpenStreetMap Halle',
    'category'     => 'external_media',
    'preview_img'  => 'https://example.com/osm.jpg',
]);
$malicious_placeholder = apply_filters('wm_compliance_render_blocked_placeholder', '', [
    'service_name' => 'OpenStreetMap Halle',
    'category'     => 'external_media',
    'preview_img'  => "https://example.com/osm.jpg');color:red;",
]);

assert_test(
    'Developer_SDK_Bridge renders accessible 2-click consent placeholder with SVG icon, zero emoji and CSS-safe preview URL',
    str_contains($placeholder_html, 'wm-blocked-embed-placeholder')
    && str_contains($placeholder_html, 'OpenStreetMap Halle')
    && str_contains($placeholder_html, 'https://example.com/osm.jpg')
    && str_contains($placeholder_html, 'data-wm-unlock="external_media"')
    && str_contains($placeholder_html, 'role="region"')
    && str_contains($placeholder_html, '<svg class="wm-block-icon"')
    && !str_contains($placeholder_html, '🔒')
    && !str_contains($malicious_placeholder, 'color:red'),
    'Placeholder render failed: ' . $malicious_placeholder
);

// 5. Test Dynamic SBOM Component Registration
add_filter('wm_compliance_register_provider', static function(array $providers): array {
    $providers[] = new class implements \ArnoldWender\SuiteHub\Contracts\WM_Compliance_Provider_Interface {
        public function get_integration_slug(): string { return 'vendor-fluent-forms-ext'; }
        public function get_integration_name(): string { return 'Vendor Fluent Forms Enterprise'; }
        public function get_integration_version(): string { return '2.4.0'; }
        public function get_supported_compliance_vectors(): array { return ['audit_ledger', 'pii_sanitization']; }
    };
    return $providers;
});
$sdk_bridge->discover_third_party_providers();
$sbom_components = apply_filters('wm_cra_sbom_register_component', []);

assert_test(
    'Developer_SDK_Bridge registers 3rd-party provider into CRA CycloneDX SBOM inventory',
    !empty($sbom_components) && $sbom_components[0]['name'] === 'vendor-fluent-forms-ext' && $sbom_components[0]['version'] === '2.4.0',
    'SBOM dynamic registration failed'
);

// 6. Test Standalone Compliance Facade (arnoldwender/compliance-sdk)
\ArnoldWender\ComplianceSDK\Compliance::audit(
    eventType: 'sdk.standalone.test',
    context: ['action' => 'verified'],
    severity: \ArnoldWender\ComplianceSDK\Enums\EventSeverity::CRITICAL,
    actorId: 'dev-1',
    originModule: 'sdk-test'
);
$last_sdk_row = end($wpdb->rows);
$last_sdk_payload = json_decode($last_sdk_row['payload'], true);

assert_test(
    'Compliance Facade executes audit log dispatch with typed Enums and payloads',
    ($last_sdk_payload['event_type'] ?? '') === 'sdk.standalone.test'
    && ($last_sdk_payload['severity'] ?? '') === 'CRITICAL'
    && ($last_sdk_payload['origin_module'] ?? '') === 'sdk-test',
    'SDK Facade execution failed'
);

// 7. Test WM_SDK_CLI Scaffold Generator
$scaffold_code = \ArnoldWender\SuiteHub\CLI\WM_SDK_CLI::generate_scaffold_template(
    'acme-booking-pro',
    'Acme Booking Pro',
    'Acme_Booking_Pro_Compliance_Adapter',
    ['audit_ledger', 'consent_gateway']
);

assert_test(
    'WM_SDK_CLI generate_scaffold_template produces valid PHP 8.2 boilerplate',
    str_contains($scaffold_code, 'class Acme_Booking_Pro_Compliance_Adapter implements WM_Compliance_Provider_Interface')
    && str_contains($scaffold_code, "return 'acme-booking-pro';")
    && str_contains($scaffold_code, 'wm_compliance_register_provider'),
    'Scaffold generator failed'
);

// 8. Test Unpacked Dispatch Hook do_action('wm_suite_hub_dispatch_audit_event')
$dispatch_initial_count = count($wpdb->rows);
do_action(
    'wm_suite_hub_dispatch_audit_event',
    'wm-peppol-as4-gateway',
    'invoice_transmitted',
    'finance',
    'info',
    42,
    'INV-2026-001',
    ['amount' => 1500.50, 'currency' => 'EUR']
);
$dispatch_new_count = count($wpdb->rows);
$latest_dispatch_row = end($wpdb->rows);
$latest_dispatch_payload = json_decode($latest_dispatch_row['payload'], true);

assert_test(
    'Developer_SDK_Bridge handles wm_suite_hub_dispatch_audit_event unpacked arguments',
    $dispatch_new_count > $dispatch_initial_count
    && ($latest_dispatch_payload['event_type'] ?? '') === 'invoice_transmitted'
    && ($latest_dispatch_payload['origin_module'] ?? '') === 'wm-peppol-as4-gateway'
    && ($latest_dispatch_payload['actor_id'] ?? '') === '42'
    && ($latest_dispatch_payload['context_data']['category'] ?? '') === 'finance'
    && ($latest_dispatch_payload['context_data']['resource_id'] ?? '') === 'INV-2026-001',
    'Unpacked dispatch hook handling failed'
);

// 9. Test Standalone WM_Compliance_Bridge::log_event()
require_once __DIR__ . '/../packages/compliance-sdk/src/WM_Compliance_Bridge.php';
$bridge_initial_count = count($wpdb->rows);
\ArnoldWender\ComplianceSDK\WM_Compliance_Bridge::log_event(
    module_slug: 'mi-tienda-online',
    event_type:  'order_completed',
    category:    'finance',
    severity:    'info',
    actor_id:    101,
    resource_id: 'ORDER-999',
    metadata:    ['total' => 99.00]
);
$bridge_new_count = count($wpdb->rows);
$latest_bridge_row = end($wpdb->rows);
$latest_bridge_payload = json_decode($latest_bridge_row['payload'], true);

assert_test(
    'WM_Compliance_Bridge logs events seamlessly to the GoBD HMAC ledger',
    $bridge_new_count > $bridge_initial_count
    && ($latest_bridge_payload['event_type'] ?? '') === 'order_completed'
    && ($latest_bridge_payload['origin_module'] ?? '') === 'mi-tienda-online'
    && ($latest_bridge_payload['actor_id'] ?? '') === '101',
    'WM_Compliance_Bridge event logging failed'
);

// 10. Every WP-CLI subcommand named in the documentation exists as a registered subcommand.
// WP-CLI registers `@subcommand <name>` when present on a public method's docblock, and falls back
// to `$method->name` otherwise. Documenting a subcommand that does not match the registered spelling
// hands the reader a command that errors out, so the documents are checked against the classes.
require_once __DIR__ . '/../src/CLI/WM_Hub_CLI.php';
require_once __DIR__ . '/../src/CLI/WM_SDK_CLI.php';

$cli_classes = [
    'hub' => \ArnoldWender\SuiteHub\CLI\WM_Hub_CLI::class,
    'sdk' => \ArnoldWender\SuiteHub\CLI\WM_SDK_CLI::class,
];
$cli_registered_subcommands = [];
foreach ($cli_classes as $cmd_key => $cmd_class) {
    $cli_registered_subcommands[$cmd_key] = [];
    $cmd_ref = new ReflectionClass($cmd_class);
    foreach ($cmd_ref->getMethods(ReflectionMethod::IS_PUBLIC) as $cmd_method) {
        if ($cmd_method->isConstructor() || $cmd_method->isStatic() || $cmd_method->class !== $cmd_class) {
            continue;
        }
        $m_doc = (string) $cmd_method->getDocComment();
        $sub   = preg_match('/@subcommand\s+([a-z0-9_-]+)/', $m_doc, $sub_m) ? $sub_m[1] : $cmd_method->name;
        $cli_registered_subcommands[$cmd_key][$sub] = true;
    }
}

$cli_documents = [
    'README.md',
    'README.de.md',
    'README.es.md',
    'readme.txt',
    'DEVELOPERS.md',
    'CLAUDE.md',
    'llms.txt',
    'src/CLI/WM_Hub_CLI.php',
    'src/CLI/WM_SDK_CLI.php',
];

$cli_missing = [];
$cli_seen    = [];

foreach ($cli_documents as $relative_path) {
    $document_path = __DIR__ . '/../' . $relative_path;
    if (!is_file($document_path)) {
        $cli_missing[] = $relative_path . ' (document not found)';
        continue;
    }

    $document = (string) file_get_contents($document_path);
    $claims   = [];

    // Command-line form: "wp wm-hub crypto-verify", alternatives joined with a pipe.
    if (preg_match_all('/wp wm-(hub|sdk)\s+([a-z][a-z0-9_|-]*)/', $document, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            foreach (explode('|', $match[2]) as $subcommand) {
                $claims[] = [$match[1], $subcommand];
            }
        }
    }

    // Prose form: "`wp wm-hub` (status, sbom, crypto-verify, ...)".
    if (preg_match_all('/`wp wm-(hub|sdk)`\s*\(([^)]+)\)/', $document, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            foreach (explode(',', $match[2]) as $subcommand) {
                $claims[] = [$match[1], trim($subcommand)];
            }
        }
    }

    foreach ($claims as [$command, $subcommand]) {
        if ($subcommand === '') {
            continue;
        }
        $cli_seen[$command . ' ' . $subcommand] = true;
        if (!isset($cli_registered_subcommands[$command][$subcommand])) {
            $cli_missing[] = sprintf('%s: wp wm-%s %s', $relative_path, $command, $subcommand);
        }
    }
}

// The twelve public subcommands of the two classes. A regex that stops matching would otherwise
// leave the list empty and report green without having checked anything.
assert_test(
    'every WP-CLI subcommand named in the documentation exists as a registered subcommand of its command class',
    $cli_missing === [] && count($cli_seen) >= 12,
    sprintf(
        'documented without a method: %s | distinct subcommands checked: %d',
        $cli_missing === [] ? 'none' : implode(', ', $cli_missing),
        count($cli_seen)
    )
);

// 11. The canonical family sheet carries no class that belongs to one plugin.
// Every WM plugin bundles a byte-identical copy of this file, so a selector written for a single
// plugin ships to all 21 and cannot be removed later without re-syncing the fleet. Five aliases of
// wm-modularscale (.wmmsp-guide-card/-title/-text, .wmmsp-preset-pills/-btn) rode along that way.
//
// Two shapes are refused, because the first version of this test only refused the first and a
// reader walked straight through it: `.wm-newsticker-only-ticker-speed-control` passed green, since
// the check accepted anything starting with `wm-` — the prefix the family convention obliges every
// plugin to use. So a class is also rejected when it opens with the slug of a fleet plugin.
//
// What this test does NOT do is measure whether a class is referenced anywhere: the other repos are
// not on disk in CI. That measurement lives in `wp-plugin-fleet-ui-sync.py --check`, which runs in
// the workspace where the fleet exists. The wording here says only what the code checks.
$family_sheet = __DIR__ . '/../assets/css/wm-admin-ui.css';
$sheet_source = is_file($family_sheet) ? (string) file_get_contents($family_sheet) : '';
// Comments carry file names like "wm-admin-ui.css", which are not selectors.
$sheet_rules  = preg_replace('#/\*.*?\*/#s', '', $sheet_source);

$wordpress_classes = [
    'wrap', 'button', 'button-primary', 'button-secondary', 'notice', 'form-table', 'widefat',
    'striped', 'screen-reader-text', 'dashicons', 'active', 'description', 'regular-text',
];
$modifier_prefixes = ['is-', 'has-', 'text-'];
// The fleet slugs without their `wm-` prefix: a class opening with one of them names a plugin.
$fleet_plugin_names = [
    'a11y-sensory-governor', 'activity-sentinel', 'bfsg-ast-remediator', 'bfsg-compliance-dashboard',
    'cra-sbom-beacon', 'cyber-events-core', 'dora-ict-register', 'dsgvo-newsletter-suite',
    'dsgvo-privacy-eraser', 'edu-privacy-portal', 'ethical-conversion-triggers',
    'hinschg-whistleblower', 'k9-bh-exam-planner', 'kanzlei-ai-sanitizer', 'lksg-supply-auditor',
    'modularscale', 'newsticker', 'peppol-as4-gateway', 'prototype-nda-gate', 'salon-sovereign-hub',
    'social-care-accessibility-hub', 'sovereign-consent-vault', 'zero-cdn-asset-localizer',
    // Prefixes the plugins use for their own layers, which belong in their own sheets.
    'wmmsp', 'cyb', 'sentinel', 'nda',
];

preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', (string) $sheet_rules, $sheet_classes);
$foreign_classes = [];
$plugin_classes  = [];
foreach (array_unique($sheet_classes[1]) as $class_name) {
    foreach ($fleet_plugin_names as $plugin_name) {
        if (str_starts_with($class_name, $plugin_name . '-') || str_starts_with($class_name, 'wm-' . $plugin_name . '-')) {
            $plugin_classes[] = $class_name;
            continue 2;
        }
    }
    if (str_starts_with($class_name, 'wm-') || in_array($class_name, $wordpress_classes, true)) {
        continue;
    }
    foreach ($modifier_prefixes as $prefix) {
        if (str_starts_with($class_name, $prefix)) {
            continue 2;
        }
    }
    $foreign_classes[] = $class_name;
}

assert_test(
    'every class in the canonical family sheet is a family class, a modifier or a WordPress class, and none is named after a plugin',
    '' !== $sheet_source && $foreign_classes === [] && $plugin_classes === [],
    sprintf(
        'named after a plugin: %s | neither wm-*, a modifier nor a WordPress class: %s',
        $plugin_classes === [] ? 'none' : implode(', ', $plugin_classes),
        $foreign_classes === [] ? 'none' : implode(', ', $foreign_classes)
    )
);

// 11b. The family header clears WordPress's help-tab float.
// On a screen with a help tab, #screen-meta-links floats right above .wrap; the header is a flex box,
// which may not overlap a float, so it was shortened by the toggle's width (1155px of 1238px on 21
// screens of four plugins, 2026-11-21). The first rule for the header selector (section 2, outside the
// media query) must carry clear: both. Measured in a browser by wp-plugin-fleet-admin-header-probe.py.
$header_rule_body = preg_match(
    '/:is\(\.wm-admin, \.wm-obsidian-wrap, \.wm-admin-wrap, \.wm-wrap\) :is\(\.wm-admin-header, \.wm-obsidian-header, \.wm-enterprise-banner\)\s*\{([^}]*)\}/',
    (string) $sheet_rules,
    $header_rule_m
) ? $header_rule_m[1] : '';
assert_test(
    'the family header clears the help-tab float, so it spans the page on screens with a "Hilfe" toggle',
    1 === preg_match('/(^|;|\s)clear\s*:\s*both\s*;/', $header_rule_body),
    '' === $header_rule_body ? 'header rule not found' : 'no clear: both in the header rule'
);

// 12. Each CLI class documents every subcommand it registers.
// Test 10 checks one direction (everything documented exists); this is the other one. The `Usage:`
// block of WM_Hub_CLI listed five of its eight methods, so `wp help wm-hub` — which prints this
// text — omitted bundle, package and deduplicate-telemetry.
$cli_usage_gaps = [];
foreach (['hub' => \ArnoldWender\SuiteHub\CLI\WM_Hub_CLI::class, 'sdk' => \ArnoldWender\SuiteHub\CLI\WM_SDK_CLI::class] as $command => $class_name) {
    $reflection = new ReflectionClass($class_name);
    $doc_block  = (string) $reflection->getDocComment();
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isConstructor() || $method->isStatic() || $method->class !== $class_name) {
            continue;
        }
        $method_doc = (string) $method->getDocComment();
        $sub_name   = preg_match('/@subcommand\s+([a-z0-9_-]+)/', $method_doc, $sub_m) ? $sub_m[1] : $method->name;
        if (!str_contains($doc_block, "wp wm-{$command} {$sub_name}")) {
            $cli_usage_gaps[] = "wm-{$command} {$sub_name}";
        }
    }
}

assert_test(
    'the Usage block of each CLI class names every subcommand it registers (it is what wp help prints)',
    $cli_usage_gaps === [],
    'registered but undocumented: ' . ($cli_usage_gaps === [] ? 'none' : implode(', ', $cli_usage_gaps))
);

// 13. The dashboard widget's inline CSS stays inside the hub's own widgets.
// Dashboard_Widgets.php ships a palette of its own through wp_add_inline_style( 'dashboard', … ),
// which loads on every visit to index.php while the hub is active. Its selectors were the family
// classes every spoke uses for its own dashboard widget — 16 of them render .wm-dash-kpi-val — and
// `.wm-dash-widget-box[data-wm-theme="cyber-events"] .wm-dash-kpi-val` (0,0,3,0) beat the
// `.text-emerald` (0,0,1,0) those spokes put on it, so the hub painted another plugin's "active"
// green in its own blue. Every rule is scoped to .wm-hub-widget now, a class only the hub's two
// widgets carry.
$widget_file   = __DIR__ . '/../src/Admin/Dashboard_Widgets.php';
$widget_source = is_file($widget_file) ? (string) file_get_contents($widget_file) : '';
$unscoped_rules = [];
foreach (preg_split('/\R/', $widget_source) ?: [] as $line) {
    // A CSS rule of this file opens a line with a class selector and ends it with a brace.
    if (preg_match('/^\s*\.[a-zA-Z][^{}]*\{/', $line) && !str_contains($line, '.wm-hub-widget')) {
        $unscoped_rules[] = trim($line);
    }
}

assert_test(
    'every rule of the dashboard widget CSS is scoped to the hub\'s own widget (it loads on index.php for every plugin)',
    '' !== $widget_source && $unscoped_rules === [],
    sprintf('rules that would reach another plugin\'s widget: %s', $unscoped_rules === [] ? 'none' : implode(' | ', array_slice($unscoped_rules, 0, 4)))
);

assert_test(
    'both hub widgets carry the class those rules are scoped to',
    2 === substr_count($widget_source, 'class="wm-dash-widget-box wm-hub-widget"'),
    'markup carrying the scope class: ' . substr_count($widget_source, 'class="wm-dash-widget-box wm-hub-widget"')
);

// 14. Every option the plugin writes is deleted on uninstall.
// uninstall.php promises a database "without orphaned capabilities, roles, or settings" and left
// wm_suite_theme behind — autoloaded, so WordPress kept loading it on every request for a plugin
// that no longer existed. Grepping the writers keeps this honest as options are added.
$option_writers = [];
$plugin_root    = dirname(__DIR__);
$php_files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . '/src'));
foreach ($php_files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    preg_match_all(
        '/(?:add|update)_option\(\s*[\'"](wm[_a-z0-9]+)[\'"]/',
        (string) file_get_contents($file->getPathname()),
        $matches
    );
    foreach ($matches[1] as $option) {
        $option_writers[$option] = true;
    }
}

// Only lines that actually execute count. Searching the whole file for the call would accept a
// commented-out one, and that is not a hypothetical: the first version of this check passed while
// the mutation `// delete_option('wm_suite_theme');` was in place.
$uninstall_live_lines = [];
foreach (preg_split('/\R/', (string) file_get_contents($plugin_root . '/uninstall.php')) ?: [] as $line) {
    $trimmed = ltrim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
        continue;
    }
    $uninstall_live_lines[] = $line;
}
$uninstall_source = implode("\n", $uninstall_live_lines);

$undeleted = [];
foreach (array_keys($option_writers) as $option) {
    if (!str_contains($uninstall_source, "delete_option('{$option}')")) {
        $undeleted[] = $option;
    }
}

assert_test(
    'every option the plugin writes is deleted by uninstall.php',
    $option_writers !== [] && $undeleted === [],
    sprintf(
        'options written: %d | left behind on uninstall: %s',
        count($option_writers),
        $undeleted === [] ? 'none' : implode(', ', $undeleted)
    )
);

// 15. Every registered block is named in docs/BLOCKS.md.
// The README said "16 server-rendered blocks" without naming one, so the whole editor surface was
// undocumented: a user could not find out that wm/lksg-esg-scorecard prints whatever number is
// typed into it rather than reading the LkSG plugin.
$blocks_doc = (string) @file_get_contents($plugin_root . '/docs/BLOCKS.md');
$registered = \ArnoldWender\SuiteHub\Gutenberg\Block_Manager::get_instance()->get_registered_blocks();
$undocumented_blocks = [];
foreach (array_keys($registered) as $block_name) {
    if (!str_contains($blocks_doc, $block_name)) {
        $undocumented_blocks[] = $block_name;
    }
}

assert_test(
    'every block in the Block_Manager registry is documented in docs/BLOCKS.md',
    '' !== $blocks_doc && $undocumented_blocks === [],
    sprintf(
        'registered: %d | missing from the document: %s',
        count($registered),
        $undocumented_blocks === [] ? 'none' : implode(', ', $undocumented_blocks)
    )
);

// 16. No file links to a REST route this plugin does not register.
// The CRA block defaulted to rest_url('wm-suite/v1/sbom'). That route does not exist — the only
// wm-suite/v1 route is the Borlabs consent bridge — so its "download SBOM" button always led to a
// 404, on a block whose whole purpose is to look like verifiable transparency.
// The constant is private, so read it the way a test may.
$hub_namespace     = (string) (new ReflectionClass(\ArnoldWender\SuiteHub\API\API_Controller::class))
    ->getConstant('REST_NAMESPACE');
$registered_routes = [];
$linked_routes     = [];
$scan_dirs         = [$plugin_root . '/src', $plugin_root . '/includes'];
foreach ($scan_dirs as $scan_dir) {
    if (!is_dir($scan_dir)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scan_dir)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        // Comment lines are dropped first: a comment explaining a removed route would otherwise
        // re-trigger this check, which is exactly what the note about the old sbom default did.
        $code_lines = [];
        foreach (preg_split('/\R/', (string) file_get_contents($file->getPathname())) ?: [] as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $code_lines[] = $line;
        }
        $source = implode("\n", $code_lines);

        // register_rest_route( 'ns/v1', '/route', ... ) and the REST_NAMESPACE constant form.
        preg_match_all('/register_rest_route\(\s*(?:self::REST_NAMESPACE|[\'"]([^\'"]+)[\'"])\s*,\s*[\'"]([^\'"]+)[\'"]/', $source, $reg);
        foreach ($reg[2] as $i => $route) {
            $namespace = '' !== $reg[1][$i] ? $reg[1][$i] : $hub_namespace;
            $registered_routes[] = trim($namespace, '/') . '/' . ltrim($route, '/');
        }

        // rest_url( 'ns/v1/route' ) — a link something will actually follow.
        preg_match_all('/rest_url\(\s*[\'"]([^\'"]+)[\'"]/', $source, $links);
        foreach ($links[1] as $link) {
            $linked_routes[trim($link, '/')] = $file->getFilename();
        }
    }
}

// Namespaces this plugin owns. A link into somebody else's namespace — the eraser form posts to
// wm-dsgvo-eraser/v1/request, which wm-dsgvo-privacy-eraser registers — is a cross-plugin
// dependency, not a dangling link, and is documented in docs/BLOCKS.md instead.
$own_namespaces = [];
foreach ($registered_routes as $route) {
    $own_namespaces[substr($route, 0, (int) strpos($route, '/', strpos($route, '/') + 1))] = true;
}

$dangling_routes = [];
foreach ($linked_routes as $link => $where) {
    $namespace = substr($link, 0, (int) strpos($link, '/', strpos($link, '/') + 1));
    if (!isset($own_namespaces[$namespace])) {
        continue;
    }
    $found = false;
    foreach ($registered_routes as $route) {
        // A link to the namespace root (rest_url('wm-suite/v1/')) is a base for JS to append to.
        if ($route === $link || str_starts_with($route, $link) || str_starts_with($link, $route)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $dangling_routes[] = "{$link} (in {$where})";
    }
}

assert_test(
    'every rest_url() link into a namespace this plugin owns points at a route it registers',
    $registered_routes !== [] && $dangling_routes === [],
    sprintf(
        'registered: %d | own namespaces: %s | links to routes that do not exist: %s',
        count($registered_routes),
        implode(', ', array_keys($own_namespaces)),
        $dangling_routes === [] ? 'none' : implode(', ', $dangling_routes)
    )
);

// TEST 17 (2026-09-17): no emoji in the documents that ship inside the package.
// `.distignore` keeps tests and tooling out of the ZIP but not CHANGELOG.md, README.md,
// SECURITY.md, CONTRIBUTING.md or llms.txt — those reach the customer, and a WM deliverable
// carries SVG icons, never emoji. wm-edu-privacy-portal and wm-salon-sovereign-hub already
// assert this; the hub is the plugin whose family sheets and docs the fleet copies, so it
// asserts it too. Seven emoji headings lived in this changelog until today.
$shipped_docs = ['CHANGELOG.md', 'README.md', 'README.de.md', 'README.es.md', 'SECURITY.md', 'CONTRIBUTING.md', 'llms.txt'];
$emoji_re     = '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u';
$emoji_hits   = [];
foreach ($shipped_docs as $doc) {
    $path = dirname(__DIR__) . '/' . $doc;
    if (!is_file($path)) {
        continue;   // not every plugin carries every document; a missing one is not a failure
    }
    if (preg_match($emoji_re, (string) file_get_contents($path), $m)) {
        $emoji_hits[] = $doc . ' (' . $m[0] . ')';
    }
}
assert_test(
    'the documents that ship inside the package carry no emoji',
    $emoji_hits === [],
    'emoji found in: ' . implode(', ', $emoji_hits)
);

// TEST 18 (2026-09-17): every icon a block template echoes goes through wp_kses(), and the
// allowlist covers every element the icon map draws with.
// Plugin Check reported the 22 bare `echo wm_suite_hub_icon()` calls as OutputNotEscaped. The
// second half matters as much as the first: an allowlist that forgets <rect> would make wp_kses
// strip the rectangles out of calendar, lock and pause SILENTLY — measured on a local WordPress install by
// removing it: exactly those three icons changed. tools/svg-kses-dom-probe.py covers the other
// risk (wp_kses lower-cases viewBox; Chromium adjusts it back when parsing text/html).
$bare_icon_echoes = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/Gutenberg/Blocks')) as $tpl) {
    if ($tpl->getFilename() !== 'render.php') {
        continue;
    }
    foreach (file($tpl->getPathname()) as $n => $line) {
        if (strpos($line, 'wm_suite_hub_icon(') !== false
            && !preg_match("/wp_kses\\(\\s*wm_suite_hub_icon\\([^)]*\\)\\s*,\\s*wm_suite_hub_icon_allowed_html\\(\\)\\s*\\)/", $line)) {
            $bare_icon_echoes[] = basename(dirname($tpl->getPathname())) . ':' . ($n + 1);
        }
    }
}
$icons_src = (string) file_get_contents(dirname(__DIR__) . '/src/Gutenberg/icons.php');
preg_match_all('/<([a-z]+)[\s>\/]/', $icons_src, $icon_tags);
$drawn_tags = array_values(array_diff(array_unique($icon_tags[1]), ['svg']));
$allow_body = (string) strstr($icons_src, 'function wm_suite_hub_icon_allowed_html');
$missing_tags = array_values(array_filter($drawn_tags, static fn(string $tag): bool => !preg_match("/'" . $tag . "'\\s*=>/", $allow_body)));
assert_test(
    'every block template echoes its icon through wp_kses() with an allowlist that covers every drawn element',
    $bare_icon_echoes === [] && $missing_tags === [] && count($drawn_tags) >= 3,
    'bare echoes: ' . ($bare_icon_echoes === [] ? 'none' : implode(', ', $bare_icon_echoes))
        . ' | drawn elements missing from the allowlist: ' . ($missing_tags === [] ? 'none' : implode(', ', $missing_tags))
        . ' | drawn: ' . implode(', ', $drawn_tags)
);

// TEST 19 (2026-09-17): the fleet update client (packages/plugin-updater) keeps every decision
// it makes — no request without an https endpoint, a dead or malformed source never reads as
// "up to date", a package whose SHA-256 or Ed25519 signature does not match is refused before
// WordPress unpacks it. Its own suite stubs WordPress functions this file also defines, so it
// runs in a separate process (argument array, no shell); the mutations in tests/mutations.php
// that touch the updater turn this suite red through here.
$updater_lines = [];
$updater_exit  = 1;
$updater_proc  = proc_open(
    [PHP_BINARY, __DIR__ . '/test-plugin-updater.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $updater_pipes
);
if (is_resource($updater_proc)) {
    $updater_lines = preg_split('/\R/', trim((string) stream_get_contents($updater_pipes[1]) . (string) stream_get_contents($updater_pipes[2]))) ?: [];
    fclose($updater_pipes[1]);
    fclose($updater_pipes[2]);
    $updater_exit = proc_close($updater_proc);
}
$updater_tail = (string) end($updater_lines);
assert_test(
    'the fleet update client passes its own suite (tests/test-plugin-updater.php)',
    0 === $updater_exit && (bool) preg_match('/TEST RESULTS: [1-9]\d* PASSED \| 0 FAILED/', $updater_tail),
    'exit ' . $updater_exit . ' · ' . $updater_tail . ' · ' . implode(' | ', preg_grep('/^\s+FAIL/', $updater_lines))
);

// TEST 20 (2026-11-19): the version, CycloneDX bom.json, and all 16 block.json editorScript manifests stay aligned.
// Since 1.2.1 the release version is read from the header instead of pinned here: the pinned '1.2.0' had to be
// edited by hand on every bump, and bom.json stayed on 1.2.0 under the 1.2.1 header until this test was rerun.
$main_php_src   = (string) file_get_contents($plugin_root . '/wm-suite-hub.php');
$readme_txt     = (string) file_get_contents($plugin_root . '/readme.txt');
$bom_decoded    = is_file($plugin_root . '/bom.json') ? json_decode((string) file_get_contents($plugin_root . '/bom.json'), true) : null;
$header_version = preg_match('/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$/m', $main_php_src, $hv_match) ? $hv_match[1] : '';
$const_version  = preg_match("/define\(\s*'WM_SUITE_HUB_VERSION',\s*'([^']+)'\s*\)/", $main_php_src, $cv_match) ? $cv_match[1] : '';
$missing_editor_scripts = [];
foreach ($all_blocks as $b_name => $d_name) {
    $b_meta = json_decode((string) file_get_contents($blocks_dir . '/' . $d_name . '/block.json'), true);
    if (!is_array($b_meta) || ($b_meta['editorScript'] ?? '') !== 'wm-suite-hub-blocks-editor' || ($b_meta['version'] ?? '') !== $header_version) {
        $missing_editor_scripts[] = $b_name;
    }
}
assert_test(
    'the header version is synchronized across the constant, readme.txt, bom.json (version and purl), and all 16 block.json editorScript manifests',
    $header_version !== ''
        && $const_version === $header_version
        && WM_SUITE_HUB_VERSION === $header_version
        && (bool) preg_match('/^Stable tag:\s*' . preg_quote($header_version, '/') . '\s*$/m', $readme_txt)
        && is_array($bom_decoded)
        && ($bom_decoded['metadata']['component']['version'] ?? '') === $header_version
        && ($bom_decoded['metadata']['component']['purl'] ?? '') === 'pkg:wordpress/wm-suite-hub@' . $header_version
        && $missing_editor_scripts === [],
    sprintf(
        'header %s | constant %s | suite %s | bom %s %s | blocks off: %s',
        $header_version,
        $const_version,
        WM_SUITE_HUB_VERSION,
        (string) ($bom_decoded['metadata']['component']['version'] ?? '-'),
        (string) ($bom_decoded['metadata']['component']['purl'] ?? '-'),
        $missing_editor_scripts === [] ? 'none' : implode(', ', $missing_editor_scripts)
    )
);

// TEST 21 (2026-11-18): Compiled Gutenberg editor bundle (build/index.js + build/index.asset.php) and Block_Manager::register_all_blocks().
$build_js_path    = $plugin_root . '/build/index.js';
$build_asset_path = $plugin_root . '/build/index.asset.php';
$build_js_src     = is_file($build_js_path) ? (string) file_get_contents($build_js_path) : '';
$unregistered_js  = [];
foreach (array_keys($all_blocks) as $b_name) {
    if (!str_contains($build_js_src, "'" . $b_name . "'") && !str_contains($build_js_src, '"' . $b_name . '"')) {
        $unregistered_js[] = $b_name;
    }
}
$mock_registered_scripts     = [];
$mock_script_translations    = [];
$mock_registered_blocks_meta = [];
$block_mgr->register_all_blocks();

assert_test(
    'Block_Manager registers wm-suite-hub-blocks-editor (build/index.js), JED script translations, and all 16 block metadata directories',
    is_file($build_js_path)
        && is_file($build_asset_path)
        && $unregistered_js === []
        && isset($mock_registered_scripts['wm-suite-hub-blocks-editor'])
        && str_ends_with((string) $mock_registered_scripts['wm-suite-hub-blocks-editor']['src'], 'build/index.js')
        && ($mock_script_translations['wm-suite-hub-blocks-editor']['domain'] ?? '') === 'wm-suite-hub'
        && count($mock_registered_blocks_meta) === 16,
    sprintf(
        'missing in build/index.js: %s | script registered: %s | metadata dirs: %d',
        $unregistered_js === [] ? 'none' : implode(', ', $unregistered_js),
        isset($mock_registered_scripts['wm-suite-hub-blocks-editor']) ? 'yes' : 'no',
        count($mock_registered_blocks_meta)
    )
);

// TEST 21b (2026-11-21): the editor script's version follows its content. index.asset.php held 1.1.5 through
// 1.2.1, so a browser that had cached the editor script kept the old controls after an update. The version is
// the first 20 hex digits of the MD5 of build/index.js, as wp-scripts writes it, and build/index.js is the
// byte copy of blocks-src/index.js the editor actually loads.
$build_asset_meta = is_file($build_asset_path) ? include $build_asset_path : [];
assert_test(
    'the editor script is registered with the content hash of build/index.js, a byte copy of blocks-src/index.js',
    is_array($build_asset_meta)
        && ($build_asset_meta['version'] ?? '') === substr((string) md5_file($build_js_path), 0, 20)
        && ($mock_registered_scripts['wm-suite-hub-blocks-editor']['ver'] ?? null) === $build_asset_meta['version']
        && md5_file($plugin_root . '/blocks-src/index.js') === md5_file($build_js_path),
    sprintf(
        'asset version %s | md5 prefix %s | registered ver %s | source equals build: %s',
        (string) ($build_asset_meta['version'] ?? '-'),
        substr((string) md5_file($build_js_path), 0, 20),
        var_export($mock_registered_scripts['wm-suite-hub-blocks-editor']['ver'] ?? null, true),
        md5_file($plugin_root . '/blocks-src/index.js') === md5_file($build_js_path) ? 'yes' : 'no'
    )
);

// TEST 22 (2026-11-18): Opt-in uninstall (D-UNINSTALL) preserves data by default and purges only when opted in, with REST /settings/lifecycle.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}
$mock_options['wm_suite_theme']                         = 'obsidian-dark';
$mock_options['wm_suite_hub_version']                   = '1.1.0';
$mock_options['wm_suite_hub_activated_at']              = '2026-11-18 12:00:00';
$mock_options['wm_suite_hub_delete_data_on_uninstall']  = 0;
$wpdb->queries                                          = [];

include $plugin_root . '/uninstall.php';

assert_test(
    'uninstall.php preserves options and wm_suite_audit_log by default when wm_suite_hub_delete_data_on_uninstall is false',
    ($mock_options['wm_suite_theme'] ?? '') === 'obsidian-dark'
        && ($mock_options['wm_suite_hub_version'] ?? '') === '1.1.0'
        && $wpdb->queries === [],
    'queries executed on default uninstall: ' . implode(' | ', $wpdb->queries)
);

$req_get_life = new WP_REST_Request('GET', '/wm-hub/v1/settings/lifecycle');
$res_get_life = $api_ctrl->get_lifecycle_setting($req_get_life)->get_data();
$req_set_life = new WP_REST_Request('POST', '/wm-hub/v1/settings/lifecycle');
$req_set_life->set_param('delete_data_on_uninstall', true);
$res_set_life = $api_ctrl->update_lifecycle_setting($req_set_life)->get_data();

assert_test(
    'API_Controller /settings/lifecycle reads default false, updates opt-in true, and seals lifecycle_setting_updated',
    ($res_get_life['delete_data_on_uninstall'] ?? null) === false
        && ($res_set_life['success'] ?? false) === true
        && ($res_set_life['delete_data_on_uninstall'] ?? false) === true
        && (bool) ($mock_options['wm_suite_hub_delete_data_on_uninstall'] ?? false) === true,
    'REST lifecycle state mismatch'
);

include $plugin_root . '/uninstall.php';

assert_test(
    'uninstall.php purges all options and drops wp_wm_suite_audit_log once wm_suite_hub_delete_data_on_uninstall is true',
    !isset($mock_options['wm_suite_theme'])
        && !isset($mock_options['wm_suite_hub_delete_data_on_uninstall'])
        && in_array('DROP TABLE IF EXISTS wp_wm_suite_audit_log;', $wpdb->queries, true),
    'remaining options or missing DROP TABLE: ' . implode(' | ', $wpdb->queries)
);

// TEST 23 (2026-11-18): Telemetry_Bridge registers WP 6.5+ Block Bindings (`wm-suite-hub/telemetry`) and GenerateBlocks 2.x Dynamic Tag (`wm_hub_telemetry`).
require_once $plugin_root . '/src/Gutenberg/Telemetry_Bridge.php';
$mock_block_bindings = [];
GenerateBlocks_Register_Dynamic_Tag::$registered = [];
$bridged_fired = false;
add_action('wm_suite_hub_telemetry_bridged', static function () use (&$bridged_fired): void {
    $bridged_fired = true;
});

$telemetry_bridge = \ArnoldWender\SuiteHub\Gutenberg\Telemetry_Bridge::get_instance();
$telemetry_bridge->register_bridges();

$binding_cb = $mock_block_bindings['wm-suite-hub/telemetry']['get_value_callback'] ?? null;
$gb_cb      = GenerateBlocks_Register_Dynamic_Tag::$registered['wm_hub_telemetry']['return'] ?? null;

assert_test(
    'Telemetry_Bridge registers wm-suite-hub/telemetry Block Bindings source and wm_hub_telemetry GenerateBlocks Dynamic Tag',
    $bridged_fired === true
        && is_callable($binding_cb)
        && is_callable($gb_cb)
        && call_user_func($binding_cb, ['key' => 'version']) === WM_SUITE_HUB_VERSION
        && call_user_func($binding_cb, ['key' => 'nonexistent_field']) === ''
        && call_user_func($gb_cb, ['field' => 'version']) === WM_SUITE_HUB_VERSION
        && apply_filters('generateblocks_dynamic_tag_replacement', 'fallback', ['tag' => 'wm_hub_telemetry', 'options' => ['field' => 'version']]) === WM_SUITE_HUB_VERSION
        && apply_filters('generateblocks_dynamic_tag_output', 'Post Title', ['source' => 'post'], 'Post Title') === 'Post Title'
        && $telemetry_bridge->filter_generateblocks_dynamic_tag_output('Post Title', ['source' => 'post'], 'Post Title') === 'Post Title'
        && $telemetry_bridge->filter_generateblocks_dynamic_tag_output('fallback', 'wm_hub_telemetry', ['field' => 'version']) === WM_SUITE_HUB_VERSION,
    'Block Bindings or GenerateBlocks Dynamic Tag registration failed'
);

// TEST 24 (2026-11-18): Multisite wp_initialize_site provisioning and non-frontend option autoload=false (R2-B8).
$mock_switched_blogs = [];
$mock_restored_blogs = 0;
$mock_dbdelta_sql    = [];
$GLOBALS['mock_network_active'] = false;
do_action('wp_initialize_site', (object) ['blog_id' => 7]);
$switched_when_not_network = $mock_switched_blogs;

$GLOBALS['mock_network_active'] = true;
unset($mock_options['wm_suite_hub_version'], $mock_options['wm_suite_hub_activated_at'], $mock_options['wm_suite_hub_delete_data_on_uninstall']);
$wpdb->prefix = 'wp_';
do_action('wp_initialize_site', (object) ['blog_id' => 7]);

assert_test(
    'wp_initialize_site hook provisions new Multisite sub-sites when network-activated and skips when not',
    has_action('wp_initialize_site')
        && $switched_when_not_network === []
        && $mock_switched_blogs === [7]
        && $mock_restored_blogs === 1
        && $wpdb->prefix === 'wp_'
        && !empty($mock_dbdelta_sql)
        && str_contains(end($mock_dbdelta_sql), 'wp_7_wm_suite_audit_log')
        && ($mock_options['wm_suite_hub_version'] ?? '') === WM_SUITE_HUB_VERSION
        && ($mock_option_autoloads['wm_suite_hub_version'] ?? '') === 'no'
        && ($mock_option_autoloads['wm_suite_hub_activated_at'] ?? '') === 'no'
        && ($mock_option_autoloads['wm_suite_hub_delete_data_on_uninstall'] ?? '') === 'no',
    sprintf(
        'switched=%s restored=%d prefix=%s dbdelta=%d',
        implode(',', $mock_switched_blogs),
        $mock_restored_blogs,
        $wpdb->prefix,
        count($mock_dbdelta_sql)
    )
);

$req_theme_opt = new WP_REST_Request('POST', '/wm-hub/v1/settings/theme');
$req_theme_opt->set_param('theme', 'obsidian-dark');
$req_theme_opt->set_param('scope', 'global');
$api_ctrl->update_theme_setting($req_theme_opt);

$req_life_opt = new WP_REST_Request('POST', '/wm-hub/v1/settings/lifecycle');
$req_life_opt->set_param('delete_data_on_uninstall', false);
$api_ctrl->update_lifecycle_setting($req_life_opt);

$src_autoload_violations = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . '/src')) as $src_file) {
    if ($src_file->getExtension() !== 'php') {
        continue;
    }
    $src_text = (string) file_get_contents($src_file->getPathname());
    if (preg_match_all('/update_option\([^;]+\)/', $src_text, $u_matches)) {
        foreach ($u_matches[0] as $call) {
            if (!preg_match('/,\s*(false|\'no\'|"no")\s*\)$/', $call)) {
                $src_autoload_violations[] = $src_file->getFilename() . ': ' . $call;
            }
        }
    }
}
assert_test(
    'Internal non-frontend options write with autoload=false across API_Controller and src/',
    ($mock_options['wm_suite_theme'] ?? '') === 'obsidian-dark'
        && ($mock_option_autoloads['wm_suite_theme'] ?? null) === false
        && ($mock_option_autoloads['wm_suite_hub_delete_data_on_uninstall'] ?? null) === false
        && $src_autoload_violations === [],
    'autoload violations: ' . implode(' | ', $src_autoload_violations)
);

// TEST 25 (2026-11-20): the hub removes a spoke's own top-level menu only when it lists that spoke under WM Suite.
// Hub 1.1.5 removed the menus of all 18 spokes unconditionally while register() re-added an entry only for the
// registered ones, so a spoke that did not register — every spoke still on the previous contract namespace after
// the 2026-11-20 rename — was left with no menu entry at all.
if (!function_exists('remove_menu_page')) {
    function remove_menu_page(string $menu_slug) {
        $GLOBALS['mock_removed_menus'][] = $menu_slug;
        return false;
    }
}
$saved_module_filters = [$mock_filters['wm_register_suite_module'] ?? [], $mock_filters['wm_suite_hub_registered_modules'] ?? []];
$mock_filters['wm_register_suite_module'] = [static fn(array $m): array => array_merge($m, [
    new Sample_Spoke_Adapter('wm-prototype-nda-gate', 'WM Prototype NDA Gate', '1.3.0', 'Click-wrap NDA', 'HEALTHY'),
])];
$mock_filters['wm_suite_hub_registered_modules'] = [];
$menu_registry = new Module_Registry();
[$mock_filters['wm_register_suite_module'], $mock_filters['wm_suite_hub_registered_modules']] = $saved_module_filters;

$GLOBALS['mock_removed_menus'] = [];
(new \ArnoldWender\SuiteHub\Admin\Admin_Menu($menu_registry, new Audit_Chainer()))->consolidate_standalone_spoke_menus();
$removed_menus = $GLOBALS['mock_removed_menus'];

// Every spoke register() can list must have its own menu slugs mapped, or it would show twice (its own menu and
// the hub's entry); a mapped spoke register() never lists would lose its menu for nothing.
$menu_ref       = new ReflectionClass(\ArnoldWender\SuiteHub\Admin\Admin_Menu::class);
$listed_spokes  = [];
foreach ($menu_ref->getConstant('CATEGORIES') as $menu_category) {
    $listed_spokes = array_merge($listed_spokes, array_keys($menu_category['modules']));
}
$mapped_spokes = array_keys($menu_ref->getConstant('ROOT_MENU_SLUGS'));
sort($listed_spokes);
sort($mapped_spokes);

assert_test(
    'Admin_Menu removes the own menu of a registered spoke only, and maps the own menu of every spoke it lists',
    $removed_menus === ['wm-prototype-nda-gate', 'wm-nda-dashboard'] && $listed_spokes === $mapped_spokes,
    'removed: ' . implode(', ', $removed_menus)
        . ' | listed, not mapped: ' . implode(', ', array_diff($listed_spokes, $mapped_spokes))
        . ' | mapped, not listed: ' . implode(', ', array_diff($mapped_spokes, $listed_spokes))
);

// TEST 25 b (2026-11-21, 1.2.4): a registered spoke's own sub-pages keep WM Suite open with the spoke's entry marked.
// Their parent is the menu consolidate_standalone_spoke_menus() removed, so until 1.2.4 nothing was highlighted on
// them. menu-header.php resets the parent through get_admin_page_parent() right after the filter, which is why the
// removed root must also be mapped in $_wp_real_parent_file (WordPress 7.1, wp-admin/menu-header.php:43-55).
$GLOBALS['_wp_real_parent_file'] = [];
$adopting_menu  = new \ArnoldWender\SuiteHub\Admin\Admin_Menu($menu_registry, new Audit_Chainer());
$adopted_parent = $adopting_menu->adopt_spoke_subpages('wm-nda-dashboard');
$adopted_entry  = $adopting_menu->mark_adopted_spoke_entry(null);
$adopted_map    = $GLOBALS['_wp_real_parent_file'];
// A spoke that did not register keeps its own menu, and core pages are never touched.
$GLOBALS['_wp_real_parent_file'] = [];
$plain_menu         = new \ArnoldWender\SuiteHub\Admin\Admin_Menu($menu_registry, new Audit_Chainer());
$unregistered_kept  = 'wm-sentinel-stream' === $plain_menu->adopt_spoke_subpages('wm-sentinel-stream');
$core_kept          = 'options-general.php' === $plain_menu->adopt_spoke_subpages('options-general.php');
$plain_entry        = $plain_menu->mark_adopted_spoke_entry('options-reading.php');
$plain_map          = $GLOBALS['_wp_real_parent_file'];
unset($GLOBALS['_wp_real_parent_file']);
$adopt_hooked = in_array([$adopting_menu, 'adopt_spoke_subpages'], $mock_filters['parent_file'] ?? [], true)
    && in_array([$adopting_menu, 'mark_adopted_spoke_entry'], $mock_filters['submenu_file'] ?? [], true);
assert_test(
    'a registered spoke\'s sub-pages open WM Suite and mark its entry; other parents stay as WordPress resolved them',
    'wm-suite-hub' === $adopted_parent
        && 'wm-prototype-nda-gate' === $adopted_entry
        && ['wm-nda-dashboard' => 'wm-suite-hub'] === $adopted_map
        && $unregistered_kept && $core_kept
        && 'options-reading.php' === $plain_entry
        && [] === $plain_map
        && $adopt_hooked,
    sprintf('parent %s, entry %s, map %s, unregistered kept %s, core kept %s, plain entry %s, plain map %s, hooked %s',
        var_export($adopted_parent, true), var_export($adopted_entry, true), json_encode($adopted_map),
        var_export($unregistered_kept, true), var_export($core_kept, true), var_export($plain_entry, true),
        json_encode($plain_map), var_export($adopt_hooked, true))
);

// TEST 26 (2026-11-20): no surface of the hub names BSI IT-Grundschutz. Neither the hub nor any WM Suite plugin
// has been assessed against it; the cockpit badge, the Borlabs Cookie service text of Activity Sentinel and the
// solution-pack manifest said so until 1.2.0. The compiled .mo files are scanned too: they are what WordPress reads.
$grundschutz_hits = [];
foreach (['src', 'assets/data', 'languages'] as $grundschutz_dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . '/' . $grundschutz_dir, FilesystemIterator::SKIP_DOTS)) as $grundschutz_file) {
        if (stripos((string) file_get_contents($grundschutz_file->getPathname()), 'Grundschutz') !== false) {
            $grundschutz_hits[] = $grundschutz_dir . '/' . $grundschutz_file->getFilename();
        }
    }
}
assert_test(
    'no hub surface claims BSI IT-Grundschutz (source, solution-pack manifest, catalogs, compiled catalogs)',
    $grundschutz_hits === [],
    'still named in: ' . implode(', ', array_slice($grundschutz_hits, 0, 6))
);

// TEST 27 (2026-11-20): nothing the package ships carries an emoji, and every WM Suite menu entry draws a known icon.
// Until 1.2.1 the sidebar menu (some twenty entries, on the admin screen of every spoke), the cockpit, the
// marketplace and both dashboard widgets opened their labels with emoji. A screen reader reads each one out as a
// word before the label, and nine translatable strings carried them into 25 locales. TEST 17 covers the root
// documents; this covers code, styles, scripts and catalogs. The compiled .mo files are left out: they are
// binary, so a byte search would also hit offsets, and they are compiled from the .po files scanned here.
// A file that is not valid UTF-8 makes preg_match() return false; it is reported, never read as clean.
$shipped_emoji = [];
$shipped_files = [];
foreach (['src', 'includes', 'assets', 'build', 'languages', 'packages'] as $shipped_dir) {
    if (!is_dir($plugin_root . '/' . $shipped_dir)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . '/' . $shipped_dir, FilesystemIterator::SKIP_DOTS)) as $shipped_file) {
        if (in_array(strtolower($shipped_file->getExtension()), ['php', 'js', 'css', 'json', 'md', 'txt', 'po', 'pot', 'svg', 'html'], true)) {
            $shipped_files[] = substr($shipped_file->getPathname(), strlen($plugin_root) + 1);
        }
    }
}
foreach (array_merge($shipped_files, ['wm-suite-hub.php', 'uninstall.php', 'readme.txt']) as $shipped_rel) {
    $emoji_found = preg_match($emoji_re, (string) file_get_contents($plugin_root . '/' . $shipped_rel), $em_match);
    if ($emoji_found === 1) {
        $shipped_emoji[] = $shipped_rel . ' (' . $em_match[0] . ')';
    } elseif ($emoji_found === false) {
        $shipped_emoji[] = $shipped_rel . ' (not valid UTF-8)';
    }
}
$unknown_icons = [];
foreach ($menu_ref->getConstant('CATEGORIES') as $menu_cat_key => $menu_category) {
    if (wm_suite_hub_icon((string) ($menu_category['icon'] ?? '')) === '') {
        $unknown_icons[] = $menu_cat_key;
    }
    foreach ($menu_category['modules'] as $menu_spoke => $menu_meta) {
        if (wm_suite_hub_icon((string) ($menu_meta['icon'] ?? '')) === '') {
            $unknown_icons[] = $menu_spoke;
        }
    }
}
$sample_label = \ArnoldWender\SuiteHub\Admin\Admin_Menu::menu_label('chart', 'Cockpit & Status');
assert_test(
    'nothing the package ships carries an emoji, and every WM Suite menu entry draws a known icon before plain text',
    count($shipped_files) > 100
        && $shipped_emoji === []
        && $unknown_icons === []
        && str_starts_with($sample_label, '<svg class="wm-block-icon"')
        && str_ends_with($sample_label, '</svg> ' . esc_html('Cockpit & Status')),
    sprintf(
        'scanned %d files | emoji in: %s | entries with an unknown icon: %s',
        count($shipped_files) + 3,
        $shipped_emoji === [] ? 'none' : implode(', ', array_slice($shipped_emoji, 0, 6)),
        $unknown_icons === [] ? 'none' : implode(', ', $unknown_icons)
    )
);

// TEST 28 (2026-11-21): the dashboard widgets show what the registry reports. Until 1.2.2 the governance widget
// printed a fixed table ("100% Konform", "SBOM v1.6 Aktiv", "6/6 Bestanden") whether its six plugins were
// installed or not, the cockpit widget showed the module count over a fixed 18, and every module dot was green.
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string { return 'http://localhost:8080/wp-admin/' . ltrim($path, '/'); }
}
$saved_module_filters_dw = [$mock_filters['wm_register_suite_module'] ?? [], $mock_filters['wm_suite_hub_registered_modules'] ?? []];
$mock_filters['wm_register_suite_module'] = [static fn(array $m): array => array_merge($m, [
    new Sample_Spoke_Adapter('wm-cra-sbom-beacon', 'WM CRA SBOM Beacon', '1.0.0', 'SBOM', 'HEALTHY'),
    new Sample_Spoke_Adapter('wm-dora-ict-register', 'WM DORA ICT Register', '1.0.0', 'DORA', 'DEGRADED'),
])];
$mock_filters['wm_suite_hub_registered_modules'] = [];
$widget_registry = new Module_Registry();
[$mock_filters['wm_register_suite_module'], $mock_filters['wm_suite_hub_registered_modules']] = $saved_module_filters_dw;
Health_Matrix::flush_cache();
$hub_widgets = new \ArnoldWender\SuiteHub\Admin\Dashboard_Widgets($widget_registry, new Audit_Chainer());
ob_start();
$hub_widgets->render_governance_widget();
$governance_html = (string) ob_get_clean();
ob_start();
$hub_widgets->render_cockpit_widget();
$cockpit_widget_html = (string) ob_get_clean();
Health_Matrix::flush_cache();
assert_test(
    'the dashboard widgets list the registered plugins with the health each reports, and no fixed conformity table',
    str_contains($governance_html, 'Teilweise aktiv (2/6)')
        && substr_count($governance_html, 'Nicht aktiv') === 4
        && str_contains($governance_html, 'CRA · WM CRA SBOM Beacon')
        && str_contains($governance_html, 'wm-badge-green">Gesund')
        && str_contains($governance_html, 'wm-badge-amber">Eingeschränkt')
        && !str_contains($governance_html, '>HEALTHY<')
        && !str_contains($governance_html, '>DEGRADED<')
        && str_contains($governance_html, 'Keine Konformitätsbewertung')
        && !str_contains($governance_html, '100% Konform')
        && !str_contains($governance_html, 'Bestanden')
        && str_contains($cockpit_widget_html, 'class="wm-dash-kpi-val">2</div>')
        && !str_contains($cockpit_widget_html, '/ 18')
        && str_contains($cockpit_widget_html, 'background:#10b981;" role="img" aria-label="Gesund"')
        && str_contains($cockpit_widget_html, 'background:#f59e0b;" role="img" aria-label="Eingeschränkt"'),
    'governance: ' . preg_replace('/\s+/', ' ', strip_tags($governance_html))
);

// TEST 29 (2026-11-21): the cockpit and the Solution Packs view it includes are one page, so no id may repeat.
// Until 1.2.2 the solution-pack dialog reused wm-modal-title and wm-modal-body-content, the ids of the cockpit's
// own dialog, and the audit verification wrote its result into the hidden copy. Nothing offers an install either:
// the route answers 501 without dry_run, and the two install buttons sent exactly that request.
$views_src = (string) file_get_contents($plugin_root . '/src/Admin/Views/cockpit-view.php')
    . (string) file_get_contents($plugin_root . '/src/Admin/Views/marketplace-view.php');
preg_match_all('/\sid="([^"<]+)"/', $views_src, $view_ids);
$id_counts    = array_count_values($view_ids[1]);
$repeated_ids = array_keys(array_filter($id_counts, static fn(int $n): bool => $n > 1));
$cockpit_js   = (string) file_get_contents($plugin_root . '/assets/js/wm-cockpit-vanilla.js');
preg_match_all("/getElementById\\('([^']+)'\\)/", $cockpit_js, $js_ids);
$unresolved_js = array_values(array_filter(array_unique($js_ids[1]), static fn(string $id): bool => ($id_counts[$id] ?? 0) !== 1));
assert_test(
    'no id repeats across the cockpit and Solution Packs views, every id the cockpit script looks up exists once, and nothing offers an install',
    count($id_counts) >= 20
        && $repeated_ids === []
        && count(array_unique($js_ids[1])) >= 10
        && $unresolved_js === []
        && !str_contains($views_src . $cockpit_js, 'wm-btn-install-bundle')
        && !str_contains($views_src . $cockpit_js, 'wm-btn-modal-execute')
        && !str_contains($cockpit_js, 'dry_run: false')
        && !str_contains($cockpit_js, 'dry_run: dryRun'),
    'repeated ids: ' . ($repeated_ids === [] ? 'none' : implode(', ', $repeated_ids))
        . ' | looked up but not present exactly once: ' . ($unresolved_js === [] ? 'none' : implode(', ', $unresolved_js))
);

// TEST 30 (2026-11-21): WP-CLI reports success only for what it did. Until 1.2.2 `package verify-signatures` printed
// "INTACT — SIGNED" for every installed folder without reading a signature, and `bundle install` announced
// "erfolgreich bereitgestellt (N Pakete atomar installiert)" while installing nothing.
$hub_cli   = new \ArnoldWender\SuiteHub\CLI\WM_Hub_CLI(\ArnoldWender\SuiteHub\Core\Hub_Orchestrator::get_instance());
$cli_types = static fn(array $log): array => array_column($log, 0);
$cli_text  = static fn(array $log): string => implode("\n", array_column($log, 1));
WP_CLI::$log = [];
$hub_cli->package(['verify-signatures'], []);
$verify_log = WP_CLI::$log;
WP_CLI::$log = [];
$hub_cli->bundle(['install', 'security-legal'], []);
$install_log = WP_CLI::$log;
WP_CLI::$log = [];
$hub_cli->deduplicate_telemetry([], []);
$dedup_cli_log = WP_CLI::$log;
WP_CLI::$log = [];
// The install subcommand runs the pre-flight first; which error it ends with depends on this machine.
$cli_preflight_passed = (bool) \ArnoldWender\SuiteHub\Marketplace\Auditor\Preflight_Auditor::audit(Manifest_Parser::load_default_manifest()['security-legal'])['passed'];
assert_test(
    'wp wm-hub package verify-signatures and bundle install fail instead of reporting a check or an install that did not happen',
    !in_array('success', $cli_types($verify_log), true)
        && in_array('error', $cli_types($verify_log), true)
        && str_contains($cli_text($verify_log), 'Keine Signatur geprüft')
        && !str_contains($cli_text($verify_log), 'SIGNED')
        && !in_array('success', $cli_types($install_log), true)
        && in_array('error', $cli_types($install_log), true)
        && str_contains($cli_text($install_log), $cli_preflight_passed ? 'noch nicht verfügbar' : 'Pre-Flight Überprüfung fehlgeschlagen')
        && !str_contains($cli_text($install_log), 'bereitgestellt')
        && $dedup_cli_log !== []
        && !str_contains($cli_text($dedup_cli_log), 'TTFB'),
    'verify: ' . $cli_text($verify_log) . ' | install: ' . $cli_text($install_log)
);

// TEST 31 (2026-11-21): the deduplicator counts the bytes of the files it drops. Until 1.2.2 it added a fixed
// estimate per rule (24 500 bytes for the token sheet) and derived a TTFB gain of 0.45 ms per KB from it.
$dedup_plugins_dir = sys_get_temp_dir() . '/wm-dedup-probe-' . getmypid();
@mkdir($dedup_plugins_dir . '/wm-probe-spoke/assets', 0775, true);
file_put_contents($dedup_plugins_dir . '/wm-probe-spoke/assets/tokens.css', str_repeat('a', 1234));
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', $dedup_plugins_dir);
}
if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string {
        return 'http://localhost:8080/wp-content/plugins' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
}
$wp_styles->queue      = ['wm-obsidian-glass', 'wm-tokens-shared', 'wm-glassmorphism-theme'];
$wp_styles->registered = [
    'wm-tokens-shared'       => (object) ['src' => 'http://localhost:8080/wp-content/plugins/wm-probe-spoke/assets/tokens.css?ver=1.0.0'],
    'wm-glassmorphism-theme' => (object) ['src' => 'https://cdn.example.com/glass.css'],
];
$dedup_before = $deduplicator->get_telemetry();
$deduplicator->deduplicate_assets();
$dedup_after = $deduplicator->get_telemetry();
assert_test(
    'Asset_Deduplicator adds the size on disk of a dropped local file, 0 for a foreign source, and reports no timing',
    WP_PLUGIN_DIR === $dedup_plugins_dir
        && $dedup_after['bytes_saved'] - $dedup_before['bytes_saved'] === 1234
        && $dedup_after['styles_deduplicated'] - $dedup_before['styles_deduplicated'] === 2
        && !array_key_exists('estimated_ttfb_improvement_ms', $dedup_after)
        && $wp_styles->queue === ['wm-obsidian-glass'],
    sprintf(
        'bytes added %d (expected 1234) | styles dropped %d | queue %s | keys %s',
        $dedup_after['bytes_saved'] - $dedup_before['bytes_saved'],
        $dedup_after['styles_deduplicated'] - $dedup_before['styles_deduplicated'],
        implode(',', $wp_styles->queue),
        implode(',', array_keys($dedup_after))
    )
);
@unlink($dedup_plugins_dir . '/wm-probe-spoke/assets/tokens.css');
@rmdir($dedup_plugins_dir . '/wm-probe-spoke/assets');
@rmdir($dedup_plugins_dir . '/wm-probe-spoke');
@rmdir($dedup_plugins_dir);

// TEST 32 (2026-11-21): no surface of the hub states again a conformity, a seal, a measurement or a result that
// 1.2.2 removed. The scan reads what the plugin prints: PHP string literals and inline HTML (comments are neither),
// the scripts without their comments, block titles and descriptions, and every value of the solution-pack
// manifest. A comment that records what was removed therefore does not trip it.
$removed_claims = [
    'AAA', 'TTFB', '100% Konform', 'Aktiv & Konform', 'Konforme E-Rechnung', '§ 12 konform', 'Konform nach',
    'Versiegelt', 'utomatisch verifiziert', 'RSA-4096', 'erfolgreich verschlüsselt', 'kryptografisch auditiert',
    'zzgl. 19', '/kasse/', 'wm_book_event', 'Gültige Peppol', 'XRechnung 4.0', 'vorab blockiert', 'Weinberg Campus',
    'Transparenzbericht', 'verbindlich buchen', 'Verbindlich Installieren', '#book-event', 'Warteliste',
    'revisionssicher', 'erfüllt die Anforderungen', 'WCAG 2.2 A', 'August 2026', '6/6 Bestanden', 'SBOM v1.6 Aktiv',
    'Zero-Retention', 'INTACT', 'erfolgreich bereitgestellt', 'atomar installiert', '1-Click', 'FORENSISCHE',
    'Master-Schlüssel', 'ANNEX I', 'BFSG 2025 High', 'BFSG · CRA', 'Compliance-Konformität', ' / 18<',
    'estimated_bytes', 'estimated_ttfb', 'bfsg_compliant',
    // 1.2.3: the chain is linear (no Merkle tree), the checklist was not computed, the badge said "Nominal"
    // whatever the matrix reported, and a changed AUTH_SALT is not proof of tampering.
    'Merkle', 'SOUVERÄNITÄT', 'System Nominal', 'Forensisch', 'Manipulation erkannt', 'Kryptographischen Audit',
    'HMAC-SHA256-Merkle-Intact', 'CRA & SBOM Hub',
];
$removed_claims_en = ['compliant', 'complying', 'conformity', 'certified', 'cryptographic', 'Merkle', 'real-time', 'Real-time', 'live seat', 'legally', 'sealed', 'zero-knowledge', '60fps', 'verified', 'blocked', 'guarantee', 'audited', 'BFSGV'];
$claim_hits    = [];
$claim_sources = 0;
$scan_texts    = static function (string $where, array $texts, array $phrases) use (&$claim_hits): void {
    foreach ($texts as $text) {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                $claim_hits[] = $where . ' [' . $phrase . ']';
            }
        }
    }
};
$php_sources = [$plugin_root . '/includes/helpers.php', $plugin_root . '/wm-suite-hub.php'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . '/src', FilesystemIterator::SKIP_DOTS)) as $claim_file) {
    if ($claim_file->getExtension() === 'php') {
        $php_sources[] = $claim_file->getPathname();
    } elseif ($claim_file->getFilename() === 'block.json') {
        $claim_meta = json_decode((string) file_get_contents($claim_file->getPathname()), true);
        $claim_sources++;
        $scan_texts(basename(dirname($claim_file->getPathname())) . '/block.json', [(string) ($claim_meta['title'] ?? ''), (string) ($claim_meta['description'] ?? '')], array_merge($removed_claims, $removed_claims_en));
        // The booking button lost its e-invoicing note in 1.2.2; its inserter keyword went with it (the Peppol
        // block keeps e-invoicing terms, because that is its subject).
        if (basename(dirname($claim_file->getPathname())) === 'event-booking-cta') {
            $scan_texts('event-booking-cta/block.json keywords', array_map('strval', (array) ($claim_meta['keywords'] ?? [])), ['e-rechnung']);
        }
    }
}
foreach ($php_sources as $claim_php) {
    $claim_strings = [];
    foreach (token_get_all((string) file_get_contents($claim_php)) as $claim_token) {
        if (is_array($claim_token) && in_array($claim_token[0], [T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML, T_ENCAPSED_AND_WHITESPACE], true)) {
            $claim_strings[] = $claim_token[1];
        } elseif (is_array($claim_token) && $claim_token[0] === T_STRING && $claim_token[1] === 'is_bfsg_compliant') {
            $claim_strings[] = 'bfsg_compliant';
        }
    }
    $claim_sources++;
    $scan_texts(substr($claim_php, strlen($plugin_root) + 1), $claim_strings, $removed_claims);
}
foreach (['assets/js/wm-blocks-interactivity.js', 'assets/js/wm-cockpit-vanilla.js', 'build/index.js'] as $claim_js) {
    $js_code = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($plugin_root . '/' . $claim_js));
    $js_code_lines = array_filter(preg_split('/\R/', (string) $js_code) ?: [], static fn(string $l): bool => !str_starts_with(ltrim($l), '//'));
    $claim_sources++;
    $scan_texts($claim_js, $js_code_lines, $removed_claims);
}
$manifest_values          = [];
$manifest_data_for_claims = json_decode((string) file_get_contents($plugin_root . '/assets/data/wm-bundles-manifest.json'), true) ?: [];
array_walk_recursive($manifest_data_for_claims, static function ($value, $key) use (&$manifest_values): void {
    $manifest_values[] = (string) $key . '=' . (is_scalar($value) ? (string) $value : '');
});
$claim_sources++;
$scan_texts('assets/data/wm-bundles-manifest.json', $manifest_values, array_merge($removed_claims, ['tagline', 'mandate', 'synergies', 'features', 'ed25519_signature', 'sha256_hash', 'cyclonedx_purl']));
// 73 sources on 2026-11-21 (53 PHP files, 16 block.json, 3 scripts, the manifest); the floor keeps a scan that
// stopped finding its files from reporting green.
assert_test(
    'no string the hub prints, no script, no block title or description and no manifest value repeats a claim removed in 1.2.2 or 1.2.3',
    $claim_sources >= 70 && $claim_hits === [],
    'sources scanned: ' . $claim_sources . ' | hits: ' . implode(', ', array_slice($claim_hits, 0, 10))
);

// TEST 33 (2026-11-21): every block setting in the editor is an attribute block.json declares and render.php reads,
// and the reverse. Until 1.2.2 the grid offered a category filter on a column the events table does not have and a
// column count no stylesheet read, and three blocks kept panels for attributes their render ignored.
preg_match_all("/registerBlockType\\(\\s*'([^']+)'\\s*,\\s*createSuiteBlock\\(\\s*'[^']+'\\s*,\\s*\\[(.*?)\\]\\s*\\)\\s*\\);/s", $build_js_src, $editor_blocks, PREG_SET_ORDER);
$setting_mismatches = [];
foreach ($editor_blocks as [, $editor_block, $editor_fields]) {
    preg_match_all("/key:\\s*'([^']+)'/", $editor_fields, $editor_keys);
    $editor_dir    = $blocks_dir . '/' . substr($editor_block, 3);
    $editor_meta   = json_decode((string) @file_get_contents($editor_dir . '/block.json'), true);
    $editor_render = (string) @file_get_contents($editor_dir . '/render.php');
    $declared      = array_keys((array) ($editor_meta['attributes'] ?? []));
    $editor_set    = $editor_keys[1];
    sort($declared);
    sort($editor_set);
    if ($declared !== $editor_set) {
        $setting_mismatches[] = $editor_block . ' editor [' . implode(',', $editor_set) . '] vs block.json [' . implode(',', $declared) . ']';
    }
    foreach ($editor_set as $editor_key) {
        if (!str_contains($editor_render, "\$attributes['" . $editor_key . "']")) {
            $setting_mismatches[] = $editor_block . ' render.php never reads ' . $editor_key;
        }
    }
}
$blocks_css = (string) file_get_contents($plugin_root . '/assets/css/wm-blocks-core.css');
assert_test(
    'every editor setting of the 16 blocks is a declared attribute its render reads, and the grid column count reaches the stylesheet',
    count($editor_blocks) === 16
        && $setting_mismatches === []
        && str_contains($blocks_css, 'var(--wm-grid-cols')
        && str_contains($html_grid_live, '--wm-grid-cols: 3;'),
    'editor blocks parsed: ' . count($editor_blocks) . ' | mismatches: ' . ($setting_mismatches === [] ? 'none' : implode(' | ', $setting_mismatches))
);

// Front-end script and styles: a browser that blocks site data no longer stops every block script (localStorage
// threw before the ticker, the Peppol check and the placeholder were set up), the placeholder inserts a <template>
// whose content has not loaded yet, and reduced motion leaves the ticker text readable instead of off-screen.
$front_js       = (string) file_get_contents($plugin_root . '/assets/js/wm-blocks-interactivity.js');
$unguarded_ls   = [];
$guarded_ls     = 0;
foreach (preg_split('/\R/', $front_js) ?: [] as $front_n => $front_line) {
    if (!str_contains($front_line, 'localStorage.') || str_starts_with(ltrim($front_line), '//')) {
        continue;
    }
    if (str_contains($front_line, 'try {')) {
        $guarded_ls++;
    } else {
        $unguarded_ls[] = $front_n + 1;
    }
}
assert_test(
    'the block script reaches localStorage only inside try, inserts a template on load, and reduced motion stops the ticker in place',
    $guarded_ls === 3
        && $unguarded_ls === []
        && str_contains($front_js, "target.tagName === 'TEMPLATE'")
        && str_contains($front_js, 'document.importNode(target.content, true)')
        && !str_contains($front_js, 'initWhistleblowerWizard')
        && (bool) preg_match('/@media \(prefers-reduced-motion: reduce\) \{[^@]*\.wm-newsticker-track \{\s*animation: none;/', $blocks_css),
    'unguarded localStorage on lines: ' . ($unguarded_ls === [] ? 'none' : implode(', ', $unguarded_ls)) . ' | guarded: ' . $guarded_ls
);

// TEST 34 (2026-11-21): the block assets carry the plugin version and print no credentials. Until 1.2.2 they were
// pinned to 1.1.1, so browsers kept serving the stylesheet and script of that release after every update, and a
// REST nonce, the REST base and the admin-ajax URL went into every page with a block although no script read them.
if (!class_exists('WP_Post')) {
    class WP_Post {
        public string $post_content = '';
    }
}
if (!function_exists('has_block')) {
    function has_block(string $block_name, $post = null): bool {
        return $post instanceof WP_Post && str_contains($post->post_content, '<!-- wp:' . $block_name . ' ');
    }
}
$GLOBALS['mock_enqueued'] = ['styles' => [], 'scripts' => [], 'localized' => []];
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void {
        $GLOBALS['mock_enqueued']['styles'][$handle] = ['src' => $src, 'ver' => $ver];
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, $args = []): void {
        $GLOBALS['mock_enqueued']['scripts'][$handle] = ['src' => $src, 'ver' => $ver];
    }
}
if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool {
        $GLOBALS['mock_enqueued']['localized'][$handle][$object_name] = $l10n;
        return true;
    }
}
$asset_engine = \ArnoldWender\SuiteHub\Gutenberg\Zero_Duplication_Asset_Engine::get_instance();
$post = new WP_Post();
$post->post_content = '<!-- wp:paragraph --><p>Ohne Suite-Block</p><!-- /wp:paragraph -->';
$asset_engine->conditional_enqueue_assets();
$enqueued_without_block = $GLOBALS['mock_enqueued'];
$post->post_content = '<!-- wp:wm/newsticker-banner {"tickerText":"Hallo"} /-->';
$asset_engine->conditional_enqueue_assets();
// On the front end the editor hook adds nothing; conditional_enqueue_assets() decides there.
$asset_engine->enqueue_editor_assets();
$editor_style_on_front = isset($GLOBALS['mock_enqueued']['styles']['wm-blocks-editor-core']);
$GLOBALS['mock_is_admin'] = true;
$asset_engine->enqueue_editor_assets();
$GLOBALS['mock_is_admin'] = false;
$suite_data = $GLOBALS['mock_enqueued']['localized']['wm-blocks-interactivity']['wmSuiteData'] ?? [];
assert_test(
    'block assets load only with a WM block, carry the plugin version, and localize texts but no nonce or URL',
    $enqueued_without_block['styles'] === [] && $enqueued_without_block['scripts'] === []
        && ($GLOBALS['mock_enqueued']['styles']['wm-blocks-core']['ver'] ?? null) === WM_SUITE_HUB_VERSION
        && ($GLOBALS['mock_enqueued']['scripts']['wm-blocks-interactivity']['ver'] ?? null) === WM_SUITE_HUB_VERSION
        && ($GLOBALS['mock_enqueued']['styles']['wm-blocks-editor-core']['ver'] ?? null) === WM_SUITE_HUB_VERSION
        && array_keys($suite_data) === ['i18n']
        && !str_contains((string) json_encode($suite_data), 'nonce'),
    'enqueued: ' . json_encode($GLOBALS['mock_enqueued'])
);
// Until 1.2.6 the editor sheet hung on enqueue_block_editor_assets, which reaches only the admin page around the
// iframed canvas: every block rendered unstyled in the editor.
$hooks_before_assets = $GLOBALS['mock_actions'] ?? [];
$asset_engine->init();
$hook_names = static fn(string $tag): array => array_map(
    static fn($cb): string => is_array($cb) ? (string) $cb[1] : '',
    $GLOBALS['mock_actions'][$tag] ?? []
);
assert_test(
    'the block sheet reaches the iframed editor canvas (enqueue_block_assets), and on the front end that hook adds nothing',
    in_array('enqueue_editor_assets', $hook_names('enqueue_block_assets'), true)
        && !in_array('enqueue_editor_assets', $hook_names('enqueue_block_editor_assets'), true)
        && !$editor_style_on_front,
    'enqueue_block_assets: ' . json_encode($hook_names('enqueue_block_assets')) . ' editor style on front: ' . var_export($editor_style_on_front, true)
);
$GLOBALS['mock_actions'] = $hooks_before_assets;
unset($post);

// TEST 36 (1.2.3): what the cockpit says is what it measured, in words a person reads.
// a) Status enums reach the screen as words; an unknown status never reads as healthy.
$label_cases  = ['HEALTHY' => 'Gesund', 'healthy' => 'Gesund', 'DEGRADED' => 'Eingeschränkt', 'NEEDS_CONFIG' => 'Nicht konfiguriert', 'ERROR' => 'Fehler', 'WARNING' => 'Warnung', 'CRITICAL' => 'Kritisch', '' => 'Unbekannt', 'BOGUS' => 'Unbekannt'];
$label_misses = [];
foreach ($label_cases as $label_raw => $label_want) {
    $label_got = Health_Matrix::status_label((string) $label_raw);
    if ($label_got !== $label_want) {
        $label_misses[] = $label_raw . ' -> ' . $label_got;
    }
}
assert_test(
    'every health status reaches the screen as a word, and an unknown one never as "Gesund"',
    $label_misses === [],
    'wrong labels: ' . implode(', ', $label_misses)
);

// b) The runtime card renders the matrix environment. Until 1.2.3 it was a "SOUVERÄNITÄT & SECURITY" list of
// three ticks nothing computed, and the badge read "System Nominal" whatever the matrix reported.
if (!function_exists('selected')) {
    function selected($selected, $current = true, bool $display = true): string {
        $attr = (string) $selected === (string) $current ? ' selected="selected"' : '';
        if ($display) {
            echo $attr;
        }
        return $attr;
    }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, int $decimals = 0): string { return number_format((float) $number, $decimals, ',', '.'); }
}
if (!function_exists('checked')) {
    function checked($checked, $current = true, bool $display = true): string {
        $attr = (string) $checked === (string) $current ? ' checked="checked"' : '';
        if ($display) {
            echo $attr;
        }
        return $attr;
    }
}
$render_cockpit = static function (array $matrix) use ($plugin_root): string {
    ob_start();
    try {
        include $plugin_root . '/src/Admin/Views/cockpit-view.php';
    } catch (Throwable $render_error) {
        ob_end_clean();
        return 'RENDER-ERROR: ' . $render_error->getMessage();
    }
    return (string) ob_get_clean();
};
$cockpit_no_ssl = $render_cockpit(['global_status' => 'WARNING', 'environment' => ['php_version' => '8.3.99', 'wp_version' => '7.1', 'is_ssl' => false, 'has_object_cache' => false]]);
$cockpit_ssl    = $render_cockpit(['global_status' => 'HEALTHY', 'environment' => ['php_version' => '8.3.99', 'wp_version' => '7.1', 'is_ssl' => true, 'has_object_cache' => true]]);
preg_match('#<ul class="wm-security-checklist wm-runtime-list">(.*?)</ul>#s', $cockpit_no_ssl, $runtime_no_ssl);
preg_match('#<ul class="wm-security-checklist wm-runtime-list">(.*?)</ul>#s', $cockpit_ssl, $runtime_ssl);
assert_test(
    'the runtime card shows the measured environment: no HTTPS is a warning, nothing else carries a tick, and the badge says the matrix status',
    str_contains($runtime_no_ssl[1] ?? '', 'dashicons-warning wm-check-warn')
        && !str_contains($runtime_no_ssl[1] ?? '', 'wm-check-ok')
        && str_contains($runtime_no_ssl[1] ?? '', '8.3.99')
        && substr_count($runtime_ssl[1] ?? '', 'wm-check-ok') === 1
        && str_contains($cockpit_no_ssl, '<span>Warnung</span>')
        && !str_contains($cockpit_no_ssl, 'wm-status-dot-pulse')
        && str_contains($cockpit_ssl, 'wm-status-dot-pulse')
        && !str_contains($cockpit_no_ssl . $cockpit_ssl, 'SOUVERÄNITÄT')
        && !str_contains($cockpit_no_ssl . $cockpit_ssl, 'Nominal'),
    'runtime card: ' . preg_replace('/\s+/', ' ', strip_tags($runtime_no_ssl[1] ?? substr($cockpit_no_ssl, 0, 300)))
);

// c) Every text the cockpit script shows comes from the translated table PHP hands it, and that table carries
// nothing the script does not use. Until 1.2.3 the script printed German literals no catalog could reach.
$cockpit_js_36  = (string) file_get_contents($plugin_root . '/assets/js/wm-cockpit-vanilla.js');
$cockpit_js_code = (string) preg_replace('#/\*.*?\*/#s', '', $cockpit_js_36);
preg_match_all("/\\bt\\('([A-Za-z]+)'/", $cockpit_js_code, $js_t_keys);
$js_keys_used   = array_values(array_unique($js_t_keys[1]));
$script_strings = \ArnoldWender\SuiteHub\Core\Hub_Orchestrator::cockpit_script_strings();
$keys_missing   = array_values(array_diff($js_keys_used, array_keys($script_strings)));
$keys_unused    = array_values(array_diff(array_keys($script_strings), $js_keys_used));
preg_match_all("/(['\"`])(?:(?!\\1).)*[äöüÄÖÜß](?:(?!\\1).)*\\1/u", $cockpit_js_code, $js_german_literals);
assert_test(
    'the cockpit script shows only strings from its translated table, and the table holds no key the script ignores',
    count($js_keys_used) >= 30 && $keys_missing === [] && $keys_unused === [] && $js_german_literals[0] === [],
    'missing: ' . implode(', ', $keys_missing) . ' | unused: ' . implode(', ', $keys_unused) . ' | literals: ' . implode(' ', array_slice($js_german_literals[0], 0, 3))
);

// d) The verification walks the table in pages of 500 instead of loading it whole, and still finds a break.
$wpdb_before_keyset = $wpdb;
$wpdb = new wpdb();
$keyset_chainer = new Audit_Chainer();
for ($keyset_i = 0; $keyset_i < 1201; $keyset_i++) {
    $keyset_chainer->append_event('wm-suite-hub', 'keyset_probe', ['n' => $keyset_i]);
}
$wpdb->results_queries = [];
$keyset_intact = $keyset_chainer->verify_chain_integrity();
$keyset_pages  = array_values(array_filter($wpdb->results_queries, static fn(string $q): bool => str_contains($q, 'LIMIT 500')));
$wpdb->rows[776]['payload'] = '{"n":"changed"}';
$keyset_broken = $keyset_chainer->verify_chain_integrity();
$wpdb = $wpdb_before_keyset;
new Audit_Chainer(); // the singleton points at the suite's table again
assert_test(
    'the chain is verified in keyset pages of 500, every row is checked, and a changed row names both causes',
    $keyset_intact['intact'] === true
        && $keyset_intact['total_checked'] === 1201
        && count($keyset_pages) === 3
        && $keyset_broken['intact'] === false
        && $keyset_broken['broken_at_id'] === 777
        && str_contains($keyset_broken['details'], 'AUTH_SALT')
        && !str_contains($keyset_broken['details'], 'Manipulation'),
    sprintf('intact=%s checked=%d pages=%d broken_at=%s', var_export($keyset_intact['intact'], true), $keyset_intact['total_checked'], count($keyset_pages), var_export($keyset_broken['broken_at_id'], true))
);

// e) The dialogs are styled, the script does not move focus when no dialog is open, and the menu has no empty entry.
// Until 1.2.3 the pre-flight dialog carried two classes no sheet styled (it opened as a bare block at the end of the
// page), an Escape anywhere on the cockpit sent focus back to the last element a dialog had opened from, the busy
// icon class had no rule, and "CRA & SBOM Hub" was a submenu that opened the same cockpit.
$market_src_36 = (string) file_get_contents($plugin_root . '/src/Admin/Views/marketplace-view.php');
$all_css_36    = '';
foreach (glob($plugin_root . '/assets/css/*.css') ?: [] as $css_file_36) {
    $all_css_36 .= (string) file_get_contents($css_file_36);
}
// The dialog markup only (from the pre-flight dialog to the end of the view) and the script's templates.
$market_dialog_36 = (string) strstr($market_src_36, 'id="wm-preflight-modal"');
preg_match_all('/class="([^"]+)"/', $cockpit_js_code . $market_dialog_36, $class_attrs_36);
$classes_36 = [];
foreach ($class_attrs_36[1] as $class_list_36) {
    foreach (preg_split('/\s+/', $class_list_36) ?: [] as $class_36) {
        if (preg_match('/^wm-[a-z0-9_-]+$/', $class_36)) {
            $classes_36[$class_36] = true;
        }
    }
}
$classes_36['wm-spin'] = true;
$unstyled_36 = array_values(array_filter(array_keys($classes_36), static fn(string $c): bool => !preg_match('/\.' . preg_quote($c, '/') . '[\s,{:.\[>]/', $all_css_36)));
$menu_src_36 = (string) file_get_contents($plugin_root . '/src/Admin/Admin_Menu.php');
assert_test(
    'every class the dialogs and the cockpit script use has a rule, Escape only closes an open dialog, and no submenu repeats the cockpit',
    count($classes_36) >= 20
        && $unstyled_36 === []
        && (bool) preg_match('/\.wm-spin \{\s*animation: wmSpin/', $all_css_36)
        && str_contains($market_src_36, 'id="wm-preflight-modal" class="wm-modal-overlay"')
        && substr_count($cockpit_js_36, "!modal.classList.contains('is-active')) return;") === 2
        && !str_contains($cockpit_js_code, "document.addEventListener('keydown', (e) =>")
        && !str_contains($cockpit_js_code, 'style="')
        && !str_contains($menu_src_36, "'wm-suite-hub-sbom'"),
    'classes without a rule: ' . ($unstyled_36 === [] ? 'none' : implode(', ', $unstyled_36)) . ' | classes found: ' . count($classes_36)
);

// f) The colour follows the measurement. Until 1.2.3 the cockpit pill was green whatever word it carried
// («Kritisch» on green in the screenshot of 21.11.2026), the widget showed an empty audit table as «intakt» on
// green, and a broken chain carried is-alert, a class no sheet styles.
$cockpit_crit_36    = $render_cockpit(['global_status' => 'CRITICAL']);
$cockpit_unknown_36 = $render_cockpit(['global_status' => 'BOGUS']);
$wpdb_before_widget_36 = $wpdb;
$wpdb = new wpdb();
$widget_chainer_36 = new Audit_Chainer();
$widget_36 = new \ArnoldWender\SuiteHub\Admin\Dashboard_Widgets($widget_registry, $widget_chainer_36);
Health_Matrix::flush_cache();
ob_start();
$widget_36->render_cockpit_widget();
$widget_empty_36 = (string) ob_get_clean();
$widget_chainer_36->append_event('wm-suite-hub', 'widget_probe', ['n' => 1]);
$widget_chainer_36->append_event('wm-suite-hub', 'widget_probe', ['n' => 2]);
$wpdb->rows[1]['payload'] = '{"n":"changed"}';
ob_start();
$widget_36->render_cockpit_widget();
$widget_broken_36 = (string) ob_get_clean();
$wpdb = $wpdb_before_widget_36;
new Audit_Chainer(); // the singleton points at the suite's table again
Health_Matrix::flush_cache();
$widgets_src_36 = (string) file_get_contents($plugin_root . '/src/Admin/Dashboard_Widgets.php');
$colour_checks_36 = [
    'healthy pill plain'        => str_contains($cockpit_ssl, 'class="wm-system-status-badge"'),
    'warning pill amber'        => str_contains($cockpit_no_ssl, 'class="wm-system-status-badge is-warning"'),
    'unknown pill amber'        => str_contains($cockpit_unknown_36, 'class="wm-system-status-badge is-warning"'),
    'critical pill red'         => str_contains($cockpit_crit_36, 'class="wm-system-status-badge is-critical"'),
    'amber pill rule'           => (bool) preg_match('/\.wm-system-status-badge\.is-warning \{[^}]*--wm-warning\)/', $all_css_36),
    'red pill rule'             => (bool) preg_match('/\.wm-system-status-badge\.is-critical \{[^}]*--wm-danger\)/', $all_css_36),
    'empty table neutral'       => str_contains($widget_empty_36, 'class="wm-dash-banner"'),
    'empty table says empty'    => str_contains($widget_empty_36, 'Die Audit-Tabelle ist leer.'),
    'empty table not intakt'    => !str_contains($widget_empty_36, 'intakt'),
    'broken chain amber'        => str_contains($widget_broken_36, 'class="wm-dash-banner is-warning"'),
    'broken chain names entry'  => str_contains($widget_broken_36, 'Audit-Kette unterbrochen bei Eintrag #2'),
    // As a class in code (quoted), not the comment that records it.
    'no unstyled is-alert'      => !preg_match("/is-alert['\"]/", $widgets_src_36),
];
assert_test(
    'the cockpit pill is green only when healthy, and the widget shows an empty audit table as empty and a broken chain in amber',
    !in_array(false, $colour_checks_36, true),
    'failing: ' . implode(', ', array_keys(array_filter($colour_checks_36, static fn(bool $ok): bool => !$ok)))
);

// TEST 35 (2026-11-21): the pre-flight check changes nothing on disk. Until 1.2.2 it created wp-content/wm-backups
// on every dry run; a backup directory that does not exist yet is now judged by its parent. It runs last because
// WP_CONTENT_DIR, once defined, stays defined for every test after it.
$preflight_content_dir = sys_get_temp_dir() . '/wm-preflight-probe-' . getmypid();
@mkdir($preflight_content_dir, 0775, true);
@mkdir(WP_PLUGIN_DIR, 0775, true);
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', $preflight_content_dir);
}
$preflight_probe   = Preflight_Auditor::audit(Manifest_Parser::load_default_manifest()['security-legal']);
$preflight_created = is_dir(WP_CONTENT_DIR . '/wm-backups');
assert_test(
    'the pre-flight check creates no backup directory and judges a missing one by its parent',
    WP_CONTENT_DIR === $preflight_content_dir
        && !$preflight_created
        && ($preflight_probe['checks']['filesystem_writable']['passed'] ?? false) === true,
    'wm-backups created: ' . ($preflight_created ? 'yes' : 'no') . ' | check: ' . json_encode($preflight_probe['checks']['filesystem_writable'] ?? null)
);

// TEST 37 (2026-11-25): Activator::maybe_upgrade() upgrades schema on plugins_loaded without IF NOT EXISTS,
// and Admin_Menu maps edu, k9, social-care, and newsletter-suite root slugs cleanly.
unset($mock_options['wm_suite_hub_db_version']);
$mock_dbdelta_sql = [];
\ArnoldWender\SuiteHub\Setup\Activator::maybe_upgrade();
$upgraded_sql = (string) end($mock_dbdelta_sql);
$first_count  = count($mock_dbdelta_sql);
\ArnoldWender\SuiteHub\Setup\Activator::maybe_upgrade();
$second_count = count($mock_dbdelta_sql);
$root_slugs_37 = $menu_ref->getConstant('ROOT_MENU_SLUGS');
assert_test(
    'Activator::maybe_upgrade runs dbDelta cleanly on stale installs and Admin_Menu covers edu, k9, social-care & newsletter root slugs',
    1 === $first_count
        && 1 === $second_count
        && str_contains($upgraded_sql, 'CREATE TABLE ')
        && !str_contains($upgraded_sql, 'IF NOT EXISTS')
        && 2 === ($mock_options['wm_suite_hub_db_version'] ?? 0)
        && in_array('wm-newsletter-suite', $root_slugs_37['wm-dsgvo-newsletter-suite'] ?? [], true)
        && isset($root_slugs_37['wm-edu-privacy-portal'], $root_slugs_37['wm-k9-bh-exam-planner'], $root_slugs_37['wm-social-care-accessibility-hub']),
    'maybe_upgrade or Admin_Menu root slug invariant failed'
);
@rmdir(WP_CONTENT_DIR . '/wm-backups');
@rmdir($preflight_content_dir);
@rmdir(WP_PLUGIN_DIR);

echo "\n============================================================\n";
echo sprintf(" TEST RESULTS: \033[32m%d PASSED\033[0m | \033[31m%d FAILED\033[0m\n", $passed, $failed);
echo "============================================================\n\n";

exit($failed > 0 ? 1 : 0);





