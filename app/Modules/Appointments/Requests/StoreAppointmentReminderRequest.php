<?php

namespace App\Modules\Appointments\Requests;

use App\Modules\Appointments\Services\AppointmentReminderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(array_keys(AppointmentReminderService::CHANNEL_LABELS))],
            'outcome' => ['required', 'string', Rule::in(array_keys(AppointmentReminderService::OUTCOME_LABELS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'return_from' => ['nullable', 'date'],
            'return_to' => ['nullable', 'date', 'after_or_equal:return_from'],
            'return_state' => ['nullable', 'string', Rule::in(array_merge(['all', 'pending'], array_keys(AppointmentReminderService::OUTCOME_LABELS)))],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required' => 'Informe o canal usado no contato.',
            'channel.in' => 'Informe um canal de contato valido.',
            'outcome.required' => 'Informe o resultado do contato.',
            'outcome.in' => 'Informe um resultado de contato valido.',
        ];
    }
}
