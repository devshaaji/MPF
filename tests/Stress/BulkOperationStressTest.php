<?php

declare(strict_types=1);

namespace Tests\Stress;

use App\Contracts\Events\EventEnvelope;
use App\Core\Infrastructure\Cache\CacheStore;
use App\Core\Infrastructure\Event\EventDispatcher;
use App\Core\Infrastructure\Queue\QueueDispatcher;
use App\Core\Infrastructure\Queue\RetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * BulkOperationStressTest — validates system stability under large batch
 * and import-style operations.
 *
 * Validates:
 *  - Queue processes 10 000 bulk-import jobs without memory explosion
 *  - Cache handles 10 000 set + flush cycle correctly
 *  - EventDispatcher handles 10 000 bulk events across multiple topics
 *  - Batch errors (partial failures) do not corrupt overall state
 *  - Resumable batch: jobs dispatched in chunks arrive fully
 */
final class BulkOperationStressTest extends TestCase
{
    // ------------------------------------------------------------------
    // Bulk queue import
    // ------------------------------------------------------------------

    public function testBulkImportOf10kJobsProcessedInFull(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = 0;

        $dispatcher->consume('bulk.import', function (array $p) use (&$processed): void {
            $processed++;
        });

        for ($i = 0; $i < 10_000; $i++) {
            $dispatcher->dispatch('bulk.import', ['record_id' => $i], "import-{$i}");
        }

        $this->assertSame(10_000, $processed, '10 000 bulk-import jobs must all be processed.');
    }

    public function testBulkImportMemoryBudget(): void
    {
        $dispatcher = new QueueDispatcher();

        $dispatcher->consume('bulk.mem', function (array $p): void {
            // Lightweight processing simulation.
        });

        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 10_000; $i++) {
            $dispatcher->dispatch('bulk.mem', ['record_id' => $i], "mem-import-{$i}");
        }

        $growthMb = (memory_get_usage(true) - $memBefore) / 1_048_576;

        // Allow ≤ 32 MB growth for 10 000 jobs (seen keys + transient state).
        $this->assertLessThan(
            32.0,
            $growthMb,
            sprintf('Bulk import memory grew %.2f MB — exceeds 32 MB budget.', $growthMb),
        );
    }

    // ------------------------------------------------------------------
    // Bulk cache operations
    // ------------------------------------------------------------------

    public function testCacheBulkSetAndFlushCycle(): void
    {
        $cache = new CacheStore(defaultTtl: 300);

        for ($i = 0; $i < 10_000; $i++) {
            $cache->set("bulk-entry-{$i}", ['record' => $i], 300);
        }

        // Verify a sample.
        $this->assertSame(['record' => 9_999], $cache->get('bulk-entry-9999'));

        $cache->flush();

        // After flush every entry must be gone.
        for ($i = 0; $i < 10; $i++) {
            $this->assertNull($cache->get("bulk-entry-{$i}"), "Entry bulk-entry-{$i} must be null after flush.");
        }
    }

    public function testCacheBulkRememberCallsCallbackOncePerKey(): void
    {
        $cache   = new CacheStore(defaultTtl: 300);
        $compute = 0;

        // Simulate 1 000 rows being loaded through a remember cache.
        for ($i = 0; $i < 1_000; $i++) {
            $cache->remember("row-{$i}", 300, function () use (&$compute, $i): array {
                $compute++;
                return ['id' => $i, 'name' => "row-{$i}"];
            });
        }

        // Second pass — all hits, no recompute.
        for ($i = 0; $i < 1_000; $i++) {
            $cache->remember("row-{$i}", 300, function () use (&$compute): array {
                $compute++;
                return [];
            });
        }

        $this->assertSame(1_000, $compute, 'Callback must run exactly once per unique key.');
    }

    // ------------------------------------------------------------------
    // Bulk event publishing
    // ------------------------------------------------------------------

    public function testBulkEventPublish10kEventsAcrossTopics(): void
    {
        $dispatcher = new EventDispatcher();
        $totals     = ['users' => 0, 'forum' => 0, 'news' => 0];

        $dispatcher->subscribe('users.bulk_event', function (EventEnvelope $e) use (&$totals): void {
            $totals['users']++;
        });
        $dispatcher->subscribe('forum.bulk_event', function (EventEnvelope $e) use (&$totals): void {
            $totals['forum']++;
        });
        $dispatcher->subscribe('news.bulk_event', function (EventEnvelope $e) use (&$totals): void {
            $totals['news']++;
        });

        for ($i = 0; $i < 10_000; $i++) {
            $topic = match ($i % 3) {
                0       => 'users.bulk_event',
                1       => 'forum.bulk_event',
                default => 'news.bulk_event',
            };
            $dispatcher->publish(EventEnvelope::create($topic, 'sys', "bulk-{$i}", ['seq' => $i]));
        }

        // 10 000 events split ~evenly: 0→3334, 1→3333, 2→3333.
        $this->assertGreaterThanOrEqual(3_333, $totals['users']);
        $this->assertGreaterThanOrEqual(3_333, $totals['forum']);
        $this->assertGreaterThanOrEqual(3_333, $totals['news']);
        $this->assertSame(10_000, $totals['users'] + $totals['forum'] + $totals['news']);
    }

    // ------------------------------------------------------------------
    // Partial-failure / resumable batch
    // ------------------------------------------------------------------

    public function testBatchWithPartialFailuresDoesNotLoseSuccessfulJobs(): void
    {
        $policy     = new RetryPolicy(maxRetries: 1);
        $dispatcher = new QueueDispatcher(retryPolicy: $policy);

        $succeeded = 0;

        // Every 10th job fails permanently.
        $dispatcher->consume('batch.partial', function (array $p) use (&$succeeded): void {
            if ($p['id'] % 10 === 0) {
                throw new \RuntimeException('Planned partial failure');
            }
            $succeeded++;
        });

        for ($i = 0; $i < 1_000; $i++) {
            $dispatcher->dispatch('batch.partial', ['id' => $i], "partial-{$i}");
        }

        // 1 000 total, 100 fail (every 10th) → 900 must succeed.
        $this->assertSame(900, $succeeded, '900 out of 1 000 jobs must succeed despite partial failures.');

        $dlq = $dispatcher->getDlqJobs();
        $this->assertCount(100, $dlq, '100 always-failing jobs must end in DLQ.');
    }

    public function testChunkedDispatchAllJobsArrive(): void
    {
        $dispatcher = new QueueDispatcher();
        $received   = [];

        $dispatcher->consume('batch.chunk', function (array $p) use (&$received): void {
            $received[] = $p['id'];
        });

        // Simulate resumable chunked import (10 chunks of 1 000).
        for ($chunk = 0; $chunk < 10; $chunk++) {
            for ($row = 0; $row < 1_000; $row++) {
                $globalId = $chunk * 1_000 + $row;
                $dispatcher->dispatch('batch.chunk', ['id' => $globalId], "chunk-{$globalId}");
            }
        }

        $this->assertCount(10_000, $received,   'All 10 000 chunked jobs must be received.');
        $this->assertSame(range(0, 9_999), $received, 'Jobs must arrive in dispatch order.');
    }
}
