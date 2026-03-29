#!/usr/bin/env php
<?php
declare(strict_types=1);

// Contract validation harness for Village Community Digital Platform
// Run: php bin/validate-contracts.php

$root = dirname(__DIR__);
$failures = [];
$passed = 0;
$failed = 0;

/** Emit a coloured status line and update counters. */
function report(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $status = $ok ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
    $suffix = ($detail !== '') ? " — {$detail}" : '';
    echo "  [{$status}] {$label}{$suffix}\n";
    $ok ? $passed++ : $failed++;
}

function sectionHeader(string $title): void
{
    echo "\n\033[1m{$title}\033[0m\n";
    echo str_repeat('-', 60) . "\n";
}

// ============================================================
// 1. API Contract Validation
// ============================================================
sectionHeader('1. API Contract Validation');

$apiPattern = $root . '/app/Contracts/Api/**/*.json';
$apiFiles   = glob($apiPattern, GLOB_BRACE);

// glob with ** is not recursive in PHP; use a manual recursive scan
function collectFiles(string $dir, string $extension): array
{
    $result = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === $extension) {
            $result[] = $file->getPathname();
        }
    }
    sort($result);
    return $result;
}

$apiDir   = $root . '/app/Contracts/Api';
$apiFiles = collectFiles($apiDir, 'json');

if (empty($apiFiles)) {
    echo "  \033[33mWARN\033[0m  No API contract files found in {$apiDir}\n";
} else {
    foreach ($apiFiles as $file) {
        $name    = str_replace($root . '/', '', $file);
        $content = file_get_contents($file);
        $decoded = json_decode($content, true);

        // Validate parseable JSON
        $isJson = $decoded !== null && json_last_error() === JSON_ERROR_NONE;
        report("{$name} — valid JSON", $isJson, $isJson ? '' : json_last_error_msg());
        if (!$isJson) {
            continue;
        }

        // Must have a title key
        report("{$name} — has 'title' key", isset($decoded['title']));

        // Must have at least one structural key: request, response, or $schema
        $hasStructure = isset($decoded['request']) || isset($decoded['response']) || isset($decoded['$schema']);
        report("{$name} — has 'request', 'response', or '\$schema'", $hasStructure);
    }
}

// ============================================================
// 2. Event Schema Validation
// ============================================================
sectionHeader('2. Event Schema Validation');

$requiredEnvelopeFields = [
    'event_id', 'event_name', 'occurred_at',
    'actor_id', 'correlation_id', 'causation_id', 'payload',
];

$eventDir   = $root . '/app/Contracts/Events';
$eventFiles = array_filter(
    glob($eventDir . '/*.json'),
    static fn(string $f): bool => !str_ends_with($f, 'EventEnvelope.json')
);
sort($eventFiles);

if (empty($eventFiles)) {
    echo "  \033[33mWARN\033[0m  No event schema files found in {$eventDir}\n";
} else {
    foreach ($eventFiles as $file) {
        $name    = str_replace($root . '/', '', $file);
        $content = file_get_contents($file);
        $decoded = json_decode($content, true);

        // Validate parseable JSON
        $isJson = $decoded !== null && json_last_error() === JSON_ERROR_NONE;
        report("{$name} — valid JSON", $isJson, $isJson ? '' : json_last_error_msg());
        if (!$isJson) {
            continue;
        }

        // Must declare all required envelope fields in top-level `required` array
        $declaredRequired = $decoded['required'] ?? [];
        $missingFields    = array_diff($requiredEnvelopeFields, $declaredRequired);
        report(
            "{$name} — required envelope fields declared",
            empty($missingFields),
            empty($missingFields) ? '' : 'missing: ' . implode(', ', $missingFields)
        );

        // All envelope fields must appear in `properties`
        $declaredProperties = array_keys($decoded['properties'] ?? []);
        $missingProps       = array_diff($requiredEnvelopeFields, $declaredProperties);
        report(
            "{$name} — envelope fields in properties",
            empty($missingProps),
            empty($missingProps) ? '' : 'missing: ' . implode(', ', $missingProps)
        );

        // payload must be an object with properties
        $payloadType = $decoded['properties']['payload']['type'] ?? null;
        $hasPayloadProps = isset($decoded['properties']['payload']['properties']);
        report(
            "{$name} — payload is object with properties",
            $payloadType === 'object' && $hasPayloadProps
        );

        // event_name.const must follow module.event_action naming convention
        $eventNameConst = $decoded['properties']['event_name']['const'] ?? null;
        $validPattern   = $eventNameConst !== null
            && (bool) preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', (string) $eventNameConst);
        report(
            "{$name} — event_name follows module.event_action pattern",
            $validPattern,
            $validPattern ? $eventNameConst : "got: {$eventNameConst}"
        );
    }
}

// ============================================================
// 3. Migration Validation
// ============================================================
sectionHeader('3. Migration SQL Validation');

$migrationDir   = $root . '/database/migrations';
$migrationFiles = glob($migrationDir . '/*.sql');
sort($migrationFiles);

if (empty($migrationFiles)) {
    echo "  \033[33mWARN\033[0m  No migration files found in {$migrationDir}\n";
} else {
    foreach ($migrationFiles as $file) {
        $name    = str_replace($root . '/', '', $file);
        $content = file_get_contents($file);
        $upper   = strtoupper($content);

        // Must contain CREATE TABLE
        $hasCreateTable = str_contains($upper, 'CREATE TABLE');
        report("{$name} — has CREATE TABLE", $hasCreateTable);

        // Must contain PRIMARY KEY
        $hasPrimaryKey = str_contains($upper, 'PRIMARY KEY');
        report("{$name} — has PRIMARY KEY", $hasPrimaryKey);

        // Must contain created_at timestamp column
        $hasCreatedAt = str_contains($content, 'created_at');
        report("{$name} — has created_at column", $hasCreatedAt);

        // Filename must follow <module>_<NNN>.sql convention
        $basename       = basename($file);
        $validFilename  = (bool) preg_match('/^[a-z][a-z0-9_]*_\d{3}\.sql$/', $basename);
        report("{$name} — filename matches <module>_NNN.sql", $validFilename);
    }
}

// ============================================================
// Report Summary
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
$total = $passed + $failed;
echo "\033[1mValidation Summary\033[0m\n";
echo "  Total checks : {$total}\n";
echo "  \033[32mPassed\033[0m       : {$passed}\n";

if ($failed > 0) {
    echo "  \033[31mFailed\033[0m       : {$failed}\n";
    echo "\n\033[31mContract validation FAILED — {$failed} check(s) did not pass.\033[0m\n";
    exit(1);
}

echo "\n\033[32mAll contract checks passed.\033[0m\n";
exit(0);
