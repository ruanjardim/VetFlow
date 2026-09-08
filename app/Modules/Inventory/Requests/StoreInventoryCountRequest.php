<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'clinic_id' => [
                $clinicId ? 'nullable' : 'required',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true),
                ...($clinicId ? [Rule::in([(int) $clinicId])] : []),
            ],
            'title' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Informe a clínica da contagem.',
            'clinic_id.exists' => 'A clínica informada não está ativa ou não foi encontrada.',
            'clinic_id.in' => 'A contagem deve pertencer à sua clínica.',
            'title.required' => 'Informe um título para identificar a contagem.',
        ];
    }
}
