<?php

namespace App\Modules\ServiceOrders\Requests;

use App\Modules\ServiceOrders\Services\GroomingAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGroomingScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $days = $this->input('days', []);

        if (is_array($days)) {
            foreach ($days as $weekday => $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $days[$weekday]['enabled'] = array_key_exists('enabled', $definition)
                    && filter_var($definition['enabled'], FILTER_VALIDATE_BOOL);
            }
        }

        $this->merge([
            'days' => $days,
            'inherit' => $this->boolean('inherit'),
        ]);
    }

    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id ?? $this->integer('clinic_id');

        return [
            'clinic_id' => [
                Rule::requiredIf($this->user()?->clinic_id === null),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->where('active', true)),
            ],
            'inherit' => ['nullable', 'boolean'],
            'slot_minutes' => [
                Rule::requiredIf(! $this->filled('user_id')),
                'nullable',
                'integer',
                Rule::in(GroomingAvailabilityService::SLOT_OPTIONS),
            ],
            'days' => [Rule::requiredIf(! $this->boolean('inherit')), 'nullable', 'array'],
            'days.*.enabled' => ['required', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('inherit')) {
                return;
            }

            foreach ($this->input('days', []) as $weekday => $definition) {
                if (! is_array($definition) || ! ($definition['enabled'] ?? false)) {
                    continue;
                }

                $opens = $definition['opens_at'] ?? null;
                $closes = $definition['closes_at'] ?? null;
                $breakStart = $definition['break_start'] ?? null;
                $breakEnd = $definition['break_end'] ?? null;
                $field = "days.$weekday";

                if (! $opens || ! $closes || $opens >= $closes) {
                    $validator->errors()->add($field, 'A abertura deve ser anterior ao fechamento.');

                    continue;
                }

                if (($breakStart && ! $breakEnd) || (! $breakStart && $breakEnd)) {
                    $validator->errors()->add($field, 'Informe o início e o fim do intervalo.');

                    continue;
                }

                if ($breakStart && ! ($opens < $breakStart && $breakStart < $breakEnd && $breakEnd < $closes)) {
                    $validator->errors()->add($field, 'O intervalo deve ficar dentro do expediente.');
                }
            }
        });
    }
}
