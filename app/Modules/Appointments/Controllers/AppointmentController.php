<?php

namespace App\Modules\Appointments\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Appointments\Requests\StoreAppointmentRequest;
use App\Modules\Appointments\Requests\StoreAppointmentReminderRequest;
use App\Modules\Appointments\Requests\UpdateAppointmentRequest;
use App\Modules\Appointments\Services\AppointmentReminderService;
use App\Modules\Appointments\Services\AppointmentService;
use App\Modules\Patients\Models\Patient;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends BaseCrudController
{
    public function __construct(AppointmentService $service)
    {
        $this->service = $service;
        $this->viewPath = 'appointments';
        $this->routeName = 'appointments';
        $this->viewVariable = 'appointments';
    }

    public function create()
    {
        return view("{$this->viewPath}.create", $this->formData());
    }

    public function edit(int $id)
    {
        return view("{$this->viewPath}.edit", array_merge($this->formData(), [
            'item' => $this->service->findOrFail($id),
        ]));
    }

    public function reminders(Request $request, AppointmentReminderService $reminders)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'state' => ['nullable', 'string', Rule::in(array_merge(
                ['all', 'pending'],
                array_keys(AppointmentReminderService::OUTCOME_LABELS)
            ))],
        ]);

        return view("{$this->viewPath}.reminders", [
            'summary' => $reminders->queue(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $validated['state'] ?? 'all'
            ),
            'channelLabels' => AppointmentReminderService::CHANNEL_LABELS,
            'outcomeLabels' => AppointmentReminderService::OUTCOME_LABELS,
        ]);
    }

    public function storeReminder(
        StoreAppointmentReminderRequest $request,
        int $appointment,
        AppointmentReminderService $reminders
    ) {
        $validated = $request->validated();
        $reminders->record($appointment, $validated);

        return redirect()
            ->route('appointments.reminders', array_filter([
                'from' => $validated['return_from'] ?? null,
                'to' => $validated['return_to'] ?? null,
                'state' => $validated['return_state'] ?? null,
            ]))
            ->with('success', 'Contato da consulta registrado com sucesso.');
    }

    protected function storeRequest(): string
    {
        return StoreAppointmentRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateAppointmentRequest::class;
    }

    private function formData(): array
    {
        return [
            'patients' => Patient::query()->orderBy('name')->get(),
            'tutors' => Tutor::query()->orderBy('name')->get(),
            'preselectedPatientId' => (int) request()->query('patient_id'),
            'preselectedTutorId' => (int) request()->query('tutor_id'),
        ];
    }
}
