<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Logging;

use App\Contracts\Services\LoggerInterface;

/**
 * LoggerFactory — creates channel-scoped StructuredLogger instances.
 *
 * Supported channels: app | audit | security | queue | integration
 *
 * By default all channels write JSON-encoded log envelopes to STDERR.
 * In production, replace the write handler with a Monolog-compatible
 * transport by swapping the $writer callable.
 *
 * Log envelope schema (all fields required):
 *   timestamp      — ISO-8601 with microseconds
 *   level          — debug | info | warning | error | critical
 *   channel        — one of the supported channel names
 *   message        — human-readable description
 *   context        — arbitrary key/value pairs from the caller
 *   correlation_id — request / job correlation identifier
 *   actor_id       — authenticated user or system actor
 */
final class LoggerFactory
{
    private const VALID_CHANNELS = ['app', 'audit', 'security', 'queue', 'integration'];

    /**
     * @param callable(array<string,mixed>): void|null $writer
     *   Optional custom write handler. Receives the structured envelope array.
     *   Defaults to JSON output on STDERR.
     */
    public function __construct(
        private readonly ?string  $defaultChannel = 'app',
        private readonly ?string  $minLevel       = 'debug',
        private readonly mixed    $writer         = null,
    ) {}

    /**
     * Create a logger for the given channel.
     */
    public function make(string $channel = 'app'): LoggerInterface
    {
        $this->assertValidChannel($channel);

        return new StructuredLogger(
            channel: $channel,
            minLevel: $this->minLevel ?? 'debug',
            writer: $this->resolveWriter(),
        );
    }

    /**
     * Create a logger for the default channel.
     */
    public function makeDefault(): LoggerInterface
    {
        return $this->make($this->defaultChannel ?? 'app');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function resolveWriter(): callable
    {
        if ($this->writer !== null) {
            return $this->writer;
        }

        return static function (array $envelope): void {
            fwrite(STDERR, json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        };
    }

    private function assertValidChannel(string $channel): void
    {
        if (!in_array($channel, self::VALID_CHANNELS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown log channel "%s". Valid channels: %s.',
                $channel,
                implode(', ', self::VALID_CHANNELS),
            ));
        }
    }
}
