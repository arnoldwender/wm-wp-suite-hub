<?php
declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ArnoldWender\ComplianceSDK\Enums\EventSeverity;
use ArnoldWender\ComplianceSDK\Enums\ConsentCategory;
use ArnoldWender\ComplianceSDK\Payloads\AuditPayload;

/**
 * Class Compliance
 *
 * Primary static facade for third-party WordPress developers.
 * Provides fluent, type-safe access to WM Suite Compliance services
 * with guaranteed graceful degradation (Null-Object / No-Op) when the Hub is inactive.
 *
 * @package ArnoldWender\ComplianceSDK
 * @since   1.0.0
 */
final class Compliance {

    /**
     * Records a business or security event in the hub's append-only audit table (an HMAC-SHA256 chain).
     *
     * @param string             $eventType Action identifier (e.g. 'order.created', 'password.reset').
     * @param array<string, mixed> $context Structured metadata (PII should be omitted or pre-sanitized).
     * @param EventSeverity      $severity Event severity level.
     * @param string|null        $actorId Optional actor ID (defaults to current WP user or SYSTEM).
     * @param string|null        $originModule Optional originating module/plugin slug.
     */
    public static function audit(
        string $eventType,
        array $context = [],
        EventSeverity $severity = EventSeverity::INFO,
        ?string $actorId = null,
        ?string $originModule = null
    ): void {
        if (!function_exists('do_action') || !function_exists('has_action')) {
            return; // Not in WordPress context
        }

        if (!has_action('wm_compliance_record_audit_event')) {
            return; // Graceful No-Op if Hub is not installed
        }

        $payload = new AuditPayload(
            eventType: $eventType,
            severity: $severity->value,
            actorId: $actorId,
            contextData: $context,
            originModule: $originModule
        );

        do_action('wm_compliance_record_audit_event', $payload->toArray());
    }

    /**
     * Checks if consent is granted for a specific category under TDDDG § 25 / DSGVO.
     *
     * @param ConsentCategory|string $category
     * @param bool                   $default Default return if no consent provider is active.
     * @return bool
     */
    public static function hasConsent(ConsentCategory|string $category, bool $default = false): bool {
        if (!function_exists('apply_filters')) {
            return $default;
        }

        $categorySlug = $category instanceof ConsentCategory ? $category->value : (string) $category;
        return (bool) apply_filters('wm_compliance_has_consent', $default, $categorySlug);
    }

    /**
     * Renders a 2-click consent placeholder for blocked embeds/scripts.
     *
     * @param string               $serviceName Name of external service (e.g. 'Google Maps', 'YouTube').
     * @param ConsentCategory|string $category Category required.
     * @param string               $previewImageUrl Optional background preview image URL.
     * @param string               $fallbackHtml Fallback HTML if Hub is absent.
     * @return string
     */
    public static function renderBlockedPlaceholder(
        string $serviceName,
        ConsentCategory|string $category = ConsentCategory::EXTERNAL_MEDIA,
        string $previewImageUrl = '',
        string $fallbackHtml = ''
    ): string {
        if (!function_exists('apply_filters')) {
            return $fallbackHtml;
        }

        $categorySlug = $category instanceof ConsentCategory ? $category->value : (string) $category;

        return (string) apply_filters('wm_compliance_render_blocked_placeholder', $fallbackHtml, [
            'service_name' => $serviceName,
            'category'     => $categorySlug,
            'preview_img'  => $previewImageUrl,
        ]);
    }

    /**
     * Sanitizes PII (German IBANs, Credit Cards, Emails, Tax IDs) from text prompts before LLM dispatch.
     *
     * @param string $prompt
     * @return string
     */
    public static function sanitizePrompt(string $prompt): string {
        if (!function_exists('apply_filters')) {
            return $prompt;
        }

        return (string) apply_filters('wm_compliance_sanitize_prompt_pii', $prompt);
    }
}
