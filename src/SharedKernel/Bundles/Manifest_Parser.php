<?php
/**
 * Class Manifest_Parser
 *
 * Ingests and validates the wm-bundles-manifest.json against schema constraints.
 *
 * @package ArnoldWender\SuiteHub\SharedKernel\Bundles
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\SharedKernel\Bundles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Manifest_Parser {

    /**
     * Parses a bundle manifest JSON string or file into an array of Bundle_Definition objects.
     *
     * @param string $json_content
     * @return array<string, Bundle_Definition> Keyed by bundle_id.
     * @throws \InvalidArgumentException If JSON is invalid or missing required keys.
     */
    public static function parse_json(string $json_content): array {
        $data = json_decode($json_content, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Ungültiges Manifest: JSON konnte nicht dekodiert werden.');
        }

        $raw_bundles = $data['bundles'] ?? [];
        if (!is_array($raw_bundles)) {
            throw new \InvalidArgumentException('Ungültiges Manifest: "bundles" Array fehlt.');
        }

        $bundles = [];
        foreach ($raw_bundles as $raw) {
            $bundle_id = sanitize_key((string) ($raw['bundle_id'] ?? ''));
            if (empty($bundle_id)) {
                continue;
            }

            $suite_name      = sanitize_text_field((string) ($raw['suite_name'] ?? ''));
            $description     = sanitize_text_field((string) ($raw['description'] ?? ''));
            $required_php    = sanitize_text_field((string) ($raw['required_php'] ?? '8.2.0'));
            $compliance_tags = array_map('sanitize_text_field', (array) ($raw['compliance_tags'] ?? []));
            $packages        = [];

            // A package is its slug, name, version floor and the plugins it requires. Until 1.2.2 the
            // manifest also carried per-plugin marketing copy (tagline, mandate, role, synergies,
            // features), placeholder Ed25519 signatures and SHA-256 values, and composer purls; none
            // was read by an install path, and the view printed the copy as if it were measured.
            // A plugin's description now comes from the plugin itself once it is active.
            foreach ((array) ($raw['packages'] ?? []) as $pkg) {
                $slug = sanitize_key((string) ($pkg['slug'] ?? ''));
                if (empty($slug)) {
                    continue;
                }

                $packages[] = [
                    'slug'               => $slug,
                    'name'               => sanitize_text_field((string) ($pkg['name'] ?? $slug)),
                    'version_constraint' => sanitize_text_field((string) ($pkg['version_constraint'] ?? '>=1.0.0')),
                    'dependencies'       => array_map('sanitize_key', (array) ($pkg['dependencies'] ?? [])),
                ];
            }

            $bundles[$bundle_id] = new Bundle_Definition(
                $bundle_id,
                $suite_name,
                $description,
                $required_php,
                $compliance_tags,
                $packages
            );
        }

        return $bundles;
    }

    /**
     * Loads the default embedded bundle manifest from disk.
     *
     * @return array<string, Bundle_Definition>
     */
    public static function load_default_manifest(): array {
        $path = dirname(__DIR__, 3) . '/assets/data/wm-bundles-manifest.json';
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        return self::parse_json($content);
    }
}
