<?php
/**
 * WM Suite Hub — Global Helper Functions
 *
 * Exposes global suite-wide functions for theme resolution and service discovery.
 *
 * @package ArnoldWender\SuiteHub
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!function_exists('wm_get_suite_theme')) {
    /**
     * Retrieves the active theme slug for the WM Suite and Cockpit.
     * Defaults to 'cyber-events' (matching wm-cyber-events-core palette).
     *
     * @return string Theme slug: 'cyber-events', 'wp-native', 'obsidian-dark', 'high-contrast'.
     */
    function wm_get_suite_theme(): string {
        $theme = get_option('wm_suite_theme', 'cyber-events');
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $user_theme = get_user_meta(get_current_user_id(), 'wm_suite_theme', true);
            if (!empty($user_theme) && is_string($user_theme)) {
                $theme = $user_theme;
            }
        }
        return (string) apply_filters('wm_suite_active_theme', $theme);
    }
}

if (!function_exists('wm_get_suite_available_themes')) {
    /**
     * Returns the array of registered theme palettes for the WM Suite suite.
     *
     * @return array<string, string>
     */
    function wm_get_suite_available_themes(): array {
        return [
            'cyber-events'  => 'CyberEvents Enterprise (Standard)',
            'wp-native'     => 'WordPress Native Classic',
            'obsidian-dark' => 'Obsidian Midnight Dark',
            'high-contrast' => 'High-Contrast',
        ];
    }
}
