<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$gitDirectory = resolveGitDirectory($root);
$sha = $gitDirectory === null ? null : resolveGitSha($gitDirectory);

if ($sha === null) {
    fwrite(STDOUT, "Release identity marker skipped: no Git commit was available.\n");
    exit(0);
}

$cacheDirectory = $root.'/bootstrap/cache';
$markerPath = $cacheDirectory.'/vetflow-release.sha';

if (! is_dir($cacheDirectory) && ! mkdir($cacheDirectory, 0775, true) && ! is_dir($cacheDirectory)) {
    fwrite(STDERR, "Unable to create the release marker directory: {$cacheDirectory}\n");
    exit(1);
}

$temporaryPath = tempnam($cacheDirectory, 'vetflow-release-');

if ($temporaryPath === false) {
    fwrite(STDERR, "Unable to create a temporary release marker.\n");
    exit(1);
}

if (file_put_contents($temporaryPath, $sha.PHP_EOL, LOCK_EX) === false) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to write the temporary release marker.\n");
    exit(1);
}

if (is_file($markerPath) && ! unlink($markerPath)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to replace the existing release marker: {$markerPath}\n");
    exit(1);
}

if (! rename($temporaryPath, $markerPath)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to publish the release marker: {$markerPath}\n");
    exit(1);
}

@chmod($markerPath, 0664);
fwrite(STDOUT, "Release identity marker written for {$sha}.\n");

function resolveGitDirectory(string $root): ?string
{
    $dotGit = $root.'/.git';

    if (is_dir($dotGit)) {
        return realpath($dotGit) ?: $dotGit;
    }

    if (! is_file($dotGit)) {
        return null;
    }

    $contents = trim((string) file_get_contents($dotGit));

    if (preg_match('/^gitdir:\s*(.+)$/i', $contents, $matches) !== 1) {
        return null;
    }

    $path = trim($matches[1]);

    if (! preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path)) {
        $path = $root.'/'.$path;
    }

    return is_dir($path) ? (realpath($path) ?: $path) : null;
}

function resolveGitSha(string $gitDirectory): ?string
{
    $headPath = $gitDirectory.'/HEAD';

    if (! is_file($headPath)) {
        return null;
    }

    $head = strtolower(trim((string) file_get_contents($headPath)));

    if (preg_match('/^[a-f0-9]{40}$/', $head) === 1) {
        return $head;
    }

    if (preg_match('/^ref:\s*(.+)$/', $head, $matches) !== 1) {
        return null;
    }

    $reference = trim($matches[1]);
    $commonDirectory = resolveCommonGitDirectory($gitDirectory);
    $referencePath = $commonDirectory.'/'.$reference;

    if (is_file($referencePath)) {
        $sha = strtolower(trim((string) file_get_contents($referencePath)));

        return preg_match('/^[a-f0-9]{40}$/', $sha) === 1 ? $sha : null;
    }

    $packedReferencesPath = $commonDirectory.'/packed-refs';

    if (! is_file($packedReferencesPath)) {
        return null;
    }

    foreach (file($packedReferencesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
            continue;
        }

        [$sha, $packedReference] = array_pad(preg_split('/\s+/', trim($line), 2) ?: [], 2, null);

        if ($packedReference === $reference && is_string($sha) && preg_match('/^[a-f0-9]{40}$/i', $sha) === 1) {
            return strtolower($sha);
        }
    }

    return null;
}

function resolveCommonGitDirectory(string $gitDirectory): string
{
    $commonDirectoryPath = $gitDirectory.'/commondir';

    if (! is_file($commonDirectoryPath)) {
        return $gitDirectory;
    }

    $commonDirectory = trim((string) file_get_contents($commonDirectoryPath));

    if ($commonDirectory === '') {
        return $gitDirectory;
    }

    if (! preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $commonDirectory)) {
        $commonDirectory = $gitDirectory.'/'.$commonDirectory;
    }

    return is_dir($commonDirectory)
        ? (realpath($commonDirectory) ?: $commonDirectory)
        : $gitDirectory;
}
