<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Logging;

use App\Contracts\Services\LoggerInterface;

/**
 * StructuredLogger — concrete LoggerInterface implementation.
 *
 * Emits JSON log envelopes with required metadata:
 *   timestamp, level, channel, message, context, correlation_id, actor_id
 *
 * Instances are immutable; builder methods return clones with updated state.
 */
final class StructuredLogger implements LoggerInterface
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];

    public function __construct(
        private readonly string   $channel       = 'app',
        private readonly string   $minLevel      = 'debug',
        private readonly mixed    $writer        = null,
        private readonly string   $correlationId = '',
        private readonly string   $actorId       = '',
    ) {}

    // ------------------------------------------------------------------
    // LoggerInterface — level methods
    // ------------------------------------------------------------------

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->write('critical', $message, $context);
    }

    // ------------------------------------------------------------------
    // LoggerInterface — immutable builder
    // ------------------------------------------------------------------

    public function withChannel(string $channel): static
    {
        return new self(
            channel:       $channel,
            minLevel:      $this->minLevel,
            writer:        $this->writer,
            correlationId: $this->correlationId,
            actorId:       $this->actorId,
        );
    }

    public function withCorrelationId(string $correlationId): static
    {
        return new self(
            channel:       $this->channel,
            minLevel:      $this->minLevel,
            writer:        $this->writer,
            correlationId: $correlationId,
            actorId:       $this->actorId,
        );
    }

    public function withActorId(string $actorId): static
    {
        return new self(
            channel:       $this->channel,
            minLevel:      $this->minLevel,
            writer:        $this->writer,
            correlationId: $this->correlationId,
            actorId:       $actorId,
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (!$this->isLevelEnabled($level)) {
            return;
        }

        $envelope = [
            'timestamp'      => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'level'          => $level,
            'channel'        => $this->channel,
            'message'        => $message,
            'context'        => $context,
            'correlation_id' => $this->correlationId,
            'actor_id'       => $this->actorId,
        ];

        ($this->writer)($envelope);
    }

    private function isLevelEnabled(string $level): bool
    {
        $min     = self::LEVELS[$this->minLevel] ?? 0;
        $current = self::LEVELS[$level]          ?? 0;

        return $current >= $min;
    }
}
