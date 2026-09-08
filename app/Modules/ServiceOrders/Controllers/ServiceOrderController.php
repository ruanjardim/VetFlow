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
use App\Modules\ServiceOrders\Services\ServiceOrderService;
use App\Modules\Tutors\Models\Tutor;
use Illuminate\Http\Request;

class ServiceOrderController extends BaseCrudController
{
    public function __construct(ServiceOrderService $service)
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

    public function updateStatus(UpdateServiceOrderStatusRequest $request, int $serviceOrder)
    {
        $this->service->updateStatus($serviceOrder, $request->validated('status'));

        return back()->with('success', 'Status da comanda atualizado.');
    }

    public function destroy(int $id)
    {
        $order = $this->service->findOrFail($id);

        if ($order->status !== 'open') {
            return redirect()
                ->route('service-orders.index')
                ->with('error', 'Somente comandas abertas e sem histórico podem ser excluídas.');
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
        ];
    }
}
