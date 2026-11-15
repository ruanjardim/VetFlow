<?php

namespace App\Modules\Patients\Services;

use App\Modules\MedicalRecords\Models\MedicalRecordExamResult;
use App\Modules\Prescriptions\Models\Prescription;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PatientClinicalTimelineService
{
    /**
     * @param  array<string, Collection>  $sources
     * @return Collection<int, array<string, mixed>>
     */
    public function build(array $sources): Collection
    {
        $events = new Collection;

        foreach ($sources['appointments'] as $appointment) {
            $events->push($this->event(
                occurredAt: $appointment->scheduled_at,
                category: 'Consulta',
                title: $appointment->title,
                description: 'Atendimento registrado na agenda clínica.',
                status: $this->appointmentStatus($appointment->status),
                statusClass: match ($appointment->status) {
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    'confirmed' => 'warning',
                    default => 'muted-badge',
                },
                routeName: 'appointments.edit',
                routeParameters: [$appointment->id],
            ));
        }

        foreach ($sources['medicalRecords'] as $medicalRecord) {
            $events->push($this->event(
                occurredAt: $medicalRecord->examined_at,
                category: 'Prontuário',
                title: 'Prontuário #'.$medicalRecord->id,
                description: Str::limit($medicalRecord->diagnosis ?: 'Registro clínico sem diagnóstico informado.', 180),
                status: 'Registrado',
                statusClass: 'success',
                actor: $medicalRecord->createdBy?->name,
                routeName: 'medical-records.show',
                routeParameters: [$medicalRecord->id],
            ));

            foreach ($medicalRecord->examRequests as $examRequest) {
                $result = $examRequest->result;

                if (! $result) {
                    continue;
                }

                $events->push($this->event(
                    occurredAt: $result->resulted_at ?? $result->finalized_at ?? $result->created_at,
                    category: 'Resultado de exame',
                    title: $examRequest->exam_name,
                    description: Str::limit($result->result_summary ?: 'Resultado registrado sem resumo.', 180),
                    status: MedicalRecordExamResult::STATUS_LABELS[$result->status] ?? $result->status,
                    statusClass: match ($result->status) {
                        'finalized' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    },
                    actor: match ($result->status) {
                        'finalized' => $result->finalizedBy?->name,
                        'cancelled' => $result->cancelledBy?->name,
                        default => $result->createdBy?->name,
                    },
                    routeName: 'exam-results.edit',
                    routeParameters: [$examRequest->id],
                ));
            }
        }

        foreach ($sources['prescriptions'] as $prescription) {
            $events->push($this->event(
                occurredAt: $prescription->prescribed_at,
                category: 'Prescrição',
                title: 'Prescrição #'.$prescription->id,
                description: Str::limit($prescription->items->pluck('medication_name')->join(', ') ?: 'Sem itens visíveis.', 180),
                status: Prescription::STATUS_LABELS[$prescription->status] ?? $prescription->status,
                statusClass: match ($prescription->status) {
                    'finalized' => 'success',
                    'cancelled' => 'danger',
                    default => 'warning',
                },
                actor: $prescription->createdBy?->name,
                routeName: 'prescriptions.show',
                routeParameters: [$prescription->id],
            ));
        }

        foreach ($sources['vaccinations'] as $vaccination) {
            $events->push($this->event(
                occurredAt: $vaccination->applied_at ?? $vaccination->scheduled_for?->startOfDay(),
                category: 'Vacinação',
                title: $vaccination->vaccine_name,
                description: $vaccination->applied_at ? 'Aplicação registrada na carteira do paciente.' : 'Aplicação agendada na carteira do paciente.',
                status: match ($vaccination->status) {
                    'scheduled' => 'Agendada',
                    'applied' => 'Aplicada',
                    'skipped' => 'Não aplicada',
                    default => $vaccination->status,
                },
                statusClass: match ($vaccination->status) {
                    'applied' => 'success',
                    'skipped' => 'danger',
                    default => 'warning',
                },
                actor: $vaccination->createdBy?->name,
                routeName: 'vaccinations.edit',
                routeParameters: [$vaccination->id],
            ));
        }

        foreach ($sources['hospitalizations'] as $hospitalization) {
            $events->push($this->event(
                occurredAt: $hospitalization->admitted_at,
                category: 'Internação',
                title: 'Admissão hospitalar #'.$hospitalization->id,
                description: Str::limit($hospitalization->admission_reason, 180),
                status: match ($hospitalization->status) {
                    'hospitalized' => 'Internado',
                    'discharged' => 'Alta registrada',
                    'cancelled' => 'Cancelada',
                    default => $hospitalization->status,
                },
                statusClass: match ($hospitalization->status) {
                    'hospitalized' => 'warning',
                    'discharged' => 'success',
                    default => 'danger',
                },
                actor: $hospitalization->admittedBy?->name,
                routeName: 'hospitalizations.edit',
                routeParameters: [$hospitalization->id],
            ));

            if ($hospitalization->discharged_at) {
                $events->push($this->event(
                    occurredAt: $hospitalization->discharged_at,
                    category: 'Internação',
                    title: 'Alta hospitalar #'.$hospitalization->id,
                    description: Str::limit($hospitalization->discharge_notes ?: 'Alta registrada sem observações adicionais.', 180),
                    status: 'Alta registrada',
                    statusClass: 'success',
                    routeName: 'hospitalizations.edit',
                    routeParameters: [$hospitalization->id],
                ));
            }
        }

        foreach ($sources['hospitalizationEvolutions'] as $evolution) {
            $events->push($this->event(
                occurredAt: $evolution->observed_at,
                category: 'Evolução',
                title: 'Evolução da internação #'.$evolution->hospitalization_id,
                description: Str::limit($evolution->notes, 180),
                status: 'Observada',
                statusClass: 'muted-badge',
                actor: $evolution->recordedBy?->name,
                routeName: 'hospitalizations.edit',
                routeParameters: [$evolution->hospitalization_id],
            ));
        }

        foreach ($sources['activeClinicalAlerts']->concat($sources['resolvedClinicalAlerts']) as $alert) {
            $events->push($this->event(
                occurredAt: $alert->created_at,
                category: 'Alerta clínico',
                title: $alert->title,
                description: Str::limit($alert->details ?: 'Alerta registrado sem detalhes adicionais.', 180),
                status: $alert->isActive() ? 'Ativo' : 'Resolvido',
                statusClass: $alert->isActive() ? 'danger' : 'success',
                actor: $alert->createdBy?->name,
                routeName: 'patients.show',
                routeParameters: [$alert->patient_id],
            ));

            if ($alert->resolved_at) {
                $events->push($this->event(
                    occurredAt: $alert->resolved_at,
                    category: 'Alerta clínico',
                    title: 'Resolução: '.$alert->title,
                    description: Str::limit($alert->resolution_notes ?: 'Alerta resolvido sem observações adicionais.', 180),
                    status: 'Resolvido',
                    statusClass: 'success',
                    actor: $alert->resolvedBy?->name,
                    routeName: 'patients.show',
                    routeParameters: [$alert->patient_id],
                ));
            }
        }

        return $events
            ->filter(fn (array $event): bool => $event['occurred_at'] !== null)
            ->sortByDesc(fn (array $event): int => $event['occurred_at']->getTimestamp())
            ->take(30)
            ->values();
    }

    /** @return array<string, mixed> */
    private function event(
        ?CarbonInterface $occurredAt,
        string $category,
        string $title,
        string $description,
        string $status,
        string $statusClass,
        ?string $actor = null,
        ?string $routeName = null,
        array $routeParameters = [],
    ): array {
        return [
            'occurred_at' => $occurredAt,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'status_class' => $statusClass,
            'actor' => $actor,
            'route_name' => $routeName,
            'route_parameters' => $routeParameters,
        ];
    }

    private function appointmentStatus(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Agendada',
            'confirmed' => 'Confirmada',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            default => $status,
        };
    }
}
