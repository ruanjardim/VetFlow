<?php

namespace App\Modules\ServiceOrders\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\ServiceOrders\Models\GroomingScheduleBlock;
use App\Modules\ServiceOrders\Requests\StoreGroomingScheduleBlockRequest;
use App\Modules\ServiceOrders\Requests\UpdateGroomingScheduleRequest;
use App\Modules\ServiceOrders\Services\GroomingAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroomingScheduleController extends Controller
{
    public function __construct(private readonly GroomingAvailabilityService $availability) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
            'professional_id' => ['nullable', 'integer'],
        ]);
        $clinics = $request->user()?->clinic_id === null
            ? Clinic::query()->active()->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
        $clinic = $request->user()?->clinic_id
            ? Clinic::query()->findOrFail($request->user()->clinic_id)
            : Clinic::query()->active()->find($validated['clinic_id'] ?? $clinics->first()?->id);
        $professionals = $clinic
            ? User::query()->active()->where('clinic_id', $clinic->id)->orderBy('name')->get()
            : collect();
        $professional = isset($validated['professional_id'])
            ? $professionals->firstWhere('id', (int) $validated['professional_id'])
            : null;

        return view('service-orders.grooming-settings', [
            'clinics' => $clinics,
            'clinic' => $clinic,
            'professionals' => $professionals,
            'professional' => $professional,
            'clinicSchedule' => $clinic ? $this->availability->scheduleFor($clinic) : null,
            'professionalSchedule' => $clinic && $professional
                ? $this->availability->scheduleFor($clinic, $professional)
                : null,
            'professionalInherits' => $professional?->grooming_schedule === null,
            'dayLabels' => GroomingAvailabilityService::DAY_LABELS,
            'slotOptions' => GroomingAvailabilityService::SLOT_OPTIONS,
            'blocks' => $clinic
                ? GroomingScheduleBlock::query()
                    ->with(['user:id,name', 'creator:id,name'])
                    ->where('clinic_id', $clinic->id)
                    ->where('ends_at', '>=', now()->startOfDay())
                    ->orderBy('starts_at')
                    ->limit(100)
                    ->get()
                : collect(),
        ]);
    }

    public function update(UpdateGroomingScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinic = $this->clinicFor($request, $data['clinic_id'] ?? null);
        $professional = isset($data['user_id'])
            ? User::query()->where('clinic_id', $clinic->id)->findOrFail($data['user_id'])
            : null;

        $this->availability->saveSchedule($clinic, $data, $professional);

        return redirect()
            ->route('service-orders.grooming-settings', array_filter([
                'clinic_id' => $request->user()?->clinic_id === null ? $clinic->id : null,
                'professional_id' => $professional?->id,
            ]))
            ->with('success', $professional
                ? 'Disponibilidade do profissional atualizada.'
                : 'Configuração da grade atualizada.');
    }

    public function storeBlock(StoreGroomingScheduleBlockRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $clinic = $this->clinicFor($request, $data['clinic_id'] ?? null);
        $this->availability->createBlock($clinic, $data);

        return redirect()
            ->route('service-orders.grooming-settings', array_filter([
                'clinic_id' => $request->user()?->clinic_id === null ? $clinic->id : null,
                'professional_id' => $data['user_id'] ?? null,
            ]))
            ->with('success', 'Bloqueio de agenda registrado.');
    }

    public function destroyBlock(Request $request, GroomingScheduleBlock $block): RedirectResponse
    {
        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);
        $clinic = $this->clinicFor($request, $validated['clinic_id'] ?? null);
        $this->availability->deleteBlock($block, $clinic);

        return redirect()
            ->route('service-orders.grooming-settings', array_filter([
                'clinic_id' => $request->user()?->clinic_id === null ? $clinic->id : null,
            ]))
            ->with('success', 'Bloqueio removido.');
    }

    private function clinicFor(Request $request, mixed $clinicId): Clinic
    {
        $resolved = $request->user()?->clinic_id ?? $clinicId;

        return Clinic::query()->active()->findOrFail((int) $resolved);
    }
}
