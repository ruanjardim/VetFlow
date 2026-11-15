<?php

namespace App\Modules\Saas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Requests\AcceptLegalPackageRequest;
use App\Modules\Saas\Services\LegalDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LegalAcceptanceController extends Controller
{
    public function __construct(private readonly LegalDocumentService $legal) {}

    public function show(): View
    {
        return view('legal.acceptance', $this->legal->acceptanceData(request()->user()));
    }

    public function store(AcceptLegalPackageRequest $request): RedirectResponse
    {
        $uuid = $this->legal->acceptCurrentPackage(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return back()->with('success', 'Pacote jurídico aceito e registrado. Comprovante: '.$uuid);
    }
}
