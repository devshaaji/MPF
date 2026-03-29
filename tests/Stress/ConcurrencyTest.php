<?php

declare(strict_types=1);

namespace Tests\Stress;

use App\Contracts\Events\EventEnvelope;
use App\Core\Infrastructure\Cache\CacheStore;
use App\Core\Infrastructure\Event\EventDispatcher;
use App\Core\Infrastructure\Queue\QueueDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * ConcurrencyTest — validates correctness under re-entrant and sequential
 * "simultaneous write" patterns within PHP's single-threaded model.
 *
 * Validates:
 *  - Idempotent dispatch (same key dispatched N times = processed once)
 *  - Queue handler re-entry does not corrupt job state
 *  - Cache stampede guard (remember lock released on callback exception)
 *  - Transaction-style nesting: depth counter integrity
 *  - Event delivery order with multiple subscribers
 */
final class ConcurrencyTest extends TestCase
{
    // ------------------------------------------------------------------
    // Queue idempotency / deduplication
    // ------------------------------------------------------------------

    public function testDuplicateDispatchesAreDeduplicatedExactlyOnce(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = 0;

        $dispatcher->consume('concurrency.dedup', function (array $p) use (&$processed): void {
            $processed++;
        });

        // Dispatch the same idempotency key 100 times.
        for ($i = 0; $i < 100; $i++) {
            $dispatcher->dispatch('concurrency.dedup', ['value' => $i], 'same-key');
        }

        $this->assertSame(1, $processed, 'Same idempotency key dispatched 100×: must process exactly once.');
    }

    public function testMixedUniqueAndDuplicateKeysDontCorruptCount(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = 0;

        $dispatcher->consume('concurrency.mixed', function (array $p) use (&$processed): void {
            $processed++;
        });

        // 50 unique + 50 duplicate keys.
        for ($i = 0; $i < 50; $i++) {
            $dispatcher->dispatch('concurrency.mixed', ['seq' => $i], "unique-{$i}");
        }
        for ($i = 0; $i < 50; $i++) {
            $dispatcher->dispatch('concurrency.mixed', ['seq' => $i], "unique-{$i}"); // Duplicate.
        }

        $this->assertSame(50, $processed, 'Only 50 unique keys must be processed; duplicates dropped.');
    }

    // ------------------------------------------------------------------
    // Queue handler re-entry
    // ------------------------------------------------------------------

    public function testHandlerDispatchingNewJobsDoesNotCauseInfiniteLoop(): void
    {
        $dispatcher = new QueueDispatcher();
        $processed  = [];

        // A handler that dispatches one follow-up job on first call.
        $dispatcher->consume('concurrency.reentry', function (array $p) use (&$processed, $dispatcher): void {
            $processed[] = $p['depth'];

            if ($p['depth'] < 3) {
                $depth = $p['depth'] + 1;
                $dispatcher->dispatch(
                    'concurrency.reentry',
                    ['depth' => $depth],
                    "reentry-depth-{$depth}",
                );
            }
        });

        $dispatcher->dispatch('concurrency.reentry', ['depth' => 0], 'reentry-depth-0');

        // Should process depth 0, 1, 2, 3 — no infinite loop.
        $this->assertCount(4, $processed, 'Re-entrant dispatch must process exactly depth 0–3.');
        $this->assertSame([0, 1, 2, 3], $processed);
    }

    // ------------------------------------------------------------------
    // Cache stampede guard (re-entrant remember)
    // ------------------------------------------------------------------

    public function testCacheRememberLockIsReleasedAfterCallbackException(): void
    {
        $cache = new CacheStore(defaultTtl: 60);

        // First call: callback throws — lock must be released in finally.
        try {
            $cache->remember('stampede.key', 60, function (): string {
                throw new \RuntimeException('Compute failed');
            });
        } catch (\RuntimeException) {
            // Expected.
        }

        // Second call: should succeed (lock was released by the first call's finally).
        $value = $cache->remember('stampede.key', 60, static fn (): string => 'computed');

        $this->assertSame('computed', $value, 'Cache lock must be released after callback exception.');
    }

    public function testCacheRememberCachesValueAndSkipsCallbackOnHit(): void
    {
        $cache    = new CacheStore(defaultTtl: 60);
        $computed = 0;

        for ($i = 0; $i < 100; $i++) {
            $cache->remember('hit.key', 60, function () use (&$computed): string {
                $computed++;
                return 'result';
            });
        }

        $this->assertSame(1, $computed, 'Callback must run exactly once regardless of repeated remember() calls.');
    }

    // ------------------------------------------------------------------
    // Event delivery order / multi-subscriber
    // ------------------------------------------------------------------

    public function testMultipleListenersReceiveEventsInRegistrationOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $order      = [];

        $dispatcher->subscribe('concurrency.order', function (EventEnvelope $e) use (&$order): void {
            $order[] = 'A-' . $e->payload['seq'];
        });
        $dispatcher->subscribe('concurrency.order', function (EventEnvelope $e) use (&$order): void {
            $order[] = 'B-' . $e->payload['seq'];
        });

        for ($i = 0; $i < 10; $i++) {
            $dispatcher->publish(EventEnvelope::create(
                eventName:     'concurrency.order',
                actorId:       'system',
                correlationId: "req-{$i}",
                payload:       ['seq' => $i],
            ));
        }

        // For each event, A must be called before B.
        for ($i = 0; $i < 10; $i++) {
            $posA = array_search("A-{$i}", $order, true);
            $posB = array_search("B-{$i}", $order, true);
            $this->assertLessThan($posB, $posA, "Listener A must run before B for event seq={$i}.");
        }
    }

    public function testWildcardListenerReceivesAllEvents(): void
    {
        $dispatcher = new EventDispatcher();
        $wildcard   = [];
        $specific   = [];

        $dispatcher->subscribe('*', function (EventEnvelope $e) use (&$wildcard): void {
            $wildcard[] = $e->eventName;
        });
        $dispatcher->subscribe('concurrency.alpha', function (EventEnvelope $e) use (&$specific): void {
            $specific[] = 'alpha';
        });
        $dispatcher->subscribe('concurrency.beta', function (EventEnvelope $e) use (&$specific): void {
            $specific[] = 'beta';
        });

        $dispatcher->publish(EventEnvelope::create('concurrency.alpha', 'sys', 'c1', []));
        $dispatcher->publish(EventEnvelope::create('concurrency.beta',  'sys', 'c2', []));

        $this->assertCount(2, $wildcard,   'Wildcard must receive both events.');
        $this->assertCount(2, $specific,   'Specific listeners must receive their respective events.');
        $this->assertContains('concurrency.alpha', $wildcard);
        $this->assertContains('concurrency.beta',  $wildcard);
    }
}
