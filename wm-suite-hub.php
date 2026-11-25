<?php
/**
 * Plugin Name:       WM Suite Hub (Enterprise Cockpit)
 * Plugin URI:        https://arnoldwender.com/wordpress-plugins/wm-suite-hub/
 * Description:       Registry and health overview of the WM Suite plugins, a CycloneDX 1.6 software bill of materials and an audit table whose entries are chained with HMAC-SHA256.
 * Version:           1.2.6
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      8.2
 * Author:            Arnold Wender
 * Author URI:        https://arnoldwender.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wm-suite-hub
 * Domain Path:       /languages
 *
 * @package           ArnoldWender\SuiteHub
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub;

use ArnoldWender\SuiteHub\Core\Hub_Orchestrator;
use ArnoldWender\SuiteHub\Setup\Activator;
use ArnoldWender\SuiteHub\Setup\Deactivator;

// Direct execution guard
if (!defined('ABSPATH')) {
    exit;
}

// Define core constants
if (!defined('WM_SUITE_HUB_VERSION')) {
    define('WM_SUITE_HUB_VERSION', '1.2.6');
}
if (!defined('WM_SUITE_HUB_FILE')) {
    define('WM_SUITE_HUB_FILE', __FILE__);
}
if (!defined('WM_SUITE_HUB_PATH')) {
    define('WM_SUITE_HUB_PATH', plugin_dir_path(__FILE__));
}
if (!defined('WM_SUITE_HUB_URL')) {
    define('WM_SUITE_HUB_URL', plugin_dir_url(__FILE__));
}

// Environment compatibility check (PHP 8.2+ and WordPress 6.4+)
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    add_action('admin_notices', function (): void {
        echo '<div class="notice notice-error"><p>' .
            esc_html__('WM Suite Hub requires PHP 8.2 or higher. Please upgrade your PHP runtime.', 'wm-suite-hub') .
            '</p></div>';
    });
    return;
}

// Built-in PSR-4 Autoloader for standalone operation without Composer build requirement
spl_autoload_register(function (string $class): void {
    $prefix = 'ArnoldWender\\SuiteHub\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Register activation and deactivation hooks
register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

// Provision new Multisite sub-sites automatically when network-activated
add_action('wp_initialize_site', function ($new_site): void {
    $blog_id = 0;
    if (is_object($new_site)) {
        if (isset($new_site->blog_id)) {
            $blog_id = (int) $new_site->blog_id;
        } elseif (isset($new_site->id)) {
            $blog_id = (int) $new_site->id;
        }
    } elseif (is_numeric($new_site)) {
        $blog_id = (int) $new_site;
    }

    if ($blog_id <= 0) {
        return;
    }

    if (!function_exists('is_plugin_active_for_network')) {
        $plugin_file_include = ABSPATH . 'wp-admin/includes/plugin.php';
        if (file_exists($plugin_file_include)) {
            require_once $plugin_file_include;
        }
    }

    $plugin_basename = function_exists('plugin_basename')
        ? plugin_basename(__FILE__)
        : 'wm-suite-hub/wm-suite-hub.php';

    if (!function_exists('is_plugin_active_for_network') || !is_plugin_active_for_network($plugin_basename)) {
        return;
    }

    if (!function_exists('switch_to_blog') || !function_exists('restore_current_blog')) {
        return;
    }

    switch_to_blog($blog_id);
    try {
        Activator::activate();
    } finally {
        restore_current_blog();
    }
}, 10, 1);

// Load Global Suite Helpers
require_once __DIR__ . '/includes/helpers.php';

// Boot the Hub Orchestrator
Hub_Orchestrator::get_instance()->init();



