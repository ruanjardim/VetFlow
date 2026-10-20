<?php

namespace App\Modules\Commissions\Controllers;

use App\Core\Base\BaseCrudController;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Requests\StoreCommissionRuleRequest;
use App\Modules\Commissions\Requests\StoreSalesCommissionSettlementRequest;
use App\Modules\Commissions\Requests\UpdateCommissionRuleRequest;
use App\Modules\Commissions\Services\CommissionService;
use Illuminate\Http\RedirectResponse;

class CommissionController extends BaseCrudController
{
    public function __construct(CommissionService $service)
    {
        $this->service = $service;
        $this->viewPath = 'commissions';
        $this->routeName = 'commissions';
        $this->viewVariable = 'rules';
    }

    public function index()
    {
        return view('commissions.index', [
            'rules' => $this->service->paginate(),
            'preview' => $this->service->preview(request()->query('from'), request()->query('to')),
            'settlements' => $this->service->recentSettlements(),
        ]);
    }

    public function settle(StoreSalesCommissionSettlementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $settlement = $this->service->settle(
            $actor,
            (int) $data['seller_user_id'],
            $data['from'],
            $data['to'],
            $data['due_date'],
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('commissions.index', ['from' => $data['from'], 'to' => $data['to']])
            ->with('success', sprintf(
                'Fechamento de R$ %s criado para %s. A conta a pagar já está no Financeiro.',
                number_format((float) $settlement->total, 2, ',', '.'),
                $settlement->seller?->name ?? 'o vendedor',
            ));
    }

    public function create()
    {
        return view('commissions.create', [
            'sellers' => $this->service->sellers(),
            'clinics' => $this->clinics(),
        ]);
    }

    public function edit(int $id)
    {
        return view('commissions.edit', [
            'rule' => $this->service->findOrFail($id),
            'sellers' => $this->service->sellers(),
            'clinics' => $this->clinics(),
        ]);
    }

    protected function storeRequest(): string
    {
        return StoreCommissionRuleRequest::class;
    }

    protected function updateRequest(): string
    {
        return UpdateCommissionRuleRequest::class;
    }

    private function clinics()
    {
        return Clinic::query()->active()->orderBy('trade_name')->get();
    }
}
