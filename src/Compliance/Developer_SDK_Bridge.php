<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Compliance;

use ArnoldWender\SuiteHub\Contracts\WM_Audit_Payload;
use ArnoldWender\SuiteHub\Contracts\WM_Compliance_Provider_Interface;
use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Developer_SDK_Bridge
 *
 * Central extensibility bridge exposing declarative WordPress hooks and filters
 * for 3rd-party developers, themes, and plugins to integrate with WM Suite's
 * compliance, audit, privacy, and accessibility pipeline.
 *
 * @package ArnoldWender\SuiteHub\Compliance
 * @since   1.1.0
 */
class Developer_SDK_Bridge {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Cache of registered 3rd-party compliance providers.
     *
     * @var array<string, WM_Compliance_Provider_Interface>
     */
    private array $registered_providers = [];

    /**
     * Retrieves singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — registers core filter and action listeners.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Registers global WordPress hooks for third-party extensibility.
     */
    public function register_hooks(): void {
        // 1. Audit Ledger Ingestion Actions
        add_action('wm_compliance_record_audit_event', [$this, 'handle_record_audit_event'], 10, 1);
        add_action('wm_suite_hub_dispatch_audit_event', [$this, 'handle_dispatch_audit_event'], 10, 7);

        // 2. Consent Verification Filter
        add_filter('wm_compliance_has_consent', [$this, 'filter_has_consent'], 10, 2);

        // 3. Two-Click Blocked Embed Placeholder Filter
        add_filter('wm_compliance_render_blocked_placeholder', [$this, 'filter_render_blocked_placeholder'], 10, 2);

        // 4. PII Sanitization for LLM Prompts Filter
        add_filter('wm_compliance_sanitize_prompt_pii', [$this, 'filter_sanitize_prompt_pii'], 10, 1);

        // 5. SBOM Component Dynamic Registration
        add_filter('wm_cra_sbom_register_component', [$this, 'filter_register_sbom_component'], 10, 1);

        // 6. Provider Discovery
        add_action('init', [$this, 'discover_third_party_providers'], 20);
    }

    /**
     * Handles unpacked third-party audit event dispatch from wm_suite_hub_dispatch_audit_event.
     *
     * @param string               $module_slug Module slug (e.g. 'wm-peppol-as4-gateway').
     * @param string               $event_type  Event type identifier.
     * @param string               $category    Category ('finance', 'privacy', 'security', 'general').
     * @param string               $severity    Severity ('info', 'warning', 'critical', 'emergency').
     * @param int                  $actor_id    Actor User ID or 0.
     * @param string               $resource_id Resource identifier.
     * @param array<string, mixed> $metadata    Contextual metadata.
     */
    public function handle_dispatch_audit_event(
        string $module_slug,
        string $event_type,
        string $category = 'general',
        string $severity = 'info',
        int $actor_id = 0,
        string $resource_id = '',
        array $metadata = []
    ): void {
        $payload = WM_Audit_Payload::create(
            source_module: $module_slug,
            event_type:    $event_type,
            category:      $category,
            severity:      $severity,
            actor_id:      $actor_id,
            resource_id:   $resource_id,
            payload_digest: '',
            metadata:      $metadata
        );

        $this->handle_record_audit_event($payload);
    }

    /**
     * Appends a third-party audit event to the HMAC-SHA256 audit chain.
     *
     * @param WM_Audit_Payload|array<string, mixed> $payload
     */
    public function handle_record_audit_event(mixed $payload): void {
        try {
            if (!$payload instanceof WM_Audit_Payload) {
                if (is_array($payload)) {
                    $payload = WM_Audit_Payload::from_array($payload);
                } else {
                    return; // Invalid payload type
                }
            }

            // Sanitize context to prevent secret/password leak in audit log
            $sanitized_context = $this->sanitize_context_data($payload->context_data);

            $entry = [
                'event_type'    => $payload->event_type,
                'severity'      => $payload->severity,
                'actor_id'      => $payload->actor_id,
                'context_data'  => $sanitized_context,
                'origin_module' => $payload->origin_module,
                'timestamp'     => $payload->timestamp > 0 ? $payload->timestamp : time(),
            ];

            $actor_user_id = is_numeric($payload->actor_id) ? (int) $payload->actor_id : 0;
            Audit_Chainer::get_instance()->append_event(
                $payload->origin_module,
                $payload->event_type,
                $entry,
                $actor_user_id
            );
        } catch (Throwable $e) {
            // Error boundary: log failure without breaking host request
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[WM-Suite-Hub] Error recording external audit event: ' . $e->getMessage() ); }
        }
    }

    /**
     * Filters consent state for a given category under TDDDG § 25 / DSGVO.
     *
     * Priority order:
     * 1. WM Sovereign Consent Vault (`wm_consent_has()` global API or `wm_consent_has` filter).
     * 2. Borlabs Cookie (`borlabs-cookie` JSON cookie), see borlabs_consents_allow().
     * 3. `$default_consent` fallback.
     *
     * @param bool   $default_consent
     * @param string $category Borlabs service group (marketing, statistics, external_media, …) or service key.
     * @return bool
     */
    public function filter_has_consent(bool $default_consent, string $category): bool {
        $normalized = sanitize_key($category);
        if ($normalized === '') {
            return $default_consent;
        }

        if (function_exists('wm_consent_has')) {
            return (bool) wm_consent_has($normalized);
        }

        if (function_exists('has_filter') && has_filter('wm_consent_has')) {
            return (bool) apply_filters('wm_consent_has', $default_consent, $normalized);
        }

        if (isset($_COOKIE['borlabs-cookie'])) {
            $raw_cookie  = function_exists('wp_unslash') ? (string) wp_unslash((string) $_COOKIE['borlabs-cookie']) : stripslashes((string) $_COOKIE['borlabs-cookie']);
            $cookie_data = json_decode($raw_cookie, true);
            if (is_array($cookie_data) && isset($cookie_data['consents']) && is_array($cookie_data['consents'])) {
                return self::borlabs_consents_allow($cookie_data['consents'], $normalized);
            }
        }

        return $default_consent;
    }

    /**
     * Reads the consents object of the Borlabs Cookie plugin cookie (measured against 3.4), shaped
     * {service-group: [service, …]} — e.g. {"essential":["borlabs-cookie"],"marketing":["…"]}
     * (Borlabs 3.4, CrossDomainCookieService::getPluginCookie()). A declined group is absent.
     *
     * Until 1.2.3 this looked for the category among the VALUES, which are lists, so with Borlabs
     * present every category came back as "no consent", including after the visitor accepted.
     *
     * @param array<mixed> $consents Decoded `consents` member of the cookie.
     * @param string       $wanted   Service-group key (marketing, statistics, external-media, …) or a service key.
     * @return bool
     */
    private static function borlabs_consents_allow(array $consents, string $wanted): bool {
        // Borlabs names the group external-media; callers of this filter pass external_media.
        $group_keys = array_unique([$wanted, str_replace('_', '-', $wanted)]);
        foreach ($group_keys as $group) {
            if (isset($consents[$group]) && is_array($consents[$group]) && $consents[$group] !== []) {
                return true;
            }
        }

        // A single service (googlemaps, youtube, …) counts once it is listed in any accepted group.
        foreach ($consents as $services) {
            if (is_array($services) && in_array($wanted, $services, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Renders an accessible 2-click consent placeholder for unconsented widgets.
     *
     * @param string               $default_html
     * @param array<string, mixed> $args
     * @return string Accessible HTML placeholder
     */
    public function filter_render_blocked_placeholder(string $default_html, array $args = []): string {
        if (!function_exists('wm_suite_hub_icon') && file_exists(dirname(__DIR__) . '/Gutenberg/icons.php')) {
            require_once dirname(__DIR__) . '/Gutenberg/icons.php';
        }

        $service_name = esc_html((string) ($args['service_name'] ?? __('Externer Dienst', 'wm-suite-hub')));
        $category     = esc_attr((string) ($args['category'] ?? 'external_media'));
        $raw_preview  = esc_url_raw((string) ($args['preview_img'] ?? ''));
        $safe_preview = ($raw_preview !== '' && 1 !== preg_match('/[\'"();\s\\\\]/', $raw_preview)) ? $raw_preview : '';

        $bg_style  = $safe_preview !== '' ? sprintf('background-image: url("%s");', esc_attr($safe_preview)) : '';
        $lock_icon = function_exists('wm_suite_hub_icon') && function_exists('wm_suite_hub_icon_allowed_html')
            ? wp_kses(wm_suite_hub_icon('lock'), wm_suite_hub_icon_allowed_html())
            : '';

        /* translators: %s: name of the blocked third-party service (YouTube, Google Maps, …) */
        $region_label = sprintf(__('Blockierter externer Inhalt: %s', 'wm-suite-hub'), $service_name);
        /* translators: %s: name of the blocked third-party service */
        $blocked_title = sprintf(__('Inhalt von %s blockiert', 'wm-suite-hub'), $service_name);
        /* translators: %s: name of the blocked third-party service */
        $unlock_label = sprintf(__('%s einmalig laden und aktivieren', 'wm-suite-hub'), $service_name);

        return sprintf(
            '<div class="wm-blocked-embed-placeholder" data-wm-category="%s" style="%s" role="region" aria-label="%s">
                <div class="wm-blocked-embed-inner">
                    <div class="wm-blocked-embed-icon" aria-hidden="true">%s</div>
                    <p class="wm-blocked-embed-title">%s</p>
                    <p class="wm-blocked-embed-desc">%s</p>
                    <button type="button" class="wm-blocked-embed-btn" data-wm-unlock="%s" aria-label="%s">%s</button>
                    <p class="wm-blocked-embed-legal"><small>%s</small></p>
                </div>
            </div>',
            $category,
            $bg_style,
            esc_attr($region_label),
            $lock_icon,
            esc_html($blocked_title),
            esc_html__('Um diesen Inhalt zu laden, ist Ihre Einwilligung gemäß § 25 TDDDG und DSGVO erforderlich.', 'wm-suite-hub'),
            $category,
            esc_attr($unlock_label),
            esc_html__('Inhalt laden & entsperren', 'wm-suite-hub'),
            esc_html__('Mit dem Laden akzeptieren Sie die Datenübertragung an den Drittanbieter.', 'wm-suite-hub')
        );
    }

    /**
     * Tokenizes Personally Identifiable Information (PII) from text prompts before LLM dispatch.
     *
     * Supports:
     * - German IBAN (DE + 2 control digits + 18 BBAN digits, with/without spacing)
     * - Credit card numbers (13-19 digits formatted with spaces or dashes)
     * - Email addresses
     * - German Tax ID (USt-IdNr DE + 9 digits)
     * - Phone numbers (German/International formats)
     *
     * @param string $prompt
     * @return string Sanitized prompt with structured substitution tokens
     */
    public function filter_sanitize_prompt_pii(string $prompt): string {
        if ($prompt === '') {
            return '';
        }

        // 1. German IBAN Sanitization (DE + 2 control + 18 digits = 22 characters total)
        // Matches: DE89 3704 0044 0532 0130 00 or DE89370400440532013000
        $prompt = preg_replace_callback(
            '/\bDE\s*(?:[0-9]\s*){20}\b/i',
            static function (array $matches): string {
                return '[ANONYMIZED_IBAN_DE]';
            },
            $prompt
        ) ?? $prompt;

        // 2. German VAT / Tax ID (USt-IdNr: DE + 9 digits)
        $prompt = preg_replace(
            '/\bDE\s*(?:[0-9]\s*){9}\b/i',
            '[ANONYMIZED_UST_IDNR]',
            $prompt
        ) ?? $prompt;

        // 3. Email Addresses
        $prompt = preg_replace(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            '[ANONYMIZED_EMAIL]',
            $prompt
        ) ?? $prompt;

        // 4. Credit Card Numbers (13 to 19 digits formatted with spaces, dashes, or continuous; Mod-10 Luhn verified)
        $prompt = preg_replace_callback(
            '/\b(?:4[0-9]{3}(?:[ -]?[0-9]{4}){3}|5[1-5][0-9]{2}(?:[ -]?[0-9]{4}){3}|3[47][0-9]{2}[ -]?[0-9]{6}[ -]?[0-9]{5}|(?:[0-9]{4}[ -]){3}[0-9]{4})\b/',
            function (array $matches): string {
                $digits = preg_replace('/\D+/', '', $matches[0]) ?? '';
                if ($digits === '' || !$this->is_luhn_valid($digits)) {
                    return $matches[0];
                }
                return '[ANONYMIZED_CREDIT_CARD]';
            },
            $prompt
        ) ?? $prompt;

        // 5. Phone Numbers (German / International: +49..., 0049..., 0345..., 0151...)
        $prompt = preg_replace(
            '/(?:\+49|0049|0)[\s\-\/\.]?[1-9][0-9 \-\/\(\)]{4,14}[0-9]/',
            '[ANONYMIZED_PHONE]',
            $prompt
        ) ?? $prompt;

        return $prompt;
    }

    /**
     * Validates card number using Luhn algorithm (Mod 10).
     *
     * @param string $number
     * @return bool
     */
    private function is_luhn_valid(string $number): bool {
        $sum = 0;
        $num_digits = strlen($number);
        $parity = $num_digits % 2;

        for ($i = 0; $i < $num_digits; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ($sum % 10 === 0);
    }

    /**
     * Discovers registered 3rd-party compliance provider plugins.
     */
    public function discover_third_party_providers(): void {
        $providers = apply_filters('wm_compliance_register_provider', []);
        if (!is_array($providers)) {
            return;
        }

        foreach ($providers as $provider) {
            if ($provider instanceof WM_Compliance_Provider_Interface) {
                $this->registered_providers[$provider->get_integration_slug()] = $provider;
            }
        }
    }

    /**
     * Registers components into CycloneDX SBOM array.
     *
     * @param array<int, array<string, mixed>> $components
     * @return array<int, array<string, mixed>>
     */
    public function filter_register_sbom_component(array $components): array {
        foreach ($this->registered_providers as $provider) {
            $components[] = [
                'type'        => 'application',
                'name'        => $provider->get_integration_slug(),
                'version'     => $provider->get_integration_version(),
                'description' => $provider->get_integration_name(),
                'supplier'    => ['name' => 'Third-Party Integration'],
                'properties'  => [
                    'wm_compliance_vectors' => implode(',', $provider->get_supported_compliance_vectors()),
                ],
            ];
        }
        return $components;
    }

    /**
     * Returns array of registered third-party compliance providers.
     *
     * @return array<string, WM_Compliance_Provider_Interface>
     */
    public function get_registered_providers(): array {
        return $this->registered_providers;
    }

    /**
     * Strips dangerous/sensitive keys from context metadata before persisting to audit log.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitize_context_data(array $context): array {
        $blacklisted_keys = [
            'password', 'pass', 'pwd', 'secret', 'auth_token',
            'api_key', 'access_token', 'private_key', 'credit_card', 'cvv'
        ];

        $clean = [];
        foreach ($context as $key => $value) {
            $key_lower = strtolower((string) $key);
            if (in_array($key_lower, $blacklisted_keys, true)) {
                $clean[$key] = '[REDACTED_SECRET]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitize_context_data($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
