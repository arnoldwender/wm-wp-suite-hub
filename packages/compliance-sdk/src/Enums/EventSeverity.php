<?php
declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK\Enums;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum EventSeverity: string {
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';
    case EMERGENCY = 'EMERGENCY';
}
