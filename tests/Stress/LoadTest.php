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
 * LoadTest — simulates high-volume read/write patterns.
 *
 * Validates:
 *  - Queue handles 1 000–10 000 jobs without data loss
 *  - Cache handles 10 000 read/write cycles without memory explosion
 *  - EventDispatcher handles 10 000 publishes without degradation
 */
final class LoadTest extends TestCase
{
    // ------------------------------------------------------------------
    // Queue load
    // ------------------------------------------------------------------

    public function testQueueProcesses1kJobsWithoutDataLoss(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = [];

        $dispatcher->consume('load.test', function (array $payload) use (&$processed): void {
            $processed[] = $payload['seq'];
        });

        for ($i = 0; $i < 1_000; $i++) {
            $dispatcher->dispatch('load.test', ['seq' => $i], "load-key-{$i}");
        }

        $this->assertCount(1_000, $processed, 'All 1 000 jobs must be processed.');
        $this->assertSame(range(0, 999), $processed, 'Jobs must be processed in dispatch order.');
    }

    public function testQueueProcesses10kJobsWithoutDataLoss(): void
    {
        $dispatcher = new QueueDispatcher();
        $count      = 0;

        $dispatcher->consume('load.bulk', function (array $payload) use (&$count): void {
            $count++;
        });

        for ($i = 0; $i < 10_000; $i++) {
            $dispatcher->dispatch('load.bulk', ['seq' => $i], "bulk-key-{$i}");
        }

        $this->assertSame(10_000, $count, 'All 10 000 jobs must be processed.');
    }

    public function testQueueMemoryRemainsStableAfter10kJobs(): void
    {
        $dispatcher = new QueueDispatcher();

        $dispatcher->consume('mem.test', function (array $payload): void {
            // Simulate lightweight work.
        });

        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 10_000; $i++) {
            $dispatcher->dispatch('mem.test', ['seq' => $i], "mem-key-{$i}");
        }

        $memAfter = memory_get_usage(true);
        $growthMb = ($memAfter - $memBefore) / 1_048_576;

        // Completed jobs are removed from the jobs array; only the seen set
        // and queue overhead should remain.  Allow up to 32 MB growth for
        // 10 000 string keys in the seen set.
        $this->assertLessThan(
            32.0,
            $growthMb,
            sprintf('Memory grew by %.2f MB after 10k jobs — exceeds 32 MB budget.', $growthMb),
        );
    }

    // ------------------------------------------------------------------
    // Cache load
    // ------------------------------------------------------------------

    public function testCacheHandles10kReadWriteCycles(): void
    {
        $cache = new CacheStore(defaultTtl: 60);

        for ($i = 0; $i < 10_000; $i++) {
            $cache->set("key-{$i}", "value-{$i}", 60);
        }

        $hits = 0;

        for ($i = 0; $i < 10_000; $i++) {
            if ($cache->get("key-{$i}") !== null) {
                $hits++;
            }
        }

        $this->assertSame(10_000, $hits, 'All 10 000 cache entries must be readable.');
    }

    public function testCacheMemoryBudgetFor10kEntries(): void
    {
        $cache     = new CacheStore(defaultTtl: 60);
        $memBefore = memory_get_usage(true);

        for ($i = 0; $i < 10_000; $i++) {
            $cache->set("cache-key-{$i}", str_repeat('x', 128), 60);
        }

        $growthMb = (memory_get_usage(true) - $memBefore) / 1_048_576;

        // 10 000 × (key ~14 B + value 128 B + metadata ~80 B) ≈ 2.2 MB.
        // Allow a generous 20 MB for PHP array overhead.
        $this->assertLessThan(
            20.0,
            $growthMb,
            sprintf('Cache memory grew by %.2f MB for 10k entries — exceeds 20 MB budget.', $growthMb),
        );
    }

    // ------------------------------------------------------------------
    // Event dispatcher load
    // ------------------------------------------------------------------

    public function testEventDispatcherHandles10kPublishes(): void
    {
        $dispatcher = new EventDispatcher();
        $count      = 0;

        $dispatcher->subscribe('load.event', function (EventEnvelope $e) use (&$count): void {
            $count++;
        });

        for ($i = 0; $i < 10_000; $i++) {
            $event = EventEnvelope::create(
                eventName:     'load.event',
                actorId:       'system',
                correlationId: "req-{$i}",
                payload:       ['seq' => $i],
            );
            $dispatcher->publish($event);
        }

        $this->assertSame(10_000, $count, 'All 10 000 events must reach the listener.');
    }

    public function testEventDispatcherWithMultipleListeners10kPublishes(): void
    {
        $dispatcher = new EventDispatcher();
        $countA     = 0;
        $countB     = 0;

        $dispatcher->subscribe('load.multi', function (EventEnvelope $e) use (&$countA): void {
            $countA++;
        });
        $dispatcher->subscribe('load.multi', function (EventEnvelope $e) use (&$countB): void {
            $countB++;
        });

        for ($i = 0; $i < 10_000; $i++) {
            $dispatcher->publish(EventEnvelope::create(
                eventName:     'load.multi',
                actorId:       'system',
                correlationId: "req-{$i}",
                payload:       ['seq' => $i],
            ));
        }

        $this->assertSame(10_000, $countA, 'Listener A must receive all 10 000 events.');
        $this->assertSame(10_000, $countB, 'Listener B must receive all 10 000 events.');
    }
}
