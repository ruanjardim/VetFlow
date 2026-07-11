<?php

namespace App\Core\Traits;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Sucesso.',
        int $status = 200
    ) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    protected function error(
        string $message = 'Erro.',
        int $status = 400,
        mixed $errors = null
    ) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}