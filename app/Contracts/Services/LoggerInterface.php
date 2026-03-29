<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * LoggerInterface — structured logging contract for all platform services.
 *
 * Every log record must carry a channel, correlation ID, and actor ID so
 * that distributed traces can be reconstructed from log aggregation.
 *
 * Usage pattern (immutable builder):
 *   $logger
 *       ->withChannel('audit')
 *       ->withCorrelationId($correlationId)
 *       ->withActorId($actorId)
 *       ->info('User logged in', ['user_id' => $id]);
 */
interface LoggerInterface
{
    // ------------------------------------------------------------------
    // Log-level methods
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void;

    // ------------------------------------------------------------------
    // Immutable builder — return new instance with the given attribute set
    // ------------------------------------------------------------------

    /**
     * Return a new logger scoped to the given channel.
     * Valid channels: app, audit, security, queue, integration.
     */
    public function withChannel(string $channel): static;

    /**
     * Return a new logger that stamps every record with the given correlation ID.
     */
    public function withCorrelationId(string $correlationId): static;

    /**
     * Return a new logger that stamps every record with the given actor ID.
     */
    public function withActorId(string $actorId): static;
}
