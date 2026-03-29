<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Commands;

/**
 * Command: award (or deduct) points for a user from a named source.
 *
 * points_delta may be negative to deduct points, but the resulting
 * running_total must remain ≥ 0 (enforced by the handler).
 * Emits users.points_awarded upon successful ledger entry.
 */
final readonly class AwardPointsCommand
{
    public function __construct(
        public string  $userId,
        public int     $pointsDelta,
        public string  $source,
        public ?string $referenceId,
        public string  $actorId,
        public string  $correlationId,
        public string  $causationId = '',
    ) {}
}
