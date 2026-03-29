<?php
declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates the structure of every SQL migration file.
 *
 * Each migration must:
 *  - contain at least one CREATE TABLE statement
 *  - define a PRIMARY KEY
 *  - include a created_at timestamp column
 *  - follow the <module>_NNN.sql filename convention
 */
final class MigrationContractTest extends TestCase
{
    // -------------------------------------------------------
    // Data provider
    // -------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function migrationFileProvider(): array
    {
        $root = defined('PROJECT_ROOT')
            ? rtrim((string) PROJECT_ROOT, '/')
            : dirname(__DIR__, 2);

        $dir   = $root . '/database/migrations';
        $files = (array) glob($dir . '/*.sql');
        sort($files);

        $dataset = [];
        foreach ($files as $file) {
            $key           = str_replace($root . '/', '', $file);
            $dataset[$key] = [$file];
        }

        return $dataset;
    }

    // -------------------------------------------------------
    // Tests
    // -------------------------------------------------------

    #[DataProvider('migrationFileProvider')]
    public function testMigrationHasCreateTable(string $file): void
    {
        $upper = strtoupper((string) file_get_contents($file));
        $this->assertStringContainsString(
            'CREATE TABLE',
            $upper,
            "Migration must contain a CREATE TABLE statement: {$file}"
        );
    }

    #[DataProvider('migrationFileProvider')]
    public function testMigrationHasPrimaryKey(string $file): void
    {
        $upper = strtoupper((string) file_get_contents($file));
        $this->assertStringContainsString(
            'PRIMARY KEY',
            $upper,
            "Migration must define a PRIMARY KEY: {$file}"
        );
    }

    #[DataProvider('migrationFileProvider')]
    public function testMigrationHasCreatedAtColumn(string $file): void
    {
        $content = (string) file_get_contents($file);
        $this->assertStringContainsString(
            'created_at',
            $content,
            "Migration must include a 'created_at' timestamp column: {$file}"
        );
    }

    #[DataProvider('migrationFileProvider')]
    public function testMigrationFilenameFollowsConvention(string $file): void
    {
        $basename = basename($file);
        $this->assertMatchesRegularExpression(
            '/^[a-z][a-z0-9_]*_\d{3}\.sql$/',
            $basename,
            "Migration filename must follow <module>_NNN.sql convention, got: {$basename}"
        );
    }
}
