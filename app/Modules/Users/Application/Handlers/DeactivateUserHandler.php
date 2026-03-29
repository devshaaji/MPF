<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Handlers;

use App\Contracts\Events\EventEnvelope;
use App\Contracts\Services\CacheStoreInterface;
use App\Contracts\Services\EventPublisherInterface;
use App\Contracts\Services\LoggerInterface;
use App\Modules\Users\Application\Commands\DeactivateUserCommand;
use App\Modules\Users\Domain\UserRepositoryInterface;

/**
 * DeactivateUserHandler — marks a user account as deactivated.
 *
 * Idempotent: deactivating an already-deactivated user is a no-op (no event emitted).
 * After persistence, the user profile cache is invalidated.
 *
 * Emits: users.user_deactivated (only when status actually changes)
 */
final class DeactivateUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventPublisherInterface $eventPublisher,
        private readonly CacheStoreInterface     $cache,
        private readonly LoggerInterface         $logger,
    ) {}

    /**
     * @throws \RuntimeException when the user is not found.
     */
    public function handle(DeactivateUserCommand $command): void
    {
        $log = $this->logger
            ->withChannel('audit')
            ->withCorrelationId($command->correlationId)
            ->withActorId($command->actorId);

        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            $log->warning('DeactivateUser: user not found', ['user_id' => $command->userId]);
            throw new \RuntimeException(sprintf('User "%s" not found.', $command->userId));
        }

        // --- Idempotency: no-op if already deactivated ---
        if (!$user->isActive() && $user->getStatus() === 'deactivated') {
            $log->info('DeactivateUser: already deactivated — no-op', [
                'user_id' => $command->userId,
            ]);
            return;
        }

        $user->deactivate();
        $this->userRepository->save($user);

        // --- Invalidate profile cache ---
        $this->cache->forget('users.profile.' . $command->userId);

        $log->info('DeactivateUser: user deactivated', [
            'user_id'        => $command->userId,
            'reason'         => $command->reason,
            'deactivated_by' => $command->deactivatedBy,
        ]);

        // --- Emit users.user_deactivated ---
        $event = EventEnvelope::create(
            eventName:     'users.user_deactivated',
            actorId:       $command->actorId,
            correlationId: $command->correlationId,
            payload:       [
                'user_id'        => $command->userId,
                'reason'         => $command->reason,
                'deactivated_by' => $command->deactivatedBy,
            ],
            causationId: $command->causationId,
        );

        $this->eventPublisher->publish($event);
    }
}
