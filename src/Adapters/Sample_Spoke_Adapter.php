<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Contracts\WM_Plugin_Module_Interface;

/**
 * Class Sample_Spoke_Adapter
 *
 * Reference adapter implementation demonstrating how existing and future
 * WM Suite plugins connect to the Hub via WM_Plugin_Module_Interface.
 *
 * @package ArnoldWender\SuiteHub\Adapters
 * @since   1.0.0
 */
class Sample_Spoke_Adapter implements WM_Plugin_Module_Interface {

    private string $slug;
    private string $name;
    private string $version;
    private string $description;
    private string $health_status;
    private array $dependencies;

    public function __construct(
        string $slug = 'wm-sample-spoke',
        string $name = 'WM Suite Sample Spoke',
        string $version = '1.0.0',
        string $description = 'Demonstration spoke adapter for Hub service discovery.',
        string $health_status = 'HEALTHY',
        array $dependencies = []
    ) {
        $this->slug          = $slug;
        $this->name          = $name;
        $this->version       = $version;
        $this->description   = $description;
        $this->health_status = $health_status;
        $this->dependencies  = $dependencies;
    }

    public function get_module_slug(): string {
        return $this->slug;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_version(): string {
        return $this->version;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function get_health_status(): string {
        return $this->health_status;
    }

    public function get_sbom_data(): array {
        return [
            'type'        => 'library',
            'name'        => $this->slug,
            'version'     => $this->version,
            'description' => $this->description,
            'author'      => 'WM Suite (Arnold Wender)',
            // Every WM WordPress plugin is GPLv2 or later in its header, readme.txt, composer.json
            // and LICENSE. This reference adapter said 'Proprietary' and eleven real adapters were
            // copied from it, so the SBOM — the one artefact whose job is to state the licence, and
            // which is served unauthenticated — was the only place stating it wrongly. 'Proprietary'
            // is also not an SPDX identifier, which CycloneDX requires for `id`.
            'licenses'    => [['license' => ['id' => 'GPL-2.0-or-later']]],
        ];
    }

    public function get_dependencies(): array {
        return $this->dependencies;
    }

    public function get_settings_url(): ?string {
        return function_exists('admin_url') ? admin_url('admin.php?page=' . $this->slug) : null;
    }

    public function get_telemetry_metrics(): array {
        return [
            'status'         => $this->health_status,
            'last_sync'      => gmdate('Y-m-d H:i:s'),
            'events_handled' => 128,
        ];
    }

    public function execute_action(string $action, array $params = []): array {
        if ($action === 'ping') {
            return [
                'success' => true,
                'message' => sprintf('Pong from %s v%s. Self-test passed.', $this->name, $this->version),
            ];
        }

        return [
            'success' => false,
            'message' => sprintf('Unbekannte Aktion: %s', $action),
        ];
    }
}
