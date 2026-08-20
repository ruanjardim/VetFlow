<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditEventController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', Rule::in(array_keys(AuditTrailService::eventLabels()))],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('audit.index', [
            'events' => $this->audit->paginate($filters),
            'eventLabels' => AuditTrailService::eventLabels(),
            'filters' => $filters,
        ]);
    }
}
