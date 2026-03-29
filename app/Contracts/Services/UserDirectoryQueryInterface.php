<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Contracts\Services\Dto\UserDto;
use App\Contracts\Services\Dto\LeaderboardDto;

/**
 * Read-only query interface for user identity data.
 * Cross-module consumers must depend on this interface only — never on internal Users repositories.
 */
interface UserDirectoryQueryInterface
{
    /**
     * Find a user by their platform UUID.
     *
     * @param string $userId Platform user UUID
     * @return UserDto|null null when no active user exists for the given id
     */
    public function findById(string $userId): ?UserDto;

    /**
     * Return the top-N users ranked by total points.
     *
     * @param int $limit Maximum number of entries to return (default 50)
     * @return LeaderboardDto
     */
    public function getLeaderboard(int $limit = 50): LeaderboardDto;
}
