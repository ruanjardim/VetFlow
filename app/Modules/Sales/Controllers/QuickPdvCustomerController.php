<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Requests\StoreQuickPdvCustomerRequest;
use App\Modules\Sales\Services\QuickPdvCustomerService;
use Illuminate\Http\JsonResponse;

class QuickPdvCustomerController
{
    public function store(
        StoreQuickPdvCustomerRequest $request,
        QuickPdvCustomerService $service
    ): JsonResponse {
        $result = $service->create($request->validated());

        return response()->json([
            'message' => 'Responsável e pet cadastrados com sucesso.',
            'tutor' => [
                'id' => $result['tutor']->id,
                'clinic_id' => $result['tutor']->clinic_id,
                'name' => $result['tutor']->name,
            ],
            'patient' => [
                'id' => $result['patient']->id,
                'clinic_id' => $result['patient']->clinic_id,
                'tutor_id' => $result['patient']->tutor_id,
                'name' => $result['patient']->name,
            ],
        ], 201);
    }
}
