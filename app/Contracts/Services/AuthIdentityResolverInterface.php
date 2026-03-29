<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Contracts\Services\Dto\AuthIdentityDto;

/**
 * Resolves an external provider token to a platform AuthIdentityDto.
 * Implementations must not persist state or cross module boundaries.
 */
interface AuthIdentityResolverInterface
{
    /**
     * Resolve an external identity provider token to a platform identity.
     *
     * @param string $token Raw provider token (e.g. Google ID token)
     * @return AuthIdentityDto Resolved identity — provider, subject, and email at minimum
     */
    public function resolve(string $token): AuthIdentityDto;
}
