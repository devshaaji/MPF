<?php

declare(strict_types=1);

namespace App\Contracts\Services\Dto;

/**
 * Resolved identity returned by AuthIdentityResolverInterface.
 */
final readonly class AuthIdentityDto
{
    public function __construct(
        public string $provider,
        public string $providerSubject,
        public string $email,
        public ?string $displayName,
    ) {}
}
