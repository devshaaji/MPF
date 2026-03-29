<?php

declare(strict_types=1);

namespace App\Contracts\Services\Dto;

/**
 * Read-only user profile DTO shared across module boundaries.
 */
final readonly class UserDto
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $status,
        public ?string $bio,
        public ?string $avatarPath,
        public ?string $location,
        /** @var string[] */
        public array $roles,
    ) {}
}
