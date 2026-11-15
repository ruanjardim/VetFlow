<?php

namespace App\Http\Middleware;

use App\Modules\Saas\Services\SubscriptionFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHasFeature
{
    public function __construct(private readonly SubscriptionFeatureService $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        abort_unless($user && $this->features->enabled($user->clinic_id, $feature), 403);

        return $next($request);
    }
}
