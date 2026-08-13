<?php

namespace App\Modules\Patients\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = app(TenantContext::class)->clinicId();
        $tutorExists = Rule::exists('tutors', 'id')
            ->whereNull('deleted_at');

        if ($clinicId !== null) {
            $tutorExists->where('clinic_id', $clinicId);
        }

        return [
            'tutor_id' => ['required', 'integer', $tutorExists],
            'name' => ['required', 'string', 'max:255'],
            'species_choice' => ['nullable', 'string', 'max:30'],
            'new_species' => ['nullable', 'string', 'max:120'],
            'species' => ['nullable', 'string', 'max:255'],
            'breed_choice' => ['nullable', 'string', 'max:30'],
            'new_breed' => ['nullable', 'string', 'max:120'],
            'breed' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'weight' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tutor_id.required' => 'Selecione o responsável.',
            'tutor_id.exists' => 'O responsável selecionado não está disponível para esta clínica.',
            'name.required' => 'Informe o nome do paciente.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'birth_date.date' => 'Informe uma data de nascimento válida.',
            'birth_date.before_or_equal' => 'A data de nascimento não pode estar no futuro.',
            'weight.numeric' => 'Informe um peso válido.',
            'weight.gt' => 'O peso deve ser maior que zero.',
            'notes.max' => 'As observações devem ter no máximo 5.000 caracteres.',
        ];
    }
}
