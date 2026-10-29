<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PdvSessionController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $request->session()->put('pdv_last_heartbeat_at', now()->timestamp);

        return response()
            ->json([
                'status' => 'ok',
                'session_lifetime_minutes' => (int) config('session.lifetime'),
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }
}
