<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface WM_Compliance_Provider_Interface
 *
 * Voluntary contract for 3rd-party plugins, themes, and extensions wishing
 * to integrate with WM Suite's enterprise compliance, audit, and privacy pipeline.
 *
 * @package ArnoldWender\SuiteHub\Contracts
 * @since   1.1.0
 */
interface WM_Compliance_Provider_Interface {

    /**
     * Unique vendor/plugin identifier (e.g. 'my-booking-pro', 'custom-forms').
     *
     * @return string
     */
    public function get_integration_slug(): string;

    /**
     * Display name of the third-party integration.
     *
     * @return string
     */
    public function get_integration_name(): string;

    /**
     * Integration version.
     *
     * @return string
     */
    public function get_integration_version(): string;

    /**
     * Declares which compliance vectors this integration supports.
     * Supported items: 'audit_ledger', 'consent_gateway', 'pii_sanitization', 'sbom_inventory', 'a11y_remediation', 'e_invoicing'.
     *
     * @return array<int, string>
     */
    public function get_supported_compliance_vectors(): array;
}
