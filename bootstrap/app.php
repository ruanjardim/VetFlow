<?php

use App\Http\Middleware\SecurityHeaders;
use App\Providers\RepositoryServiceProvider;
use App\Support\Operations\ReleaseRouteCachePath;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// A release-specific filename prevents a persistent PHP process from loading
// the previous deployment's compiled route table from OPcache.
ReleaseRouteCachePath::configure(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        RepositoryServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->validateCsrfTokens(except: [
            'webhooks/focus-nfe/nfsen',
        ]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'focus_token',
            'focus_token_confirmation',
        ]);
    })->create();
