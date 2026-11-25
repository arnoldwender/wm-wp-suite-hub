<?php
declare(strict_types=1);

namespace ArnoldWender\ComplianceSDK\Payloads;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final readonly class AuditPayload {
    public function __construct(
        public string $eventType,
        public string $severity = 'INFO',
        public ?string $actorId = null,
        public array $contextData = [],
        public ?string $originModule = null,
        public int $timestamp = 0
    ) {}

    public function toArray(): array {
        return [
            'event_type'    => $this->eventType,
            'severity'      => $this->severity,
            'actor_id'      => $this->actorId ?? 'SYSTEM',
            'context_data'  => $this->contextData,
            'origin_module' => $this->originModule ?? 'sdk-integration',
            'timestamp'     => $this->timestamp > 0 ? $this->timestamp : time(),
        ];
    }
}
