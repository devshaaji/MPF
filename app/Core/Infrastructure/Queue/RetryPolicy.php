<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Queue;

/**
 * RetryPolicy — configurable retry and DLQ threshold for queue jobs.
 *
 * Backoff strategy: exponential with optional jitter.
 *   delay(attempt) = baseDelay * 2^(attempt - 1) seconds
 *
 * Example with defaults (baseDelay=1s, maxRetries=3):
 *   attempt 1 → wait 1 s
 *   attempt 2 → wait 2 s
 *   attempt 3 → wait 4 s
 *   attempt 4 → DLQ
 */
final class RetryPolicy
{
    public function __construct(
        /** Maximum number of retry attempts before moving to DLQ. */
        private readonly int   $maxRetries  = 3,
        /** Base delay in seconds for exponential back-off. */
        private readonly float $baseDelay   = 1.0,
        /** Maximum delay cap in seconds (prevents runaway back-off). */
        private readonly float $maxDelay    = 60.0,
        /** If true, add ±25 % jitter to each back-off interval. */
        private readonly bool  $jitter      = false,
    ) {}

    // ------------------------------------------------------------------
    // Policy queries
    // ------------------------------------------------------------------

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Returns true when the job should be retried (attempt < maxRetries).
     *
     * @param int $attemptCount Number of attempts already made (0-based).
     */
    public function shouldRetry(int $attemptCount): bool
    {
        return $attemptCount < $this->maxRetries;
    }

    /**
     * Returns true when the job should be sent to DLQ.
     *
     * @param int $attemptCount Number of attempts already made.
     */
    public function shouldMoveToDlq(int $attemptCount): bool
    {
        return $attemptCount >= $this->maxRetries;
    }

    /**
     * Compute the back-off delay in seconds for the given attempt number.
     *
     * @param int $attemptCount 1-based attempt number.
     */
    public function backoffSeconds(int $attemptCount): float
    {
        $delay = min($this->baseDelay * (2 ** ($attemptCount - 1)), $this->maxDelay);

        if ($this->jitter) {
            // ±25 % jitter.
            $jitter = $delay * 0.25;
            $delay  = $delay + mt_rand((int) (-$jitter * 1000), (int) ($jitter * 1000)) / 1000.0;
        }

        return max(0.0, $delay);
    }
}
