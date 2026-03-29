<?php

declare(strict_types=1);

namespace App\Contracts\Services\Dto;

/**
 * Leaderboard data DTO shared across module boundaries.
 */
final readonly class LeaderboardDto
{
    public function __construct(
        /** @var LeaderboardEntryDto[] */
        public array $entries,
        public string $generatedAt,
    ) {}
}
