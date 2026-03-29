<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Handlers;

use App\Contracts\Services\CacheStoreInterface;
use App\Contracts\Services\LoggerInterface;
use App\Contracts\Services\Dto\UserDto;
use App\Modules\Users\Application\Commands\UpdateUserCommand;
use App\Modules\Users\Domain\UserRepositoryInterface;

/**
 * UpdateUserHandler — applies mutable profile changes for a user.
 *
 * Authorization rule: the actor must be the user themselves or have the 'admin' role.
 * After persistence, the user profile cache is invalidated to prevent stale reads.
 *
 * Returns the updated UserDto reflecting the new state.
 */
final class UpdateUserHandler
{
    private const CACHE_TTL_PROFILE = 300;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CacheStoreInterface     $cache,
        private readonly LoggerInterface         $logger,
    ) {}

    /**
     * @throws \InvalidArgumentException when no fields are provided.
     * @throws \RuntimeException when the user is not found or the actor is not authorized.
     */
    public function handle(UpdateUserCommand $command): UserDto
    {
        $log = $this->logger
            ->withChannel('app')
            ->withCorrelationId($command->correlationId)
            ->withActorId($command->actorId);

        // --- Require at least one mutable field ---
        if ($command->displayName === null
            && $command->bio       === null
            && $command->avatarPath === null
            && $command->location   === null
        ) {
            throw new \InvalidArgumentException('UpdateUser: at least one field must be provided.');
        }

        // --- Load target user ---
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            $log->warning('UpdateUser: user not found', ['user_id' => $command->userId]);
            throw new \RuntimeException(sprintf('User "%s" not found.', $command->userId));
        }

        // --- Authorization: actor must own the profile or be an admin ---
        if ($command->actorId !== $command->userId) {
            $actor = $this->userRepository->findById($command->actorId);
            if ($actor === null || !in_array('admin', $actor->getRoles(), true)) {
                $log->warning('UpdateUser: forbidden — actor is not the user or an admin', [
                    'user_id'  => $command->userId,
                    'actor_id' => $command->actorId,
                ]);
                throw new \RuntimeException('Forbidden: cannot update another user\'s profile.');
            }
        }

        // --- Apply changes ---
        $user->updateProfile(
            displayName: $command->displayName,
            bio:         $command->bio,
            avatarPath:  $command->avatarPath,
            location:    $command->location,
        );

        $this->userRepository->save($user);

        // --- Invalidate profile cache ---
        $this->cache->forget('users.profile.' . $command->userId);

        $log->info('UpdateUser: profile updated', [
            'user_id' => $command->userId,
            'fields'  => array_keys(array_filter([
                'display_name' => $command->displayName,
                'bio'          => $command->bio,
                'avatar_path'  => $command->avatarPath,
                'location'     => $command->location,
            ], fn ($v) => $v !== null)),
        ]);

        return new UserDto(
            id:          $user->getId(),
            email:       $user->getEmail(),
            displayName: $user->getDisplayName(),
            status:      $user->getStatus(),
            bio:         $user->getBio(),
            avatarPath:  $user->getAvatarPath(),
            location:    $user->getLocation(),
            roles:       $user->getRoles(),
        );
    }
}
