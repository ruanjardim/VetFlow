<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsGlobal
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->clinic_id !== null) {
            abort(403, 'Acesso restrito a administradores globais.');
        }

        return $next($request);
    }
}
