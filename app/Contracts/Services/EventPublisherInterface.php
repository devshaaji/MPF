<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Contracts\Events\EventEnvelope;

/**
 * EventPublisherInterface — contract for publishing and subscribing to domain events.
 *
 * Implementations may be synchronous (EventDispatcher) or asynchronous
 * (message-broker adapter).  Callers must not depend on delivery semantics
 * beyond "at-least-once".
 */
interface EventPublisherInterface
{
    /**
     * Publish an event envelope to all registered listeners.
     *
     * The envelope must contain all required metadata fields as defined in
     * EventEnvelope (event_id, event_name, occurred_at, actor_id,
     * correlation_id, causation_id, payload).
     */
    public function publish(EventEnvelope $event): void;

    /**
     * Register a listener for a specific event name.
     *
     * Listeners are called synchronously in registration order by the
     * synchronous dispatcher, or asynchronously by broker-backed adapters.
     *
     * @param callable(EventEnvelope): void $listener
     */
    public function subscribe(string $eventName, callable $listener): void;
}
