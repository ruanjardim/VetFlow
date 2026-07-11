<?php

namespace App\Core\Validation\Services;

use App\Core\Validation\Validators\BrazilianDocumentValidator;
use App\Modules\Tutors\Data\TutorLookupData;
use App\Modules\Tutors\Services\DuplicateTutorDetector;

class ValidationService
{
    public function __construct(
        private readonly BrazilianDocumentValidator $documentValidator,
        private readonly DuplicateTutorDetector $duplicateTutorDetector
    ) {
    }

    public function validateCpf(?string $cpf): array
    {
        $isValid = $this->documentValidator->cpf($cpf);
        $lookup = $isValid
            ? $this->duplicateTutorDetector->findByCpf($cpf)
            : TutorLookupData::empty();

        return [
            'valid' => $isValid,
            'type' => 'cpf',
            'lookup' => $lookup->toArray(),
        ];
    }

    public function validateCnpj(?string $cnpj): array
    {
        return [
            'valid' => $this->documentValidator->cnpj($cnpj),
            'type' => 'cnpj',
            'lookup' => TutorLookupData::empty()->toArray(),
        ];
    }
}