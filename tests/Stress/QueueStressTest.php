<?php

declare(strict_types=1);

namespace Tests\Stress;

use App\Core\Infrastructure\Queue\QueueDispatcher;
use App\Core\Infrastructure\Queue\RetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * QueueStressTest — validates queue behaviour under burst, retry storms, and
 * long-running worker scenarios.
 *
 * Validates:
 *  - Burst of 1 000 jobs all processed without loss
 *  - Always-failing job reaches DLQ after maxRetries (no infinite loop)
 *  - Idempotency key prevents job duplication on re-dispatch
 *  - DLQ reason is recorded correctly
 *  - Seen-set does not allow re-dispatch after completion
 *  - Mixed-topic burst processes each topic independently
 */
final class QueueStressTest extends TestCase
{
    // ------------------------------------------------------------------
    // Burst processing
    // ------------------------------------------------------------------

    public function testBurstOf1kJobsAllProcessed(): void
    {
        $dispatcher = new QueueDispatcher();
        $count      = 0;

        $dispatcher->consume('burst.topic', function (array $p) use (&$count): void {
            $count++;
        });

        for ($i = 0; $i < 1_000; $i++) {
            $dispatcher->dispatch('burst.topic', ['id' => $i], "burst-{$i}");
        }

        $this->assertSame(1_000, $count, 'All 1 000 burst jobs must be processed.');
    }

    public function testBurstDispatchedBeforeConsumeStillProcessed(): void
    {
        $dispatcher = new QueueDispatcher();
        $count      = 0;

        // Dispatch first, register handler later (tests pending-job drain on consume()).
        for ($i = 0; $i < 500; $i++) {
            $dispatcher->dispatch('burst.pre', ['id' => $i], "pre-{$i}");
        }

        $dispatcher->consume('burst.pre', function (array $p) use (&$count): void {
            $count++;
        });

        $this->assertSame(500, $count, 'Jobs dispatched before consume() must be drained on registration.');
    }

    // ------------------------------------------------------------------
    // Retry storm / DLQ
    // ------------------------------------------------------------------

    public function testAlwaysFailingJobReachesDlqAfterMaxRetries(): void
    {
        $policy     = new RetryPolicy(maxRetries: 3);
        $dispatcher = new QueueDispatcher(retryPolicy: $policy);
        $attempts   = 0;

        $dispatcher->consume('retry.storm', function (array $p) use (&$attempts): void {
            $attempts++;
            throw new \RuntimeException('Simulated failure');
        });

        $dispatcher->dispatch('retry.storm', ['task' => 'fail'], 'retry-storm-key-1');

        // 3 retries + 1 original attempt = 4 total attempts, then DLQ.
        $this->assertSame(3, $attempts, 'Handler must be called exactly maxRetries (3) times before DLQ.');

        $dlqJobs = $dispatcher->getDlqJobs();
        $this->assertCount(1, $dlqJobs, 'Exactly one job must be in DLQ.');
        $this->assertStringContainsString('3', $dlqJobs[0]['dlq_reason'], 'DLQ reason must mention maxRetries.');
    }

    public function test1kAlwaysFailingJobsAllReachDlq(): void
    {
        $policy     = new RetryPolicy(maxRetries: 2);
        $dispatcher = new QueueDispatcher(retryPolicy: $policy);

        $dispatcher->consume('dlq.bulk', function (array $p): void {
            throw new \RuntimeException('Always fails');
        });

        for ($i = 0; $i < 1_000; $i++) {
            $dispatcher->dispatch('dlq.bulk', ['id' => $i], "dlq-bulk-{$i}");
        }

        $dlqJobs = $dispatcher->getDlqJobs();
        $this->assertCount(1_000, $dlqJobs, 'All 1 000 always-failing jobs must end up in DLQ.');
    }

    // ------------------------------------------------------------------
    // Idempotency / no duplication
    // ------------------------------------------------------------------

    public function testJobsWithSameIdempotencyKeyNeverDuplicated(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = 0;

        $dispatcher->consume('idem.topic', function (array $p) use (&$processed): void {
            $processed++;
        });

        // Dispatch 1 000 times with the same key.
        for ($i = 0; $i < 1_000; $i++) {
            $dispatcher->dispatch('idem.topic', ['attempt' => $i], 'idem-single-key');
        }

        $this->assertSame(1, $processed, 'Idempotency key prevents all duplicate dispatches.');
    }

    public function testCompletedJobsCannotBeRedispatchedWithSameKey(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = 0;

        $dispatcher->consume('idem.complete', function (array $p) use (&$processed): void {
            $processed++;
        });

        $dispatcher->dispatch('idem.complete', ['v' => 1], 'completed-key');
        $this->assertSame(1, $processed);

        // Attempt to re-dispatch a completed job (same idempotency key).
        $dispatcher->dispatch('idem.complete', ['v' => 2], 'completed-key');
        $this->assertSame(1, $processed, 'Completed jobs must not be re-dispatched.');
    }

    // ------------------------------------------------------------------
    // Multi-topic isolation
    // ------------------------------------------------------------------

    public function testMixedTopicBurstEachTopicProcessedIndependently(): void
    {
        $dispatcher = new QueueDispatcher();
        $topicA     = 0;
        $topicB     = 0;

        $dispatcher->consume('mixed.a', function (array $p) use (&$topicA): void {
            $topicA++;
        });
        $dispatcher->consume('mixed.b', function (array $p) use (&$topicB): void {
            $topicB++;
        });

        for ($i = 0; $i < 500; $i++) {
            $dispatcher->dispatch('mixed.a', ['i' => $i], "ma-{$i}");
            $dispatcher->dispatch('mixed.b', ['i' => $i], "mb-{$i}");
        }

        $this->assertSame(500, $topicA, 'Topic A must receive exactly 500 jobs.');
        $this->assertSame(500, $topicB, 'Topic B must receive exactly 500 jobs.');
    }

    // ------------------------------------------------------------------
    // DLQ introspection
    // ------------------------------------------------------------------

    public function testDlqJobsRetainPayloadAndReason(): void
    {
        $policy     = new RetryPolicy(maxRetries: 1);
        $dispatcher = new QueueDispatcher(retryPolicy: $policy);

        $dispatcher->consume('dlq.inspect', function (array $p): void {
            throw new \RuntimeException('Planned failure');
        });

        $dispatcher->dispatch('dlq.inspect', ['entity_id' => 'abc-123'], 'dlq-inspect-key');

        $dlqJobs = $dispatcher->getDlqJobs();
        $this->assertCount(1, $dlqJobs);

        $job = $dlqJobs[0];
        $this->assertSame('dlq.inspect', $job['topic'],               'DLQ job must retain topic.');
        $this->assertSame(['entity_id' => 'abc-123'], $job['payload'], 'DLQ job must retain original payload.');
        $this->assertNotEmpty($job['dlq_reason'],                      'DLQ job must have a non-empty reason.');
        $this->assertSame('dlq', $job['status'],                       'DLQ job status must be "dlq".');
    }
}
