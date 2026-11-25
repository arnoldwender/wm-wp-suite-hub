<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Telemetry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use wpdb;
use Throwable;

/**
 * Class Audit_Chainer
 *
 * A linear, append-only hash chain over one table: every row carries the HMAC-SHA256 of its own fields
 * and of the previous row's digest, keyed with wp_salt('auth'). A change, deletion or reordering made
 * without the site key breaks the chain at that row. It is a chain, not a Merkle tree, and it shows that
 * rows were not altered; it does not make their content true or assess any law.
 *
 * @package ArnoldWender\SuiteHub\Telemetry
 * @since   1.0.0
 */
final class Audit_Chainer {

    /**
     * Genesis block hash seed for the initial chain entry.
     */
    private const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Rows read per query while the chain is verified. The walk holds one page in memory, never the table:
     * until 1.2.3 every row was loaded at once, and the dashboard widget verifies on each visit.
     */
    private const VERIFY_PAGE_SIZE = 500;

    /**
     * Database table name without prefix.
     */
    private const TABLE_SUFFIX = 'wm_suite_audit_log';

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private wpdb $db;

    /**
     * Full database table name including prefix.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Retrieves singleton instance of Audit_Chainer.
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
     * Audit_Chainer constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->db         = $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_SUFFIX;
        self::$instance   = $this;
    }

    /**
     * Appends a new verified event to the cryptographic audit chain.
     *
     * @param string               $module_slug Unique slug of the originating module.
     * @param string               $event_type  Identifier of the event (e.g., 'nda_denied', 'cdn_blocked').
     * @param array<string, mixed> $payload     Contextual event data.
     * @param int                  $user_id     ID of the triggering user (0 if anonymous/guest).
     * @param string               $ip_address  Client IP address (anonymized or raw according to policy).
     * @return string Sealed SHA-256 HMAC hash of the new entry.
     */
    public function append_event(
        string $module_slug,
        string $event_type,
        array $payload,
        int $user_id = 0,
        string $ip_address = ''
    ): string {
        $previous_hash = $this->get_latest_hash();
        $salt          = wp_salt('auth');
        $json_payload  = (string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp     = current_time('mysql');

        if (empty($ip_address) && isset($_SERVER['REMOTE_ADDR'])) {
            $ip_address = sanitize_text_field((string) $_SERVER['REMOTE_ADDR']);
        }

        // Cryptographic proof: HMAC(payload + metadata + previous_hash, salt)
        $data_to_seal = $timestamp . '|' . $module_slug . '|' . $event_type . '|' . $user_id . '|' . $ip_address . '|' . $json_payload . '|' . $previous_hash;
        $current_hash = hash_hmac('sha256', $data_to_seal, $salt);

        try {
            $this->db->insert(
                $this->table_name,
                [
                    'timestamp'     => $timestamp,
                    'module_slug'   => sanitize_key($module_slug),
                    'event_type'    => preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $event_type),
                    'user_id'       => $user_id,
                    'ip_address'    => sanitize_text_field($ip_address),
                    'payload'       => $json_payload,
                    'previous_hash' => $previous_hash,
                    'current_hash'  => $current_hash,
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            // Optional bridge to wm-activity-sentinel if active
            do_action('wm_activity_sentinel_log_event', [
                'module_slug'  => $module_slug,
                'event_type'   => $event_type,
                'current_hash' => $current_hash,
                'timestamp'    => $timestamp,
            ]);

            return $current_hash;
        } catch (Throwable $e) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( sprintf( '[WM Suite Hub] Audit Chainer failure: %s', $e->getMessage() ) ); }
            return '';
        }
    }

    /**
     * Verifies the entire cryptographic hash chain in the database.
     * Identifies any unauthorized database record insertions, updates, or deletions.
     *
     * @return array{intact: bool, total_checked: int, broken_at_id: ?int, details: string}
     */
    public function verify_chain_integrity(): array {
        $salt               = wp_salt('auth');
        $expected_prev_hash = self::GENESIS_HASH;
        $count              = 0;
        $last_id            = 0;

        // Keyset pages (id > last id): memory stays at one page however long the table grows.
        do {
            $rows = $this->db->get_results(
                $this->db->prepare(
                    "SELECT id, timestamp, module_slug, event_type, user_id, ip_address, payload, previous_hash, current_hash
                     FROM {$this->table_name} WHERE id > %d ORDER BY id ASC LIMIT %d",
                    $last_id,
                    self::VERIFY_PAGE_SIZE
                ),
                defined('ARRAY_A') ? \ARRAY_A : 'ARRAY_A'
            );
            $rows = is_array($rows) ? $rows : [];

            foreach ($rows as $row) {
                $count++;
                $id            = (int) $row['id'];
                $row_prev_hash = (string) $row['previous_hash'];
                $row_curr_hash = (string) $row['current_hash'];
                $last_id       = $id;

                // 1. Check chain linkage.
                // hash_equals() and not !==: both operands are digests, and a length-independent
                // comparison is what the CHANGELOG has claimed since 1.2.0. Pinned by suite test 3b.
                if (!hash_equals($expected_prev_hash, $row_prev_hash)) {
                    return [
                        'intact'        => false,
                        'total_checked' => $count,
                        'broken_at_id'  => $id,
                        /* translators: %d: id of the audit entry */
                        'details'       => sprintf(__('Eintrag #%d verweist nicht auf seinen Vorgänger: Ein Eintrag davor wurde gelöscht, eingefügt oder geändert.', 'wm-suite-hub'), $id),
                    ];
                }

                // 2. Re-compute HMAC and verify signature
                $data_to_seal = $row['timestamp'] . '|' . $row['module_slug'] . '|' . $row['event_type'] . '|' . $row['user_id'] . '|' . $row['ip_address'] . '|' . $row['payload'] . '|' . $row_prev_hash;
                $recalculated_hash = hash_hmac('sha256', $data_to_seal, $salt);

                if (!hash_equals($recalculated_hash, $row_curr_hash)) {
                    // A changed AUTH_SALT breaks every signature the same way, so the message names both causes.
                    return [
                        'intact'        => false,
                        'total_checked' => $count,
                        'broken_at_id'  => $id,
                        /* translators: %d: id of the audit entry */
                        'details'       => sprintf(__('Die Signatur von Eintrag #%d stimmt nicht: Der Eintrag wurde geändert, oder der Schlüssel der Website (AUTH_SALT) ist nicht mehr der, mit dem er geschrieben wurde.', 'wm-suite-hub'), $id),
                    ];
                }

                $expected_prev_hash = $row_curr_hash;
            }
        } while (count($rows) === self::VERIFY_PAGE_SIZE);

        return [
            'intact'        => true,
            'total_checked' => $count,
            'broken_at_id'  => null,
            'details'       => 0 === $count
                ? __('Die Audit-Tabelle ist leer.', 'wm-suite-hub')
                /* translators: %d: number of audit entries checked */
                : sprintf(__('%d Einträge geprüft, die Kette ist lückenlos.', 'wm-suite-hub'), $count),
        ];
    }

    /**
     * Retrieves recent audit logs for the Cockpit UI.
     *
     * @param int         $limit       Maximum number of records.
     * @param string|null $module_slug Optional module slug filter.
     * @return array<int, array<string, mixed>>
     */
    public function get_recent_logs(int $limit = 50, ?string $module_slug = null): array {
        $limit = max(1, min(200, $limit));

        if (!empty($module_slug)) {
            $query = $this->db->prepare(
                "SELECT id, timestamp, module_slug, event_type, user_id, ip_address, payload, current_hash 
                 FROM {$this->table_name} WHERE module_slug = %s ORDER BY id DESC LIMIT %d",
                sanitize_key($module_slug),
                $limit
            );
        } else {
            $query = $this->db->prepare(
                "SELECT id, timestamp, module_slug, event_type, user_id, ip_address, payload, current_hash 
                 FROM {$this->table_name} ORDER BY id DESC LIMIT %d",
                $limit
            );
        }

        $results = $this->db->get_results($query, defined('ARRAY_A') ? \ARRAY_A : 'ARRAY_A');
        return is_array($results) ? $results : [];
    }

    /**
     * Retrieves the latest hash in the database to chain against.
     *
     * @return string
     */
    private function get_latest_hash(): string {
        $query = "SELECT current_hash FROM {$this->table_name} ORDER BY id DESC LIMIT 1";
        $latest = $this->db->get_var($query);

        return !empty($latest) && is_string($latest) ? $latest : self::GENESIS_HASH;
    }
}
