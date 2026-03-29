<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Commands;

/**
 * Command: update mutable profile fields for a user.
 *
 * Only fields with a non-null value will be updated.
 * At least one field must be provided (enforced by the handler).
 */
final readonly class UpdateUserCommand
{
    public function __construct(
        public string  $userId,
        public ?string $displayName,
        public ?string $bio,
        public ?string $avatarPath,
        public ?string $location,
        public string  $actorId,
        public string  $correlationId,
    ) {}
}
