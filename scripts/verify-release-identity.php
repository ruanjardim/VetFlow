<?php

declare(strict_types=1);

$expected = strtolower(trim((string) ($argv[1] ?? '')));
$markerPath = dirname(__DIR__).'/bootstrap/cache/vetflow-release.sha';
$actual = is_file($markerPath)
    ? strtolower(trim((string) file_get_contents($markerPath)))
    : '';

if (preg_match('/^[a-f0-9]{40}$/', $actual) !== 1) {
    fwrite(STDERR, "Release identity marker is missing or invalid.\n");
    exit(1);
}

if ($expected !== '' && $actual !== $expected) {
    fwrite(STDERR, "Release identity mismatch: expected {$expected}, found {$actual}.\n");
    exit(1);
}

fwrite(STDOUT, "Release identity verified: {$actual}.\n");
