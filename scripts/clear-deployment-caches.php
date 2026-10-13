<?php

declare(strict_types=1);

/*
 * Hostinger updates the working tree before running Composer. Remove only
 * application-generated caches that can still refer to the previous release.
 * Composer's own package/service manifests are intentionally preserved.
 */
$root = dirname(__DIR__);
$cacheFiles = [
    $root.'/bootstrap/cache/compiled.php',
    $root.'/bootstrap/cache/config.php',
    $root.'/bootstrap/cache/events.php',
    $root.'/bootstrap/cache/routes.php',
    $root.'/bootstrap/cache/routes-v7.php',
];

foreach ($cacheFiles as $cacheFile) {
    if (is_file($cacheFile) && ! unlink($cacheFile)) {
        fwrite(STDERR, "Unable to remove deployment cache: {$cacheFile}\n");
        exit(1);
    }
}

foreach (glob($root.'/storage/framework/views/*.php') ?: [] as $compiledView) {
    if (is_file($compiledView) && ! unlink($compiledView)) {
        fwrite(STDERR, "Unable to remove compiled view: {$compiledView}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Deployment caches cleared.\n");
