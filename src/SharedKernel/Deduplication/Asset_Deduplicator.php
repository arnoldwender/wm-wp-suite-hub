<?php
/**
 * Class Asset_Deduplicator
 *
 * Intercepts frontend and admin script/style queues and drops a WM Suite plugin's own
 * copy of a shared asset when the canonical one is on the same page. The counters cover
 * the current request only; bytes_saved adds the size on disk of each dropped file,
 * read from its registered source (a source outside this installation counts 0).
 * Until 1.2.2 it added a fixed estimate per rule and derived a TTFB gain from it.
 *
 * @package ArnoldWender\SuiteHub\SharedKernel\Deduplication
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\SharedKernel\Deduplication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Asset_Deduplicator {

    /**
     * Known vendor and suite library patterns that should be consolidated.
     *
     * @var array<string, array{canonical_handle: string, canonical_src: string, canonical_type: string}>
     */
    private array $consolidation_map = [];

    /**
     * Counters of this request: dropped assets and the bytes of the dropped files on disk.
     *
     * @var array{scripts_deduplicated: int, styles_deduplicated: int, bytes_saved: int}
     */
    private array $metrics = [
        'scripts_deduplicated' => 0,
        'styles_deduplicated'  => 0,
        'bytes_saved'          => 0,
    ];

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_consolidation_map();
    }

    /**
     * Initializes hooks.
     */
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'deduplicate_assets'], 9999);
        add_action('admin_enqueue_scripts', [$this, 'deduplicate_assets'], 9999);
    }

    /**
     * Defines default consolidation rules for standard WM Suite assets.
     */
    private function init_consolidation_map(): void {
        $base_url = defined('WM_SUITE_HUB_PLUGIN_URL') ? WM_SUITE_HUB_PLUGIN_URL : '';
        $hub_css_url = $base_url . 'assets/css/wm-obsidian-glass.css';
        $hub_js_url  = $base_url . 'assets/js/wm-cockpit-vanilla.js';
        $family_css_url = $base_url . 'assets/css/wm-admin-ui.css';

        $this->consolidation_map = [
            // Legacy per-plugin copies of the admin sheet (handles like "wm-eraser-admin-obsidian"):
            // with the hub active, the family sheet it enqueues covers every class they used, so
            // the copy is dropped. Standalone plugins keep enqueuing their own copy.
            'admin-obsidian' => [
                'canonical_handle' => 'wm-admin-ui',
                'canonical_src'    => $family_css_url,
                'canonical_type'   => 'style',
            ],
            // Glassmorphism & Token Stylesheets
            'wm-tokens-shared' => [
                'canonical_handle' => 'wm-obsidian-glass',
                'canonical_src'    => $hub_css_url,
                'canonical_type'   => 'style',
            ],
            'wm-glassmorphism-theme' => [
                'canonical_handle' => 'wm-obsidian-glass',
                'canonical_src'    => $hub_css_url,
                'canonical_type'   => 'style',
            ],
            'cybevents-admin-tokens' => [
                'canonical_handle' => 'wm-obsidian-glass',
                'canonical_src'    => $hub_css_url,
                'canonical_type'   => 'style',
            ],
            // Common Vanilla JS Utilities
            'wm-vanilla-toast' => [
                'canonical_handle' => 'wm-suite-hub-cockpit',
                'canonical_src'    => $hub_js_url,
                'canonical_type'   => 'script',
            ],
            'wm-crypto-helpers' => [
                'canonical_handle' => 'wm-suite-hub-cockpit',
                'canonical_src'    => $hub_js_url,
                'canonical_type'   => 'script',
            ],
        ];
    }

    /**
     * Size on disk of a registered asset, read before it is deregistered.
     *
     * Only a source under this installation's plugins or content URL maps to a file; anything
     * else (another host, a missing registration, a path that leaves the directory) counts 0
     * rather than a guess.
     *
     * @param object $dependencies WP_Styles or WP_Scripts.
     * @param string $handle       Registered handle.
     * @return int Bytes, or 0 when no local file can be named.
     */
    private function local_size_of(object $dependencies, string $handle): int {
        $registered = $dependencies->registered ?? [];
        if (!is_array($registered) || !isset($registered[$handle]) || !is_object($registered[$handle])) {
            return 0;
        }

        $src = (string) ($registered[$handle]->src ?? '');
        $src = (string) strtok($src, '?#');
        if ($src === '' || str_contains($src, '..')) {
            return 0;
        }

        $roots = [];
        if (function_exists('plugins_url') && defined('WP_PLUGIN_DIR')) {
            $roots[untrailingslashit(plugins_url())] = WP_PLUGIN_DIR;
        }
        if (function_exists('content_url') && defined('WP_CONTENT_DIR')) {
            $roots[untrailingslashit(content_url())] = WP_CONTENT_DIR;
        }

        foreach ($roots as $url => $dir) {
            if ($url !== '' && str_starts_with($src, $url . '/')) {
                $path = $dir . substr($src, strlen($url));
                if (is_file($path)) {
                    $size = filesize($path);
                    return $size === false ? 0 : (int) $size;
                }
                return 0;
            }
        }

        return 0;
    }

    /**
     * Intercepts WP_Scripts and WP_Styles to deduplicate queued assets.
     */
    public function deduplicate_assets(): void {
        global $wp_scripts, $wp_styles;

        // Process Styles
        if ($wp_styles instanceof \WP_Styles) {
            foreach ($this->consolidation_map as $pattern => $rule) {
                if ($rule['canonical_type'] !== 'style') {
                    continue;
                }
                // Never drop a copy in favour of a canonical that is not on this page: doing so
                // left a spoke dashboard without any stylesheet (measured 2026-09-14).
                if (!wp_style_is($rule['canonical_handle'], 'enqueued')) {
                    continue;
                }

                foreach ($wp_styles->queue as $index => $handle) {
                    if ($handle !== $rule['canonical_handle'] && (str_contains($handle, $pattern) || $handle === $pattern)) {
                        $bytes = $this->local_size_of($wp_styles, $handle);
                        wp_dequeue_style($handle);
                        wp_deregister_style($handle);
                        $this->metrics['styles_deduplicated']++;
                        $this->metrics['bytes_saved'] += $bytes;
                    }
                }
            }
        }

        // Process Scripts
        if ($wp_scripts instanceof \WP_Scripts) {
            foreach ($this->consolidation_map as $pattern => $rule) {
                if ($rule['canonical_type'] !== 'script') {
                    continue;
                }
                if (!wp_script_is($rule['canonical_handle'], 'enqueued')) {
                    continue;
                }

                foreach ($wp_scripts->queue as $index => $handle) {
                    if ($handle !== $rule['canonical_handle'] && (str_contains($handle, $pattern) || $handle === $pattern)) {
                        $bytes = $this->local_size_of($wp_scripts, $handle);
                        wp_dequeue_script($handle);
                        wp_deregister_script($handle);
                        $this->metrics['scripts_deduplicated']++;
                        $this->metrics['bytes_saved'] += $bytes;
                    }
                }
            }
        }
    }

    /**
     * Returns the deduplication counters of this request.
     *
     * Nothing here measures timing: until 1.2.2 an `estimated_ttfb_improvement_ms` was derived
     * from the estimated bytes (0.45 ms per KB), a figure no measurement backed.
     *
     * @return array{scripts_deduplicated: int, styles_deduplicated: int, bytes_saved: int}
     */
    public function get_telemetry(): array {
        return [
            'scripts_deduplicated' => $this->metrics['scripts_deduplicated'],
            'styles_deduplicated'  => $this->metrics['styles_deduplicated'],
            'bytes_saved'          => $this->metrics['bytes_saved'],
        ];
    }
}
