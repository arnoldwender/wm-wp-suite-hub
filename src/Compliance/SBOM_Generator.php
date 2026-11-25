<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Compliance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Core\Module_Registry;

/**
 * Class SBOM_Generator
 *
 * Builds a CycloneDX 1.6 Software Bill of Materials (SBOM) of the hub and the registered
 * modules: name, version, purl and the licence each component declares. It is an inventory;
 * it does not establish conformity with the Cyber Resilience Act or BSI TR-03183-2.
 *
 * @package ArnoldWender\SuiteHub\Compliance
 * @since   1.0.0
 */
final class SBOM_Generator {

    /**
     * CycloneDX Specification Version.
     */
    private const SPEC_VERSION = '1.6';

    /**
     * Generates a CycloneDX JSON string representing the active WM Suite suite.
     *
     * @param Module_Registry $registry Active module registry.
     * @return array<string, mixed>
     */
    public static function generate_cyclonedx_array(Module_Registry $registry): array {
        $serial_number = 'urn:uuid:' . wp_generate_uuid4();
        $timestamp     = gmdate('Y-m-d\TH:i:s\Z');

        $components = [];

        // 1. Root Hub Component
        $components[] = [
            'type'        => 'application',
            'name'        => 'wm-suite-hub',
            'version'     => defined('WM_SUITE_HUB_VERSION') ? WM_SUITE_HUB_VERSION : '1.2.6',
            'description' => 'WM Enterprise Cockpit Hub',
            'author'      => 'WM Suite (Arnold Wender)',
            'licenses'    => self::own_licenses(),
            'purl'        => 'pkg:wordpress/arnoldwender/wm-suite-hub@' . (defined('WM_SUITE_HUB_VERSION') ? WM_SUITE_HUB_VERSION : '1.2.6'),
        ];

        // 2. Aggregate Spoke Components
        foreach ($registry->get_all_modules() as $slug => $module) {
            try {
                $sbom_data = $module->get_sbom_data();
                $version   = $module->get_version();
                
                // No default licence for a spoke. The hub does not know what a spoke is licensed
                // under; the spoke does, and states it in get_sbom_data(). Until 2026-09-19 the
                // default was ['id' => 'Proprietary'], so a spoke that declared nothing was
                // published as proprietary — and every WM plugin is GPLv2 or later. Inventing a
                // licence in a compliance SBOM is worse than omitting the field, which is what
                // CycloneDX means by an absent `licenses`: unknown, not proprietary.
                $components[] = array_merge([
                    'type'        => $sbom_data['type'] ?? 'library',
                    'name'        => $slug,
                    'version'     => $version,
                    'description' => $module->get_description(),
                    'author'      => $sbom_data['author'] ?? 'WM Suite',
                    'purl'        => 'pkg:wordpress/arnoldwender/' . $slug . '@' . $version,
                ], $sbom_data);
            } catch (\Throwable $e) {
                // Fallback for degraded module
                $components[] = [
                    'type'    => 'library',
                    'name'    => $slug,
                    'version' => 'UNKNOWN',
                    'purl'    => 'pkg:wordpress/arnoldwender/' . $slug,
                ];
            }
        }

        return [
            'bomFormat'    => 'CycloneDX',
            'specVersion'  => self::SPEC_VERSION,
            'serialNumber' => $serial_number,
            'version'      => 1,
            'metadata'     => [
                'timestamp' => $timestamp,
                'tools'     => [
                    'components' => [
                        [
                            // The hub builds this document. Until 1.2.2 the tool was named after
                            // another plugin of the fleet (wm-cra-sbom-beacon), which did not run.
                            'type'    => 'application',
                            'name'    => 'wm-suite-hub',
                            'version' => defined('WM_SUITE_HUB_VERSION') ? WM_SUITE_HUB_VERSION : '1.2.6',
                        ]
                    ]
                ],
                'authors' => [
                    [
                        'name'  => 'Arnold Wender',
                    ]
                ],
                'component' => [
                    'type'    => 'application',
                    'name'    => get_bloginfo('name'),
                    'version' => get_bloginfo('version'),
                    'purl'    => 'pkg:wordpress/site@' . get_bloginfo('version'),
                ],
            ],
            'components' => $components,
        ];
    }

    /**
     * The hub's own licence, read from its plugin header instead of hard-coded.
     *
     * A hard-coded string is what put `Proprietary` here while the header, readme.txt,
     * composer.json, the README badge and LICENSE all said GPLv2 or later — five surfaces right,
     * the one that exists to be machine-read wrong, and served without authentication. Reading the
     * header cannot drift from it. When the header cannot be read the field is OMITTED: in
     * CycloneDX an absent `licenses` means unknown, and unknown is honest where a guess is not.
     *
     * @return list<array{license: array{id?: string, name?: string}}>
     */
    private static function own_licenses(): array {
        $raw = '';
        if (defined('WM_SUITE_HUB_FILE') && function_exists('get_file_data')) {
            $data = get_file_data(WM_SUITE_HUB_FILE, ['License' => 'License'], 'plugin');
            $raw  = trim((string) ($data['License'] ?? ''));
        }
        if ('' === $raw) {
            return [];
        }
        // "GPLv2 or later" is what the WordPress header carries; CycloneDX wants an SPDX id, and
        // `id` and `name` may not both be set. Anything else is passed through as a name.
        if (preg_match('/^GPL\s*v?2(\s+or\s+later)?$/i', $raw)) {
            return [['license' => ['id' => 'GPL-2.0-or-later']]];
        }
        return [['license' => ['name' => $raw]]];
    }
}
