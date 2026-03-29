<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Queue;

use App\Contracts\Services\QueueDispatcherInterface;

/**
 * QueueDispatcher — in-memory/array-based job dispatcher for contract compliance.
 *
 * Job lifecycle:
 *   dispatch() → pending → consume() → processing
 *                                       ├─ success  → done
 *                                       └─ failure  → retry (up to maxRetries)
 *                                                      └─ DLQ
 *
 * Idempotency: a job with an already-seen idempotencyKey is silently dropped.
 *
 * Replace this implementation with a broker-backed adapter (Redis, SQS, RabbitMQ)
 * in production; the QueueDispatcherInterface contract remains unchanged.
 */
final class QueueDispatcher implements QueueDispatcherInterface
{
    private const STATUS_PENDING    = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_DONE       = 'done';
    private const STATUS_RETRY      = 'retry';
    private const STATUS_DLQ        = 'dlq';

    /**
     * @var array<string, array{
     *   id: string,
     *   topic: string,
     *   payload: array<string,mixed>,
     *   idempotency_key: string,
     *   attempts: int,
     *   status: string,
     *   dlq_reason: string,
     * }>
     *
     * Only pending/processing/retry/DLQ jobs are retained.
     * Completed (STATUS_DONE) jobs are removed immediately after processing
     * to prevent unbounded memory growth in long-running workers.
     */
    private array $jobs = [];

    /**
     * Set of idempotency keys already dispatched (deduplication).
     *
     * NOTE: In this in-memory implementation $seen grows for the lifetime of
     * the process. For a long-running worker handling millions of jobs, use a
     * broker-backed adapter (Redis SET with TTL, SQS MessageDeduplicationId,
     * etc.) which provides a bounded, TTL-expiring deduplication window.
     * @var array<string, true>
     */
    private array $seen = [];

    /**
     * @var array<string, callable(array<string,mixed>): void>
     */
    private array $handlers = [];

    public function __construct(private readonly RetryPolicy $retryPolicy = new RetryPolicy()) {}

    // ------------------------------------------------------------------
    // QueueDispatcherInterface
    // ------------------------------------------------------------------

    public function dispatch(string $topic, array $payload, string $idempotencyKey): void
    {
        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException('idempotencyKey must not be empty.');
        }

        // Deduplicate: silently ignore already-seen keys.
        if (isset($this->seen[$idempotencyKey])) {
            return;
        }

        $jobId = $this->generateJobId();

        $this->jobs[$jobId] = [
            'id'              => $jobId,
            'topic'           => $topic,
            'payload'         => $payload,
            'idempotency_key' => $idempotencyKey,
            'attempts'        => 0,
            'status'          => self::STATUS_PENDING,
            'dlq_reason'      => '',
        ];

        $this->seen[$idempotencyKey] = true;

        // If a handler is already registered, process immediately (sync).
        if (isset($this->handlers[$topic])) {
            $this->process($jobId);
        }
    }

    /**
     * @param callable(array<string,mixed>): void $handler
     */
    public function consume(string $topic, callable $handler): void
    {
        $this->handlers[$topic] = $handler;

        // Drain any pending jobs for this topic.
        foreach ($this->jobs as $jobId => $job) {
            if ($job['topic'] === $topic && $job['status'] === self::STATUS_PENDING) {
                $this->process($jobId);
            }
        }
    }

    public function retry(string $jobId): void
    {
        if (!isset($this->jobs[$jobId])) {
            throw new \RuntimeException(sprintf('Job "%s" not found.', $jobId));
        }

        $job = &$this->jobs[$jobId];

        if ($job['status'] === self::STATUS_DLQ) {
            throw new \RuntimeException(sprintf('Job "%s" is already in DLQ and cannot be retried.', $jobId));
        }

        $job['status'] = self::STATUS_PENDING;

        if (isset($this->handlers[$job['topic']])) {
            $this->process($jobId);
        }
    }

    public function toDlq(string $jobId, string $reason): void
    {
        if (!isset($this->jobs[$jobId])) {
            throw new \RuntimeException(sprintf('Job "%s" not found.', $jobId));
        }

        $this->jobs[$jobId]['status']     = self::STATUS_DLQ;
        $this->jobs[$jobId]['dlq_reason'] = $reason;
    }

    // ------------------------------------------------------------------
    // Introspection (test / debug helpers)
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null Returns null for unknown or successfully-completed jobs. */
    public function getJob(string $jobId): ?array
    {
        return $this->jobs[$jobId] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function getDlqJobs(): array
    {
        return array_values(
            array_filter($this->jobs, fn ($j) => $j['status'] === self::STATUS_DLQ),
        );
    }

    // ------------------------------------------------------------------
    // Internal processing
    // ------------------------------------------------------------------

    private function process(string $jobId): void
    {
        $handler = $this->handlers[$this->jobs[$jobId]['topic']] ?? null;

        if ($handler === null) {
            return; // No handler registered yet; job stays pending.
        }

        // Iterative retry loop — avoids recursive call-stack growth and makes
        // the retry count explicit without relying on PHP stack depth.
        while (isset($this->jobs[$jobId])) {
            $job = &$this->jobs[$jobId];
            $job['status'] = self::STATUS_PROCESSING;
            $job['attempts']++;

            try {
                $handler($job['payload']);
                // Success: remove the job to free memory; idempotency key
                // remains in $this->seen to prevent re-dispatch.
                unset($this->jobs[$jobId]);
                return;
            } catch (\Throwable) {
                if ($this->retryPolicy->shouldRetry($job['attempts'])) {
                    // Back-off is informational in this in-memory impl; a
                    // broker adapter would schedule re-delivery after
                    // backoffSeconds().
                    $job['status'] = self::STATUS_PENDING;
                    // Continue loop for next attempt.
                } else {
                    $this->toDlq($jobId, sprintf('Exceeded max retries (%d).', $this->retryPolicy->getMaxRetries()));
                    return;
                }
            }
        }
    }

    private function generateJobId(): string
    {
        return 'job-' . bin2hex(random_bytes(8));
    }
}
