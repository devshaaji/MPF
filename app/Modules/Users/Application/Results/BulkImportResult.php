<?php

declare(strict_types=1);

namespace App\Modules\Users\Application\Results;

/**
 * Result DTO returned by BulkImportUsersHandler.
 */
final readonly class BulkImportResult
{
    /**
     * @param array<int, array{index: int, email: string, reason: string}> $errorDetails
     */
    public function __construct(
        public int   $imported,
        public int   $skipped,
        public int   $errors,
        public array $errorDetails = [],
    ) {}
}
