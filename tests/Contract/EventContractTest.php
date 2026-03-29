<?php
declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validates the structure of every JSON event schema file.
 *
 * Each event schema must:
 *  - be valid JSON
 *  - declare all required envelope fields in its top-level 'required' array
 *  - define those fields in 'properties'
 *  - define 'payload' as an object with a 'properties' sub-key
 *  - use the module.event_action naming convention for event_name.const
 */
final class EventContractTest extends TestCase
{
    private const REQUIRED_ENVELOPE_FIELDS = [
        'event_id',
        'event_name',
        'occurred_at',
        'actor_id',
        'correlation_id',
        'causation_id',
        'payload',
    ];

    // -------------------------------------------------------
    // Data provider
    // -------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function eventSchemaFileProvider(): array
    {
        $root = defined('PROJECT_ROOT')
            ? rtrim((string) PROJECT_ROOT, '/')
            : dirname(__DIR__, 2);

        $dir     = $root . '/app/Contracts/Events';
        $files   = array_filter(
            (array) glob($dir . '/*.json'),
            static fn(string $f): bool => !str_ends_with($f, 'EventEnvelope.json')
        );
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

    #[DataProvider('eventSchemaFileProvider')]
    public function testEventSchemaIsValidJson(string $file): void
    {
        $content = file_get_contents($file);
        $this->assertNotFalse($content, "Cannot read file: {$file}");

        json_decode($content, true);
        $this->assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
            "Invalid JSON in {$file}: " . json_last_error_msg()
        );
    }

    #[DataProvider('eventSchemaFileProvider')]
    public function testEventSchemaDeclaresAllRequiredEnvelopeFields(string $file): void
    {
        $decoded          = json_decode((string) file_get_contents($file), true);
        $declaredRequired = $decoded['required'] ?? [];
        $missing          = array_diff(self::REQUIRED_ENVELOPE_FIELDS, $declaredRequired);

        $this->assertEmpty(
            $missing,
            sprintf(
                "Event schema '%s' is missing envelope fields in 'required': %s",
                basename($file),
                implode(', ', $missing)
            )
        );
    }

    #[DataProvider('eventSchemaFileProvider')]
    public function testEventSchemaDefinesEnvelopeFieldsInProperties(string $file): void
    {
        $decoded    = json_decode((string) file_get_contents($file), true);
        $properties = array_keys($decoded['properties'] ?? []);
        $missing    = array_diff(self::REQUIRED_ENVELOPE_FIELDS, $properties);

        $this->assertEmpty(
            $missing,
            sprintf(
                "Event schema '%s' is missing envelope fields in 'properties': %s",
                basename($file),
                implode(', ', $missing)
            )
        );
    }

    #[DataProvider('eventSchemaFileProvider')]
    public function testPayloadIsObjectWithProperties(string $file): void
    {
        $decoded     = json_decode((string) file_get_contents($file), true);
        $payload     = $decoded['properties']['payload'] ?? [];
        $payloadType = $payload['type'] ?? null;

        $this->assertSame(
            'object',
            $payloadType,
            "payload.type must be 'object' in {$file}"
        );
        $this->assertArrayHasKey(
            'properties',
            $payload,
            "payload must define a 'properties' sub-schema in {$file}"
        );
    }

    #[DataProvider('eventSchemaFileProvider')]
    public function testEventNameFollowsModuleDotEventActionPattern(string $file): void
    {
        $decoded        = json_decode((string) file_get_contents($file), true);
        $eventNameConst = $decoded['properties']['event_name']['const'] ?? null;

        $this->assertNotNull(
            $eventNameConst,
            "event_name must define a 'const' value in {$file}"
        );
        $this->assertMatchesRegularExpression(
            '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/',
            (string) $eventNameConst,
            "event_name.const must follow module.event_action pattern in {$file}, got: {$eventNameConst}"
        );
    }
}
