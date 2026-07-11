<?php

namespace App\Modules\Validation\Services;

use App\Core\Support\DocumentNormalizer;
use App\Core\Validation\BrazilianDocumentValidator;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Tutors\Models\Tutor;

class ValidationService
{
    public function validateCpf(string $cpf): array
    {
        $cpf = DocumentNormalizer::onlyNumbers($cpf);

        if (! BrazilianDocumentValidator::cpf($cpf)) {
            return [
                'valid' => false,
                'exists' => false,
                'message' => 'CPF inválido.',
            ];
        }

        $tutor = Tutor::where('cpf', $cpf)->first();

        if ($tutor) {
            return [
                'valid' => true,
                'exists' => true,
                'message' => 'Tutor já cadastrado.',
                'data' => [
                    'id' => $tutor->id,
                    'name' => $tutor->name,
                    'phone' => $tutor->phone,
                    'email' => $tutor->email,
                ],
            ];
        }

        return [
            'valid' => true,
            'exists' => false,
            'message' => 'CPF válido.',
        ];
    }

    public function validateCnpj(string $cnpj): array
    {
        $cnpj = DocumentNormalizer::onlyNumbers($cnpj);

        if (! BrazilianDocumentValidator::cnpj($cnpj)) {
            return [
                'valid' => false,
                'exists' => false,
                'message' => 'CNPJ inválido.',
            ];
        }

        $clinic = Clinic::where('cnpj', $cnpj)->first();

        if ($clinic) {
            return [
                'valid' => true,
                'exists' => true,
                'message' => 'Clínica já cadastrada.',
                'data' => [
                    'id' => $clinic->id,
                    'corporate_name' => $clinic->corporate_name,
                    'trade_name' => $clinic->trade_name,
                ],
            ];
        }

        return [
            'valid' => true,
            'exists' => false,
            'message' => 'CNPJ válido.',
        ];
    }
}