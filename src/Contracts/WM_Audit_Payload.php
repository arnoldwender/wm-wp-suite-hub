<?php
declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable Data Transfer Object for Compliance Audit Events.
 *
 * Enforces strict typing, structured context, and origin attribution
 * for inclusion in the tamper-evident HMAC-SHA256 audit ledger.
 *
 * @package ArnoldWender\SuiteHub\Contracts
 * @since   1.1.0
 */
final readonly class WM_Audit_Payload {

    /**
     * @param string               $event_type    Action/Event type identifier (e.g. 'order.placed', 'consent.granted').
     * @param string               $severity      Severity level: 'INFO', 'WARNING', 'CRITICAL', 'EMERGENCY'.
     * @param string               $actor_id      Actor identifier (User ID, 'SYSTEM', or anonymized hash).
     * @param array<string, mixed> $context_data  Structured metadata (must be sanitized, no raw PII).
     * @param string               $origin_module Identifier of the emitting plugin or integration.
     * @param int                  $timestamp     UNIX timestamp of event generation.
     */
    public function __construct(
        public string $event_type,
        public string $severity = 'INFO',
        public string $actor_id = 'SYSTEM',
        public array $context_data = [],
        public string $origin_module = 'unknown',
        public int $timestamp = 0
    ) {}

    /**
     * Fluent factory constructor conforming to WM Suite Compliance SDK specification.
     *
     * @param string               $source_module  Originating module slug.
     * @param string               $event_type     Event/Action identifier.
     * @param string               $category       Category (e.g. 'finance', 'privacy', 'security').
     * @param string               $severity       Severity ('INFO', 'WARNING', 'CRITICAL', 'EMERGENCY').
     * @param int                  $actor_id       User ID or 0 for system.
     * @param string               $resource_id    Affected resource ID.
     * @param string               $payload_digest Optional SHA-256 payload digest.
     * @param array<string, mixed> $metadata       Contextual metadata.
     * @return self
     */
    public static function create(
        string $source_module,
        string $event_type,
        string $category = 'general',
        string $severity = 'info',
        int $actor_id = 0,
        string $resource_id = '',
        string $payload_digest = '',
        array $metadata = []
    ): self {
        $context = $metadata;
        if ($category !== '') {
            $context['category'] = sanitize_key($category);
        }
        if ($resource_id !== '') {
            $context['resource_id'] = sanitize_text_field($resource_id);
        }
        if ($payload_digest !== '') {
            $context['payload_digest'] = sanitize_text_field($payload_digest);
        }

        return new self(
            event_type: sanitize_key($event_type),
            severity: strtoupper($severity),
            actor_id: $actor_id > 0 ? (string) $actor_id : 'SYSTEM',
            context_data: $context,
            origin_module: sanitize_key($source_module),
            timestamp: time()
        );
    }

    /**
     * Factory constructor normalizing from array or scalar input.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function from_array(array $data): self {
        $severity = strtoupper((string) ($data['severity'] ?? 'INFO'));
        if (!in_array($severity, ['INFO', 'WARNING', 'CRITICAL', 'EMERGENCY'], true)) {
            $severity = 'INFO';
        }

        $timestamp = (int) ($data['timestamp'] ?? 0);
        if ($timestamp <= 0) {
            $timestamp = time();
        }

        $event_type = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string) ($data['event_type'] ?? 'generic.event'));
        if (empty($event_type)) {
            $event_type = 'generic.event';
        }

        return new self(
            event_type: $event_type,
            severity: $severity,
            actor_id: sanitize_text_field((string) ($data['actor_id'] ?? 'SYSTEM')),
            context_data: is_array($data['context_data'] ?? null) ? $data['context_data'] : [],
            origin_module: sanitize_key((string) ($data['origin_module'] ?? 'external-integration')),
            timestamp: $timestamp
        );
    }

    /**
     * Serializes payload to array for hash generation and storage.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'event_type'    => $this->event_type,
            'severity'      => $this->severity,
            'actor_id'      => $this->actor_id,
            'context_data'  => $this->context_data,
            'origin_module' => $this->origin_module,
            'timestamp'     => $this->timestamp > 0 ? $this->timestamp : time(),
        ];
    }
}
