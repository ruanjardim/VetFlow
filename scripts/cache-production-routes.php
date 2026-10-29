<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$environment = getenv('APP_ENV') ?: null;
if ($environment === null) {
    Dotenv::createImmutable($root)->safeLoad();
    $environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
}

if ($environment !== 'production') {
    fwrite(STDOUT, "Production route cache skipped outside production.\n");

    exit(0);
}

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$status = $kernel->call('route:cache');
fwrite($status === 0 ? STDOUT : STDERR, $kernel->output());

exit($status);
