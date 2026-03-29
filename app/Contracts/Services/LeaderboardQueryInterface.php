<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Contracts\Services\Dto\LeaderboardEntryDto;

/**
 * LeaderboardQueryInterface — read-model contract for leaderboard aggregation.
 *
 * INTEGRATION-002: This interface is the authoritative cross-module contract
 * for querying the leaderboard read model. The API contract is owned by the
 * Users module at app/Contracts/Api/Users/get_leaderboard.json (MCL-01).
 *
 * Tie-break policy: for equal total_points, the user with the earlier
 * first_points_earned_at timestamp wins (lower rank number = better rank).
 */
interface LeaderboardQueryInterface
{
    /**
     * Get leaderboard entries ordered by total_points DESC.
     * Tie-break: earlier first_points_earned_at wins for equal total_points.
     *
     * @param int $limit  Maximum entries to return (default 50, max 200).
     * @param int $offset Pagination offset.
     * @return LeaderboardEntryDto[]
     */
    public function getLeaderboard(int $limit = 50, int $offset = 0): array;

    /**
     * Get the rank for a specific user.
     * Returns null if the user has no points recorded.
     *
     * @param string $userId UUID of the user.
     */
    public function getUserRank(string $userId): ?int;

    /**
     * Rebuild the leaderboard read model from the points ledger.
     * Called by the scheduler or in response to users.points_awarded events.
     * Emits leaderboard.rebuilt upon completion.
     */
    public function rebuild(): void;
}
