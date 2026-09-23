<?php

namespace App\Modules\ServiceOrders\Controllers;

use App\Core\Base\BaseCrudController;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Patients\Models\Patient;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use App\Modules\ServiceOrders\Requests\StoreServiceOrderRequest;
use App\Modules\ServiceOrders\Requests\UpdateServiceOrderRequest;
use App\Modules\ServiceOrders\Requests\UpdateServiceOrderStatusRequest;
use App\Modules\ServiceOrders\Models\ServiceOrder;
use App\Modules\ServiceOrders\Services\GroomingAgendaService;
use App\Modules\ServiceOrders\Services\ServiceOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\Request;

class ServiceOrderController extends BaseCrudController
{
    public function __construct(ServiceOrderService $service, private readonly GroomingAgendaService $agenda)
    {
        $this->service = $service;
        $this->viewPath = 'service-orders';
        $this->routeName = 'service-orders';
        $this->viewVariable = 'serviceOrders';
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

    public function board(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return view("{$this->viewPath}.board", $this->service->board($validated['date'] ?? null));
    }

    public function agenda(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'clinic_id' => ['nullable', 'integer', Rule::exists('clinics', 'id')->where('active', true)],
        ]);

        $clinics = auth()->user()?->clinic_id === null
            ? Clinic::query()->active()->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
        $clinicId = auth()->user()?->clinic_id
            ?? (isset($validated['clinic_id']) ? (int) $validated['clinic_id'] : $clinics->first()?->id);
        $day = CarbonImmutable::parse($validated['date'] ?? now()->toDateString());

        return view("{$this->viewPath}.agenda", array_merge($this->agenda->dayGrid($day, $clinicId), [
            'clinics' => $clinics,
            'selectedClinicId' => $clinicId,
        ]));
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'assigned_user_id' => ['nullable', 'integer'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'clinic_id' => ['nullable', 'integer'],
            'ignore_id' => ['nullable', 'integer'],
        ]);

        $clinicId = auth()->user()?->clinic_id ?? (isset($validated['clinic_id']) ? (int) $validated['clinic_id'] : null);
        $assignedUserId = isset($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : null;

        if ($assignedUserId !== null && ! $this->agenda->professionals($clinicId)->contains('id', $assignedUserId)) {
            $assignedUserId = null;
        }

        return response()->json([
            'date' => $validated['date'],
            'assigned_user_id' => $assignedUserId,
            'slots' => $this->agenda->availableSlots(
                CarbonImmutable::parse($validated['date']),
                $clinicId,
                $assignedUserId,
                (int) ($validated['duration_minutes'] ?? ServiceOrder::DEFAULT_DURATION_MINUTES),
                isset($validated['ignore_id']) ? (int) $validated['ignore_id'] : null,
            )->values(),
        ]);
    }

    public function updateStatus(UpdateServiceOrderStatusRequest $request, int $serviceOrder)
    {
        $this->service->updateStatus($serviceOrder, $request->validated('status'));

        return back()->with('success', 'Status da comanda atualizado.');
    }

    public function destroy(int $id)
    {
        $order = $this->service->findOrFail($id);

        if (! in_array($order->status, ServiceOrder::DELETABLE_STATUSES, true)) {
            return redirect()
                ->route('service-orders.index')
                ->with('error', 'Somente agendamentos ou comandas em espera, sem histórico, podem ser excluídos.');
        }

        if ($order->sales()->withTrashed()->exists()) {
            return redirect()
                ->route('service-orders.index')
                ->with('error', 'Esta comanda possui histórico de venda e não pode ser excluída.');
        }

        $this->service->delete($id);

        return redirect()
            ->route('service-orders.index')
            ->with('success', 'Comanda removida com sucesso.');
    }

    protected function storeRequest(): string
    {
        return StoreServiceOrderRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateServiceOrderRequest::class;
    }

    private function formData(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'clinics' => Clinic::query()
                ->active()
                ->orderBy('trade_name')
                ->orderBy('corporate_name')
                ->get(),
            'tutors' => Tutor::query()->orderBy('name')->get(),
            'patients' => Patient::query()->orderBy('name')->get(),
            'assignedUsers' => User::query()
                ->active()
                ->whereNotNull('clinic_id')
                ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
                ->orderBy('name')
                ->get(),
            'products' => Product::query()->active()->orderBy('name')->get(),
            'petShopServices' => PetShopService::query()->active()->orderBy('name')->get(),
            'recurrenceFrequencies' => GroomingAgendaService::RECURRENCE_FREQUENCIES,
            'prefill' => [
                'status' => in_array(request()->query('status'), array_keys(ServiceOrder::STATUS_LABELS), true)
                    ? request()->query('status')
                    : null,
                'scheduled_at' => $this->prefillDateTime(request()->query('scheduled_at')),
                'assigned_user_id' => request()->integer('assigned_user_id') ?: null,
                'patient_id' => request()->integer('patient_id') ?: null,
                'tutor_id' => request()->integer('tutor_id') ?: null,
            ],
        ];
    }

    private function prefillDateTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d\\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
