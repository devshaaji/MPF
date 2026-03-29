<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Commands;

/**
 * Command: import multiple users in bulk.
 *
 * Processing guarantees:
 *  - Input is split into chunks of chunkSize for batched transactions.
 *  - Entries whose provider+providerSubject already exist are skipped (idempotent).
 *  - Entries with a duplicate email belonging to a different provider are recorded
 *    as errors and skipped without aborting the rest of the chunk.
 *  - Each chunk runs in its own database transaction; a failed chunk is rolled
 *    back without affecting already-committed chunks.
 */
final readonly class BulkImportUsersCommand
{
    /**
     * @param array<int, array{
     *   provider: string,
     *   provider_subject: string,
     *   email: string,
     *   display_name: string,
     * }> $users
     */
    public function __construct(
        public array  $users,
        public string $actorId,
        public string $correlationId,
        public int    $chunkSize = 100,
    ) {}
}
