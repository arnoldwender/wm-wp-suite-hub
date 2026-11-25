<?php
/**
 * Interface WM_Bundle_Interface
 *
 * Defines what a WM Suite Solution Pack exposes: its plugins, their dependency graph and topic tags.
 *
 * @package ArnoldWender\SuiteHub\SharedKernel\Contracts
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\SharedKernel\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WM_Bundle_Interface {

    /**
     * Unique alphanumeric identifier for the bundle (e.g. 'security-legal').
     *
     * @return string
     */
    public function get_bundle_id(): string;

    /**
     * Human-readable title of the solution suite.
     *
     * @return string
     */
    public function get_suite_name(): string;

    /**
     * Minimum required PHP version (e.g. '8.2.0').
     *
     * @return string
     */
    public function get_required_php_version(): string;

    /**
     * Extracts dependency graph nodes for Kahn's topological sort.
     *
     * @return array<string, array<string>> Map of package slug to its required dependencies.
     */
    public function get_dependency_graph(): array;

    /**
     * Returns the list of package descriptors included in this bundle.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_packages(): array;

    /**
     * Returns topic tags naming the rules the bundle's plugins deal with (e.g. ['CRA', 'DORA']).
     * A tag names a topic; it is not a statement that a site meets that rule.
     *
     * Until 1.2.2 the contract also had is_bfsg_compliant(), which every bundle answered with true
     * (the parser's default) and the view printed as a "BFSG 2025 AAA" seal.
     *
     * @return array<int, string>
     */
    public function get_compliance_tags(): array;
}
