<?php

namespace App\Modules\ServiceOrders\Requests;

use App\Http\Requests\Concerns\ValidatesTenantScopedReferences;
use App\Modules\Patients\Models\Patient;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\ServiceOrders\Services\GroomingAgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceOrderRequest extends FormRequest
{
    use ValidatesTenantScopedReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => [
                Rule::requiredIf($this->user()?->clinic_id === null),
                'nullable',
                'integer',
                Rule::exists('clinics', 'id')->where('active', true),
            ],
            'tutor_id' => ['nullable', 'integer', $this->existsInCurrentClinic('tutors')],
            'patient_id' => ['nullable', 'integer', $this->existsInCurrentClinic('patients')],
            'assigned_user_id' => [
                'nullable',
                'integer',
                $this->existsInCurrentClinic('users')->where('active', true),
            ],
            'status' => ['required', 'string', Rule::in(array_keys(ServiceOrder::STATUS_LABELS))],
            'opened_at' => ['nullable', 'date'],
            'scheduled_at' => [
                Rule::requiredIf(in_array($this->input('status'), ServiceOrder::PRE_ARRIVAL_STATUSES, true)
                    || filled($this->input('recurrence_frequency'))),
                'nullable',
                'date',
            ],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'allow_overlap' => ['nullable', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', Rule::in(array_keys(GroomingAgendaService::RECURRENCE_FREQUENCIES))],
            'recurrence_count' => [
                Rule::requiredIf(filled($this->input('recurrence_frequency'))),
                'nullable',
                'integer',
                'min:2',
                'max:'.(int) config('petshop.grooming.max_recurrences', 12),
            ],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],

            'items' => ['nullable', 'array'],
            'items.*.type' => ['nullable', 'string', Rule::in(['service', 'product', 'custom'])],
            'items.*.product_id' => ['nullable', 'integer', $this->existsInCurrentClinic('products')],
            'items.*.petshop_service_id' => ['nullable', 'integer', $this->existsInCurrentClinic('petshop_services')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'clinic_id.required' => 'Selecione a clinica da comanda.',
            'clinic_id.exists' => 'A clinica informada nao foi encontrada ou esta inativa.',
            'status.required' => 'Informe o status da comanda.',
            'status.in' => 'Informe um status valido para a comanda.',
            'tutor_id.exists' => 'O responsável informado nao foi encontrado.',
            'patient_id.exists' => 'O paciente informado nao foi encontrado.',
            'assigned_user_id.exists' => 'O profissional informado nao foi encontrado ou esta inativo.',
            'scheduled_at.required' => 'Informe data e hora do agendamento.',
            'duration_minutes.min' => 'A duracao minima e de 5 minutos.',
            'duration_minutes.max' => 'A duracao maxima e de 10 horas.',
            'recurrence_count.required' => 'Informe quantas vezes o agendamento se repete.',
            'recurrence_count.min' => 'A repeticao precisa de pelo menos 2 ocorrencias.',
            'recurrence_count.max' => 'Limite de ocorrencias por repeticao excedido.',
            'items.*.product_id.exists' => 'Um dos produtos informados nao foi encontrado.',
            'items.*.petshop_service_id.exists' => 'Um dos servicos informados nao foi encontrado.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateScheduleConflicts($validator));

        $validator->after(function (Validator $validator): void {
            $patientId = $this->integer('patient_id');
            $tutorId = $this->integer('tutor_id');

            if ($patientId <= 0 || $tutorId <= 0 || $validator->errors()->has('patient_id')) {
                return;
            }

            $patientBelongsToTutor = Patient::query()
                ->whereKey($patientId)
                ->where(function ($query) use ($tutorId): void {
                    $query
                        ->whereNull('tutor_id')
                        ->orWhere('tutor_id', $tutorId);
                })
                ->exists();

            if (! $patientBelongsToTutor) {
                $validator->errors()->add('patient_id', 'O pet selecionado nao pertence ao responsável informado.');
            }
        });
    }

    protected function currentOrderId(): ?int
    {
        return null;
    }

    /**
     * Impede dois atendimentos simultaneos para o mesmo profissional,
     * a menos que o operador marque "encaixe".
     */
    private function validateScheduleConflicts(Validator $validator): void
    {
        if (
            $validator->errors()->hasAny(['scheduled_at', 'assigned_user_id', 'duration_minutes', 'status'])
            || ! $this->filled('scheduled_at')
            || ! $this->filled('assigned_user_id')
            || $this->boolean('allow_overlap')
            || ! in_array($this->input('status'), ServiceOrder::BLOCKING_STATUSES, true)
        ) {
            return;
        }

        $agenda = app(GroomingAgendaService::class);
        $clinicId = $this->user()?->clinic_id ?? ($this->filled('clinic_id') ? $this->integer('clinic_id') : null);
        $duration = $this->filled('duration_minutes')
            ? $this->integer('duration_minutes')
            : $this->estimatedDuration();
        $first = CarbonImmutable::parse($this->input('scheduled_at'));

        $dates = collect([$first])->concat(
            $this->currentOrderId() === null
                ? $agenda->recurrenceDates($first, $this->input('recurrence_frequency'), $this->integer('recurrence_count'))
                : []
        );

        $messages = $dates
            ->map(function (CarbonImmutable $start) use ($agenda, $clinicId, $duration): ?string {
                $conflict = $agenda->conflicts(
                    $clinicId,
                    $this->integer('assigned_user_id'),
                    $start,
                    $duration,
                    $this->currentOrderId()
                )->first();

                if (! $conflict) {
                    return null;
                }

                return sprintf(
                    '%s às %s já está ocupado com %s (%s).',
                    $start->format('d/m'),
                    $start->format('H:i'),
                    $conflict->patient?->name ?? 'outro atendimento',
                    $conflict->code
                );
            })
            ->filter()
            ->values();

        if ($messages->isNotEmpty()) {
            $validator->errors()->add(
                'scheduled_at',
                'Horário indisponível para o profissional: '.$messages->implode(' ').' Escolha outro horário ou marque "Encaixe".'
            );
        }
    }

    private function estimatedDuration(): int
    {
        $serviceIds = collect($this->input('items', []))
            ->filter(fn ($item) => ($item['type'] ?? 'service') === 'service' && ! empty($item['petshop_service_id']))
            ->pluck('petshop_service_id')
            ->all();

        $minutes = $serviceIds === []
            ? 0
            : (int) \App\Modules\PetShopServices\Models\PetShopService::query()->whereIn('id', $serviceIds)->sum('duration_minutes');

        return $minutes > 0 ? $minutes : ServiceOrder::DEFAULT_DURATION_MINUTES;
    }
}
