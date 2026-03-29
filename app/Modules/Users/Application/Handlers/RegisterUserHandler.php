<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Handlers;

use App\Contracts\Events\EventEnvelope;
use App\Contracts\Services\CacheStoreInterface;
use App\Contracts\Services\EventPublisherInterface;
use App\Contracts\Services\LoggerInterface;
use App\Modules\Users\Application\Commands\RegisterUserCommand;
use App\Modules\Users\Domain\User;
use App\Modules\Users\Domain\UserRepositoryInterface;
use App\Modules\Users\Infrastructure\UuidGenerator;

/**
 * RegisterUserHandler — creates a new platform user from an identity-provider registration.
 *
 * Idempotent: if the provider+providerSubject pair already exists the existing
 * user id is returned without creating a duplicate or re-emitting the event.
 *
 * Emits: users.user_registered
 */
final class RegisterUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventPublisherInterface $eventPublisher,
        private readonly CacheStoreInterface     $cache,
        private readonly LoggerInterface         $logger,
    ) {}

    /**
     * @return string The platform user UUID (existing or newly created).
     * @throws \InvalidArgumentException on validation failure.
     * @throws \RuntimeException when a conflicting email from a different provider exists.
     */
    public function handle(RegisterUserCommand $command): string
    {
        $log = $this->logger
            ->withChannel('app')
            ->withCorrelationId($command->correlationId)
            ->withActorId($command->actorId);

        // --- Idempotency: return existing user if provider record found ---
        $existing = $this->userRepository->findByProvider(
            $command->provider,
            $command->providerSubject,
        );

        if ($existing !== null) {
            $log->debug('RegisterUser: provider record already exists, returning existing id', [
                'user_id'  => $existing->getId(),
                'provider' => $command->provider,
            ]);
            return $existing->getId();
        }

        // --- Email uniqueness check (application-level guard) ---
        if ($this->userRepository->emailExists($command->email)) {
            $log->warning('RegisterUser: email conflict — email already registered', [
                'email'    => $command->email,
                'provider' => $command->provider,
            ]);
            throw new \RuntimeException(
                sprintf('Email "%s" is already registered with a different provider.', $command->email),
            );
        }

        // --- Create and persist new user ---
        $userId = UuidGenerator::generate();
        $user   = User::create(
            id:              $userId,
            email:           $command->email,
            displayName:     $command->displayName,
            provider:        $command->provider,
            providerSubject: $command->providerSubject,
        );

        $this->userRepository->save($user);

        $log->info('RegisterUser: new user created', [
            'user_id'  => $userId,
            'email'    => $command->email,
            'provider' => $command->provider,
        ]);

        // --- Emit users.user_registered ---
        $event = EventEnvelope::create(
            eventName:     'users.user_registered',
            actorId:       $userId,
            correlationId: $command->correlationId,
            payload:       [
                'user_id'          => $userId,
                'email'            => $command->email,
                'display_name'     => $command->displayName,
                'provider'         => $command->provider,
                'provider_subject' => $command->providerSubject,
            ],
            causationId: $command->causationId,
        );

        $this->eventPublisher->publish($event);

        return $userId;
    }
}
