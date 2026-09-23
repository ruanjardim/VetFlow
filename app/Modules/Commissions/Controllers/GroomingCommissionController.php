<?php

namespace App\Modules\Commissions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commissions\Services\GroomingCommissionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroomingCommissionController extends Controller
{
    public function __construct(private readonly GroomingCommissionService $commissions)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? today()->startOfMonth());
        $to = CarbonImmutable::parse($validated['to'] ?? today());
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;

        return view('grooming-commissions.index', array_merge(
            $this->commissions->overview($from, $to, $userId),
            [
                'from' => $from,
                'to' => $to,
                'userId' => $userId,
                'professionals' => $this->professionals($request),
            ]
        ));
    }

    public function settle(Request $request): RedirectResponse
    {
        $clinicId = $request->user()?->clinic_id;

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->when($clinicId !== null, fn ($rule) => $rule->where('clinic_id', $clinicId))],
            'until' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $settlement = $this->commissions->settle(
            (int) $validated['user_id'],
            CarbonImmutable::parse($validated['until']),
            CarbonImmutable::parse($validated['due_date']),
            $validated['notes'] ?? null,
        );

        return back()->with('success', sprintf(
            'Fechamento de R$ %s gerado para %s. A conta a pagar está no Financeiro.',
            number_format((float) $settlement->total, 2, ',', '.'),
            $settlement->user?->name ?? 'o profissional'
        ));
    }

    private function professionals(Request $request)
    {
        $clinicId = $request->user()?->clinic_id;

        return User::query()
            ->active()
            ->whereNotNull('clinic_id')
            ->when($clinicId !== null, fn ($query) => $query->where('clinic_id', $clinicId))
            ->orderByDesc('grooming_professional')
            ->orderBy('name')
            ->get();
    }
}
