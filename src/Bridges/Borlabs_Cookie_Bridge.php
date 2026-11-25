<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Bridges;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ArnoldWender\SuiteHub\Telemetry\Audit_Chainer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Throwable;

/**
 * Class Borlabs_Cookie_Bridge
 *
 * Copies each consent decision a visitor saves in Borlabs Cookie into the hub's audit table.
 * Only when Borlabs is active, assets/js/wm-borlabs-bridge.js listens for the Borlabs event
 * borlabs-cookie-consent-saved and posts to POST /wm-suite/v1/consent-sync. The route checks the
 * REST nonce and a per-client rate limit, reads the decision from the borlabs-cookie cookie the
 * request carries — {service group: [service, …]} plus Borlabs' own consent uid, which Borlabs
 * writes before it fires the event — and appends one consent_recorded entry with the anonymised IP.
 * Measured against Borlabs Cookie 3.4: its events carry an empty detail, so the cookie is the only
 * place the decision can be read. Without readable consent data nothing is written.
 *
 * It does not configure Borlabs. Until 1.2.3 it hooked borlabsCookie/package/register and
 * borlabsCookie/contentBlocker/modify — filters Borlabs 3.4 does not have — to register 18
 * "services" with legal-basis texts no visitor ever saw, rewrote two script handles no plugin
 * registers to text/plain with an attribute Borlabs does not read, and sealed every saved
 * decision as an entry with empty consents.
 *
 * @package ArnoldWender\SuiteHub\Bridges
 * @since   1.0.0
 */
final class Borlabs_Cookie_Bridge {

    /**
     * Rate limit for the consent-sync route: requests per client per fixed window.
     * A consent banner fires once or twice per visit; 10/min leaves headroom and
     * stops a script from filling the audit table.
     */
    private const RATE_LIMIT_MAX    = 10;
    private const RATE_LIMIT_WINDOW = 60;

    /**
     * Bounds on what a consent event may carry into the audit table (groups, services per group,
     * length of each name or value).
     */
    private const MAX_CONSENT_KEYS      = 50;
    private const MAX_CONSENT_VALUE_LEN = 64;
    private const MAX_USER_AGENT_LEN    = 255;

    /**
     * Singleton instance.
     *
     * @var Borlabs_Cookie_Bridge|null
     */
    private static ?Borlabs_Cookie_Bridge $instance = null;

    /**
     * Audit Chainer instance that signs each entry.
     *
     * @var Audit_Chainer|null
     */
    private ?Audit_Chainer $audit_chainer = null;

    /**
     * Cached detected version string ('2.x', '3.x', or 'none').
     *
     * @var string|null
     */
    private ?string $detected_version = null;

    /**
     * Retrieves the singleton instance.
     *
     * @param Audit_Chainer|null $audit_chainer
     * @return Borlabs_Cookie_Bridge
     */
    public static function get_instance(?Audit_Chainer $audit_chainer = null): Borlabs_Cookie_Bridge {
        if (self::$instance === null) {
            self::$instance = new self($audit_chainer);
        } elseif ($audit_chainer !== null && self::$instance->audit_chainer === null) {
            self::$instance->audit_chainer = $audit_chainer;
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     *
     * @param Audit_Chainer|null $audit_chainer
     */
    private function __construct(?Audit_Chainer $audit_chainer = null) {
        $this->audit_chainer = $audit_chainer ?? new Audit_Chainer();
    }

    /**
     * Registers the route and the frontend listener, only with Borlabs present: without it the
     * route would be a write surface nothing legitimate uses.
     *
     * @return void
     */
    public function init(): void {
        if ($this->is_borlabs_active()) {
            add_action('rest_api_init', [$this, 'register_telemetry_routes']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_bridge'], 50);
        }
    }

    /**
     * Detects active Borlabs Cookie installation and version.
     *
     * @return string '3.x', '2.x', or 'none'
     */
    public function detect_version(): string {
        if ($this->detected_version !== null) {
            return $this->detected_version;
        }

        if (function_exists('borlabsCookieApi') || defined('BORLABS_COOKIE_VERSION_V3') || class_exists('BorlabsCookie\Cookie\Core')) {
            $this->detected_version = '3.x';
        } elseif (defined('BORLABS_COOKIE_VERSION') || class_exists('BorlabsCookieHelper') || function_exists('BorlabsCookieHandler')) {
            $this->detected_version = '2.x';
        } else {
            // Check active plugins list
            $active_plugins = (array) get_option('active_plugins', []);
            foreach ($active_plugins as $plugin) {
                if (str_contains($plugin, 'borlabs-cookie')) {
                    $this->detected_version = '3.x'; // Default modern presumption
                    return $this->detected_version;
                }
            }
            $this->detected_version = 'none';
        }

        return $this->detected_version;
    }

    /**
     * Checks if Borlabs Cookie is currently active.
     *
     * @return bool
     */
    public function is_borlabs_active(): bool {
        return $this->detect_version() !== 'none';
    }

    /**
     * Registers the REST route for consent synchronization.
     *
     * @return void
     */
    public function register_telemetry_routes(): void {
        register_rest_route('wm-suite/v1', '/consent-sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_consent_sync'],
            // Anonymous visitors post here, so the gate is the REST nonce the bridge script
            // carries plus a per-client rate limit — not a capability.
            'permission_callback' => [$this, 'check_consent_sync_permission'],
        ]);
    }

    /**
     * Gate for the consent-sync route: a valid REST nonce (the bridge script embeds it) and
     * a fixed-window rate limit keyed by a blinded client IP. Fails closed.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function check_consent_sync_permission(WP_REST_Request $request): true|WP_Error {
        $nonce = (string) ($request->get_header('X-WP-Nonce') ?? '');
        if ('' === $nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'wm_consent_sync_nonce',
                __('Consent-Synchronisation abgelehnt: fehlender oder abgelaufener Sicherheits-Token.', 'wm-suite-hub'),
                ['status' => 403]
            );
        }

        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';
        if ($this->is_rate_limited($client_ip)) {
            return new WP_Error(
                'wm_consent_sync_rate_limited',
                __('Zu viele Consent-Ereignisse. Bitte in einer Minute erneut versuchen.', 'wm-suite-hub'),
                ['status' => 429]
            );
        }

        return true;
    }

    /**
     * Fixed-window counter in a transient. The window end is stored inside the value and the
     * TTL is derived from it on every write: rewriting a transient with the original TTL would
     * turn the window into a sliding one that never expires under sustained load.
     *
     * @param string $ip Raw client IP; never stored — only its HMAC is used as key.
     * @return bool True when the client exceeded RATE_LIMIT_MAX in the current window.
     */
    private function is_rate_limited(string $ip): bool {
        $key    = 'wm_cs_rl_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 32);
        $now    = time();
        $bucket = get_transient($key);

        if (!is_array($bucket) || (int) ($bucket['reset'] ?? 0) <= $now) {
            $bucket = ['count' => 0, 'reset' => $now + self::RATE_LIMIT_WINDOW];
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, max(1, (int) $bucket['reset'] - $now));

        return $bucket['count'] > self::RATE_LIMIT_MAX;
    }

    /**
     * The decoded borlabs-cookie cookie of this request, or null when it is absent or carries no
     * consents object. Shape (Borlabs 3.4, CrossDomainCookieService::getPluginCookie()):
     * {"consents":{"essential":["borlabs-cookie"],"statistics":["…"]},"uid":"…","version":1,…}
     *
     * @return array{consents: array<mixed>, uid: string}|null
     */
    private function read_borlabs_cookie(): ?array {
        if (!isset($_COOKIE['borlabs-cookie'])) {
            return null;
        }
        $data = json_decode(wp_unslash((string) $_COOKIE['borlabs-cookie']), true);
        if (!is_array($data) || !isset($data['consents']) || !is_array($data['consents'])) {
            return null;
        }
        return [
            'consents' => $data['consents'],
            'uid'      => substr(sanitize_key((string) ($data['uid'] ?? '')), 0, 64),
        ];
    }

    /**
     * Keeps a bounded copy of the consents so an anonymous request cannot push arbitrary
     * structures into the append-only table: a service group maps to the list of its accepted
     * services (Borlabs 3), a flag stays a scalar. Anything else is dropped.
     *
     * @param array<mixed> $consents
     * @return array<string, list<string>|bool|int|float|string>
     */
    private function shape_consents(array $consents): array {
        $shaped = [];
        foreach ($consents as $key => $value) {
            if (count($shaped) >= self::MAX_CONSENT_KEYS) {
                break;
            }
            $clean_key = substr(sanitize_key((string) $key), 0, 64);
            if ('' === $clean_key) {
                continue;
            }
            if (is_array($value)) {
                $services = [];
                foreach ($value as $service) {
                    if (count($services) >= self::MAX_CONSENT_KEYS) {
                        break;
                    }
                    $clean_service = is_string($service) ? substr(sanitize_key($service), 0, self::MAX_CONSENT_VALUE_LEN) : '';
                    if ('' !== $clean_service) {
                        $services[] = $clean_service;
                    }
                }
                // Borlabs drops a group with no service before it writes the cookie; so does this.
                if ([] !== $services) {
                    $shaped[$clean_key] = $services;
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $shaped[$clean_key] = is_string($value)
                ? substr(sanitize_text_field($value), 0, self::MAX_CONSENT_VALUE_LEN)
                : $value;
        }
        return $shaped;
    }

    /**
     * Records the visitor's consent decision as one signed entry of the audit table.
     *
     * The decision comes from the borlabs-cookie cookie; a body field `consents` is only read
     * when the request carries no such cookie. No readable decision, no entry (400).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_consent_sync(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        if (empty($params) || !is_array($params)) {
            $raw_body = $request->get_body();
            $params   = '' !== $raw_body ? json_decode($raw_body, true) : [];
            $params   = is_array($params) ? $params : [];
        }

        $cookie       = $this->read_borlabs_cookie();
        $consent_data = $this->shape_consents(null !== $cookie ? $cookie['consents'] : (array) ($params['consents'] ?? []));
        if ([] === $consent_data) {
            return new WP_REST_Response(['success' => false, 'message' => __('Keine lesbare Einwilligungsentscheidung.', 'wm-suite-hub')], 400);
        }

        $session_uuid = substr(sanitize_text_field((string) ($params['uuid'] ?? wp_generate_uuid4())), 0, 64);
        $borlabs_ver  = substr(sanitize_text_field((string) ($params['borlabs_version'] ?? $this->detect_version())), 0, 32);
        $user_agent   = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT'])) : 'Unknown';
        $user_agent   = substr($user_agent, 0, self::MAX_USER_AGENT_LEN);

        // Visitors are anonymous here: the table gets the anonymised address, never the raw one.
        $raw_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';
        $ip     = '' !== $raw_ip && function_exists('wp_privacy_anonymize_ip') ? wp_privacy_anonymize_ip($raw_ip) : $raw_ip;

        try {
            $this->audit_chainer->append_event(
                'wm-sovereign-consent-vault',
                'consent_recorded',
                [
                    'session_uuid'    => $session_uuid,
                    // Borlabs' own consent id: the same decision in Borlabs' consent history.
                    'borlabs_uid'     => null !== $cookie ? $cookie['uid'] : '',
                    'borlabs_version' => $borlabs_ver,
                    'consents'        => $consent_data,
                    'timestamp_gmt'   => gmdate('c'),
                    'user_agent'      => $user_agent,
                ],
                0,
                $ip
            );

            return new WP_REST_Response([
                'success'      => true,
                'session_uuid' => $session_uuid,
                'sealed'       => true,
            ], 200);
        } catch (Throwable $e) {
            // The reason stays on the server; an anonymous visitor gets no internals.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WM-Suite-Hub] consent-sync could not write the audit entry: ' . $e->getMessage());
            }
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Die Einwilligung konnte nicht protokolliert werden.', 'wm-suite-hub'),
            ], 500);
        }
    }

    /**
     * Enqueues the frontend bridge script.
     *
     * @return void
     */
    public function enqueue_frontend_bridge(): void {
        // The bridge lives in assets/js/wm-borlabs-bridge.js (until 2026-09-14 it was a heredoc
        // printed inline); the REST URL and the nonce reach it through wp_localize_script. The
        // nonce still rides inside the page: a full-page cache would serve it for up to 24 h, after
        // which the route answers 403 and the visitor's consent simply is not mirrored.
        wp_register_script(
            'wm-borlabs-bridge',
            WM_SUITE_HUB_URL . 'assets/js/wm-borlabs-bridge.js',
            [],
            WM_SUITE_HUB_VERSION,
            true
        );
        wp_localize_script(
            'wm-borlabs-bridge',
            'wmBorlabsBridge',
            [
                'restUrl' => esc_url_raw(rest_url('wm-suite/v1/consent-sync')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]
        );
        wp_enqueue_script('wm-borlabs-bridge');
    }
}
