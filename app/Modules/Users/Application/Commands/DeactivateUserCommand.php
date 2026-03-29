<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Commands;

/**
 * Command: deactivate a user account.
 *
 * Must be issued by an admin or the system actor.
 * Idempotent: deactivating an already-deactivated user is a no-op.
 * Emits users.user_deactivated upon state change.
 */
final readonly class DeactivateUserCommand
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $deactivatedBy,
        public string $actorId,
        public string $correlationId,
        public string $causationId = '',
    ) {}
}
