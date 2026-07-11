<?php

namespace App\Core\Validation\Http\Controllers;

use App\Core\Validation\Services\ValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ValidationController extends Controller
{
    public function __construct(
        private readonly ValidationService $validationService
    ) {
    }

    public function cpf(Request $request): JsonResponse
    {
        return response()->json(
            $this->validationService->validateCpf($request->input('value'))
        );
    }

    public function cnpj(Request $request): JsonResponse
    {
        return response()->json(
            $this->validationService->validateCnpj($request->input('value'))
        );
    }
}