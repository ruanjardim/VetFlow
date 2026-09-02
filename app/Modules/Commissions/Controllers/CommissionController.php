<?php

namespace App\Modules\Commissions\Controllers;

use App\Core\Base\BaseCrudController;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Commissions\Requests\StoreCommissionRuleRequest;
use App\Modules\Commissions\Requests\UpdateCommissionRuleRequest;
use App\Modules\Commissions\Services\CommissionService;

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
        ]);
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
