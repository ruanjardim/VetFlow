<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Operations\ReleaseIdentityService;
use Illuminate\Http\JsonResponse;

class ReleaseIdentityController extends Controller
{
    public function __invoke(ReleaseIdentityService $identity): JsonResponse
    {
        $available = $identity->sha() !== null;

        return response()->json(
            $identity->payload(),
            $available ? 200 : 503,
            ['Cache-Control' => 'no-store']
        );
    }
}
