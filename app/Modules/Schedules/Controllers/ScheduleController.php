<?php

namespace App\Modules\Schedules\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Patients\Models\Patient;
use App\Modules\Schedules\Requests\StoreScheduleRequest;
use App\Modules\Schedules\Requests\UpdateScheduleRequest;
use App\Modules\Schedules\Services\ScheduleCalendarService;
use App\Modules\Schedules\Services\ScheduleService;
use App\Modules\Tutors\Models\Tutor;

class ScheduleController extends BaseCrudController
{
    public function __construct(ScheduleService $service, private readonly ScheduleCalendarService $calendar)
    {
        $this->service = $service;
        $this->viewPath = 'schedules';
        $this->routeName = 'schedules';
        $this->viewVariable = 'schedules';
    }

    public function index()
    {
        return view('schedules.index', $this->calendar->calendarData(
            request()->query('date'),
            request()->query('view')
        ));
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

    protected function storeRequest(): string
    {
        return StoreScheduleRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateScheduleRequest::class;
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
