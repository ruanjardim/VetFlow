<?php

namespace App\Modules\Implementation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $clinicRule = Rule::exists('clinics', 'id')
            ->where(fn ($query) => $query
                ->where('active', true)
                ->whereNull('deleted_at'));

        if ($clinicId !== null) {
            $clinicRule->where(fn ($query) => $query->where('id', $clinicId));
        }

        return [
            'clinic_id' => ['required', 'integer', $clinicRule],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione a clínica que receberá os dados.',
            'clinic_id.exists' => 'A clínica selecionada não está disponível para esta implantação.',
        ];
    }
}
