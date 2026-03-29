<?php

declare(strict_types=1);

namespace App\Contracts\Events;

/**
 * EventEnvelope — canonical event envelope for all domain events.
 *
 * Every event published on the platform must be wrapped in this class.
 * Consumers bind to the envelope schema, not to ad-hoc payload fields.
 *
 * Required fields (AGENTS.md §8):
 *   event_id       — UUID v4, unique per event occurrence
 *   event_name     — dot-notation name, e.g. "users.user_deactivated"
 *   occurred_at    — ISO-8601 timestamp at time of emission
 *   actor_id       — authenticated user / system component that triggered the event
 *   correlation_id — request/job trace identifier
 *   causation_id   — event_id of the event that caused this one ('' if root)
 *   payload        — module-specific data; must conform to a versioned schema
 */
final class EventEnvelope
{
    /**
     * @param array<string, mixed> $payload Module-specific event data.
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventName,
        public readonly string $occurredAt,
        public readonly string $actorId,
        public readonly string $correlationId,
        public readonly string $causationId,
        public readonly array  $payload,
    ) {}

    /**
     * Factory: create a new envelope with a generated ID and current timestamp.
     *
     * @param array<string, mixed> $payload
     */
    public static function create(
        string $eventName,
        string $actorId,
        string $correlationId,
        array  $payload,
        string $causationId = '',
    ): self {
        return new self(
            eventId:       self::generateId(),
            eventName:     $eventName,
            occurredAt:    (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            actorId:       $actorId,
            correlationId: $correlationId,
            causationId:   $causationId,
            payload:       $payload,
        );
    }

    /**
     * Serialize to an associative array for transport or logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id'       => $this->eventId,
            'event_name'     => $this->eventName,
            'occurred_at'    => $this->occurredAt,
            'actor_id'       => $this->actorId,
            'correlation_id' => $this->correlationId,
            'causation_id'   => $this->causationId,
            'payload'        => $this->payload,
        ];
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function generateId(): string
    {
        // UUID v4 without external dependency.
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
