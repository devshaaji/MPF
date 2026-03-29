<?php

declare(strict_types=1);

namespace App\Contracts\Services\Dto;

/**
 * Single leaderboard entry DTO.
 */
final readonly class LeaderboardEntryDto
{
    public function __construct(
        public int $rank,
        public string $userId,
        public string $displayName,
        public int $totalPoints,
    ) {}
}
