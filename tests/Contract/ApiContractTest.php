<?php
declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates the structure of every JSON API contract file.
 *
 * Each contract must:
 *  - be valid JSON
 *  - contain a 'title' key
 *  - contain at least one of: 'request', 'response', or '$schema'
 */
final class ApiContractTest extends TestCase
{
    private static string $apiDir;

    public static function setUpBeforeClass(): void
    {
        $root = defined('PROJECT_ROOT')
            ? rtrim((string) PROJECT_ROOT, '/')
            : dirname(__DIR__, 2);

        self::$apiDir = $root . '/app/Contracts/Api';
    }

    // -------------------------------------------------------
    // Data provider
    // -------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function apiContractFileProvider(): array
    {
        $root = defined('PROJECT_ROOT')
            ? rtrim((string) PROJECT_ROOT, '/')
            : dirname(__DIR__, 2);

        $dir      = $root . '/app/Contracts/Api';
        $files    = self::collectJsonFiles($dir);
        $dataset  = [];

        foreach ($files as $file) {
            $key           = str_replace($root . '/', '', $file);
            $dataset[$key] = [$file];
        }

        return $dataset;
    }

    // -------------------------------------------------------
    // Tests
    // -------------------------------------------------------

    #[DataProvider('apiContractFileProvider')]
    public function testApiContractIsValidJson(string $file): void
    {
        $content = file_get_contents($file);
        $this->assertNotFalse($content, "Cannot read file: {$file}");

        $decoded = json_decode($content, true);
        $this->assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
            "Invalid JSON in {$file}: " . json_last_error_msg()
        );
        $this->assertIsArray($decoded, "Decoded JSON must be an array/object in {$file}");
    }

    #[DataProvider('apiContractFileProvider')]
    public function testApiContractHasValidStructure(string $file): void
    {
        $decoded = json_decode((string) file_get_contents($file), true);

        $this->assertArrayHasKey(
            'title',
            $decoded,
            "API contract must have a 'title' key: {$file}"
        );
    }

    #[DataProvider('apiContractFileProvider')]
    public function testApiContractHasSchemaField(string $file): void
    {
        $decoded = json_decode((string) file_get_contents($file), true);

        $hasStructure = isset($decoded['request'])
            || isset($decoded['response'])
            || isset($decoded['$schema']);

        $this->assertTrue(
            $hasStructure,
            "API contract must have 'request', 'response', or '\$schema' key: {$file}"
        );
    }

    // -------------------------------------------------------
    // Helper
    // -------------------------------------------------------

    /** @return list<string> */
    private static function collectJsonFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $result   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'json') {
                $result[] = $file->getPathname();
            }
        }

        sort($result);
        return $result;
    }
}
