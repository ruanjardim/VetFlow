<?php

namespace App\Core\Validation\Rules;

use App\Core\Validation\BrazilianDocumentValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! BrazilianDocumentValidator::cnpj($value)) {
            $fail('O CNPJ informado é inválido.');
        }
    }
}