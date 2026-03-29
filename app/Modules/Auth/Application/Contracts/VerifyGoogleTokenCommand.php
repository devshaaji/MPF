<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contracts;

/**
 * Command DTO carrying the data required to verify a Google ID token.
 * Thin data-carrier only — no business logic.
 */
final readonly class VerifyGoogleTokenCommand
{
    public function __construct(
        public string $token,
        public string $correlationId,
        public string $ipAddress,
    ) {}
}
