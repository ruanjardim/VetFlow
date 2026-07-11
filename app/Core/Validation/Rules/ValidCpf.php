<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\BrazilianDocumentValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! BrazilianDocumentValidator::cpf($value)) {
            $fail('O CPF informado é inválido.');
        }
    }
}