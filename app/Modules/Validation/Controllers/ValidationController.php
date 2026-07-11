<?php

namespace App\Modules\Validation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Validation\Services\ValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function __construct(
        private readonly ValidationService $validationService
    ) {
    }

    public function cpf(Request $request): JsonResponse
    {
        return response()->json(
            $this->validationService->validateCpf(
                $request->get('cpf', '')
            )
        );
    }

    public function cnpj(Request $request): JsonResponse
    {
        return response()->json(
            $this->validationService->validateCnpj(
                $request->get('cnpj', '')
            )
        );
    }
}