<?php
declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK\Enums;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum ComplianceDomain: string {
    case AUDIT_LEDGER = 'audit_ledger';
    case CONSENT_GATEWAY = 'consent_gateway';
    case PII_SANITIZATION = 'pii_sanitization';
    case SBOM_INVENTORY = 'sbom_inventory';
    case A11Y_REMEDIATION = 'a11y_remediation';
    case E_INVOICING = 'e_invoicing';
}
