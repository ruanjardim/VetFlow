<?php

namespace App\Support\Validation;

use App\Modules\Tutors\Services\DuplicateTutorDetector;

class ValidationService
{
    public function __construct(
        private readonly DuplicateTutorDetector $duplicateTutorDetector
    ) {
    }

    public function validateCpf(?string $cpf): array
    {
        $normalizedCpf = DocumentNormalizer::digits($cpf);

        $valid = BrazilianDocumentValidator::isValidCpf($normalizedCpf);

        $exists = false;

        if ($valid) {
            $exists = $this->duplicateTutorDetector->existsByCpf($normalizedCpf);
        }

        return [
            'valid' => $valid,
            'exists' => $exists,
            'document' => $normalizedCpf,
            'message' => match (true) {
                ! $valid => 'CPF inválido.',
                $exists => 'CPF válido, mas já existe um tutor cadastrado com este documento.',
                default => 'CPF válido.',
            },
        ];
    }

    public function validateCnpj(?string $cnpj): array
    {
        $normalizedCnpj = DocumentNormalizer::digits($cnpj);

        $valid = BrazilianDocumentValidator::isValidCnpj($normalizedCnpj);

        return [
            'valid' => $valid,
            'exists' => false,
            'document' => $normalizedCnpj,
            'message' => $valid
                ? 'CNPJ válido.'
                : 'CNPJ inválido.',
        ];
    }
}
