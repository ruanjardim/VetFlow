<?php

namespace App\Modules\Financial\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clinic_id' => ['nullable', 'integer'],
            'account_id' => ['required', 'integer'],
            'statement' => ['required', 'file', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('statement');
            $extension = strtolower((string) $file?->getClientOriginalExtension());

            if ($file && ! in_array($extension, ['csv', 'txt', 'ofx'], true)) {
                $validator->errors()->add('statement', 'Envie um extrato CSV, TXT ou OFX de até 5 MB.');
            }
        });
    }
}
