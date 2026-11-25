<?php
declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK\Enums;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum ConsentCategory: string {
    case ESSENTIAL = 'essential';
    case STATISTICS = 'statistics';
    case MARKETING = 'marketing';
    case EXTERNAL_MEDIA = 'external_media';
    case AI_PROCESSING = 'ai_processing';
}
