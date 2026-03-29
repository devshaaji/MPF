<?php

declare(strict_types=1);

namespace App\Core\Infrastructure\Event;

use App\Contracts\Events\EventEnvelope;
use App\Contracts\Services\EventPublisherInterface;

/**
 * EventDispatcher — synchronous in-process event dispatcher.
 *
 * Maintains a registry of listeners keyed by event name and dispatches
 * the full EventEnvelope to every matching listener in registration order.
 *
 * Rules:
 *  - Delivery is synchronous; listeners run in the same PHP call-stack.
 *  - No async/queue behaviour (see INFRA-QUEUE-001 for that).
 *  - Wildcard '*' listener receives ALL events (useful for audit logging).
 *  - Exceptions thrown by listeners propagate to the caller; the dispatcher
 *    does NOT swallow errors — callers should catch at use-case boundaries.
 */
final class EventDispatcher implements EventPublisherInterface
{
    /**
     * @var array<string, list<callable(EventEnvelope): void>>
     */
    private array $listeners = [];

    // ------------------------------------------------------------------
    // EventPublisherInterface
    // ------------------------------------------------------------------

    public function publish(EventEnvelope $event): void
    {
        $targets = array_merge(
            $this->listeners[$event->eventName] ?? [],
            $this->listeners['*']              ?? [],
        );

        foreach ($targets as $listener) {
            $listener($event);
        }
    }

    /**
     * @param callable(EventEnvelope): void $listener
     */
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    // ------------------------------------------------------------------
    // Introspection (test/debug helpers)
    // ------------------------------------------------------------------

    /**
     * Return the number of registered listeners for an event name.
     * Pass '*' to count wildcard listeners.
     */
    public function listenerCount(string $eventName): int
    {
        return count($this->listeners[$eventName] ?? []);
    }

    /**
     * Remove all listeners (useful in test tear-down).
     */
    public function reset(): void
    {
        $this->listeners = [];
    }
}
