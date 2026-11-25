<?php
/**
 * Class Bundle_Definition
 *
 * Concrete immutable representation of a WM Suite Solution Pack.
 *
 * @package ArnoldWender\SuiteHub\SharedKernel\Bundles
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\SharedKernel\Bundles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\SharedKernel\Contracts\WM_Bundle_Interface;

final readonly class Bundle_Definition implements WM_Bundle_Interface {

    /**
     * @param string                       $bundle_id
     * @param string                       $suite_name
     * @param string                       $description
     * @param string                       $required_php
     * @param array<int, string>           $compliance_tags
     * @param array<int, array<string, mixed>> $packages
     */
    public function __construct(
        private string $bundle_id,
        private string $suite_name,
        private string $description,
        private string $required_php,
        private array $compliance_tags,
        private array $packages
    ) {}

    public function get_bundle_id(): string {
        return $this->bundle_id;
    }

    public function get_suite_name(): string {
        return $this->suite_name;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function get_required_php_version(): string {
        return $this->required_php;
    }

    public function get_compliance_tags(): array {
        return $this->compliance_tags;
    }

    public function get_packages(): array {
        return $this->packages;
    }

    /**
     * Whether any package of this bundle requires another one.
     *
     * No plugin of the fleet declares `Requires Plugins:` today, so the bundled manifest has no
     * edges and Kahn's order is simply the listed order; the view says so instead of presenting
     * that list as a computed install sequence.
     *
     * @return bool
     */
    public function has_dependencies(): bool {
        foreach ($this->packages as $pkg) {
            if (!empty($pkg['dependencies'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extracts dependency graph nodes for Kahn's topological sort.
     *
     * @return array<string, array<string>>
     */
    public function get_dependency_graph(): array {
        $graph = [];
        foreach ($this->packages as $pkg) {
            $slug = (string) ($pkg['slug'] ?? '');
            if (!empty($slug)) {
                $graph[$slug] = (array) ($pkg['dependencies'] ?? []);
            }
        }
        return $graph;
    }

    /**
     * Serializes bundle metadata to array for REST API responses and WP-CLI output.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'bundle_id'        => $this->bundle_id,
            'suite_name'       => $this->suite_name,
            'description'      => $this->description,
            'required_php'     => $this->required_php,
            'compliance_tags'  => $this->compliance_tags,
            'package_count'    => count($this->packages),
            'packages'         => $this->packages,
        ];
    }
}
