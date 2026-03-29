<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Commands;

/**
 * Command: register a new user via an external identity provider.
 *
 * Triggered by the Auth module after successful token verification.
 * Idempotent: if provider+providerSubject already exists, the handler
 * returns the existing user's id without creating a duplicate.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $provider,
        public string $providerSubject,
        public string $email,
        public string $displayName,
        public string $correlationId,
        public string $actorId,
        public string $causationId = '',
    ) {}
}
