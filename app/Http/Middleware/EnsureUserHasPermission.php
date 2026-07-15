<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $permissions = collect($permissions)
            ->flatMap(fn (string $permission): array => explode('|', $permission))
            ->map(fn (string $permission): string => trim($permission))
            ->filter()
            ->values()
            ->all();

        if ($permissions === [] || $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        abort(403, 'Acesso nao autorizado para este modulo.');
    }
}
