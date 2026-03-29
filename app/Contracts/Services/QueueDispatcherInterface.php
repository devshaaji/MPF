<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * QueueDispatcherInterface — contract for async job dispatch and lifecycle management.
 *
 * Delivery guarantees:
 *  - dispatch() enqueues the job exactly once per unique idempotencyKey.
 *  - consume() processes jobs for a given topic.
 *  - Failed jobs are retried per RetryPolicy, then moved to DLQ.
 */
interface QueueDispatcherInterface
{
    /**
     * Enqueue a job payload on the given topic.
     *
     * @param array<string, mixed> $payload       Serialisable job data.
     * @param string               $idempotencyKey Unique key to deduplicate re-dispatches.
     */
    public function dispatch(string $topic, array $payload, string $idempotencyKey): void;

    /**
     * Register a handler callable for the given topic and begin processing.
     *
     * @param callable(array<string,mixed>): void $handler
     */
    public function consume(string $topic, callable $handler): void;

    /**
     * Manually trigger a retry for a specific job ID.
     */
    public function retry(string $jobId): void;

    /**
     * Move a job to the dead-letter queue with a failure reason.
     */
    public function toDlq(string $jobId, string $reason): void;
}
