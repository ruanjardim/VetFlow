<?php

namespace App\Modules\Sales\Controllers;

use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Sales\Models\CashSession;
use App\Modules\Sales\Models\CashSessionMovement;
use App\Modules\Sales\Requests\CloseCashSessionRequest;
use App\Modules\Sales\Requests\OpenCashSessionRequest;
use App\Modules\Sales\Requests\StoreCashMovementRequest;
use App\Modules\Sales\Services\CashSessionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cash sessions (caixas): the operator opens, moves and closes their own
 * session; users with cash-sessions.review see every session of the clinic,
 * review (encerrar) and reopen them.
 */
class CashSessionController
{
    public function __construct(
        private readonly CashSessionService $sessions,
        private readonly TenantContext $tenant
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(array_keys(CashSession::STATUS_LABELS))],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $clinicId = $this->selectedClinicId($request);
        $canReview = $user->can('cash-sessions.review');
        $current = $this->sessions->currentFor($user, $clinicId);

        $sessions = CashSession::query()
            ->with(['user', 'reviewedBy'])
            ->where('clinic_id', $clinicId)
            ->when(! $canReview, fn ($query) => $query->where('user_id', $user->id))
            ->when($canReview && ! empty($validated['user_id']), fn ($query) => $query->where('user_id', (int) $validated['user_id']))
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['from']), fn ($query) => $query->where('opened_at', '>=', $validated['from'].' 00:00:00'))
            ->when(! empty($validated['to']), fn ($query) => $query->where('opened_at', '<=', $validated['to'].' 23:59:59'))
            ->orderByRaw("case when status = 'open' then 0 when status = 'closed' then 1 else 2 end")
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        return view('sales.cash-sessions.index', [
            'current' => $current,
            'currentSummary' => $current ? $this->sessions->summary($current) : null,
            'suggestedOpening' => $this->sessions->suggestedOpening($user, $clinicId),
            'sessions' => $sessions,
            'canReview' => $canReview,
            'awaitingReview' => $canReview
                ? CashSession::query()->where('clinic_id', $clinicId)->where('status', 'closed')->count()
                : 0,
            'operators' => $canReview ? $this->operators($clinicId) : collect(),
            'filters' => $validated,
            'clinics' => $this->availableClinics(),
            'selectedClinicId' => $clinicId,
            'requiresClinic' => $this->tenant->isGlobal(),
        ]);
    }

    public function store(OpenCashSessionRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clinicId = $this->selectedClinicId($request);
        $session = $this->sessions->open($user, $clinicId, (float) ($request->validated()['opening_amount'] ?? 0), $request->validated()['notes'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Caixa '.$session->code.' aberto.',
                'session' => $session->toPdvArray(),
            ], 201);
        }

        return redirect()
            ->to($request->input('redirect_to') === 'pdv' ? route('sales.create') : route('sales.cash-sessions.show', $session->id))
            ->with('success', 'Caixa '.$session->code.' aberto com fundo de troco de R$ '.number_format((float) $session->opening_amount, 2, ',', '.').'.');
    }

    public function show(Request $request, int $cashSession): View
    {
        $session = $this->findVisible($request, $cashSession);
        $session->load(['user', 'closedBy', 'reviewedBy']);
        $summary = $this->sessions->summary($session);
        $user = $request->user();

        return view('sales.cash-sessions.show', [
            'session' => $session,
            'summary' => $summary,
            'figures' => $session->isOpen() ? $summary : ($session->closing_snapshot ?? $summary),
            'canOperate' => $session->isOpen() && ($session->user_id === $user->id || $user->can('cash-sessions.review')),
            'canReview' => $user->can('cash-sessions.review'),
            'movementTypes' => array_intersect_key(CashSessionMovement::TYPE_LABELS, array_flip(CashSessionMovement::MANUAL_TYPES)),
        ]);
    }

    public function storeMovement(StoreCashMovementRequest $request, int $cashSession): RedirectResponse
    {
        $session = $this->findOperable($request, $cashSession);
        $validated = $request->validated();
        $movement = $this->sessions->addMovement($session, $validated['type'], $validated, $request->user());

        return redirect()
            ->route('sales.cash-sessions.show', $session->id)
            ->with('success', sprintf(
                '%s de R$ %s %s.',
                $movement->typeLabel(),
                number_format((float) $movement->amount, 2, ',', '.'),
                $movement->type === 'supply' ? 'registrado' : 'registrada'
            ));
    }

    public function closeForm(Request $request, int $cashSession): View
    {
        $session = $this->findOperable($request, $cashSession);
        $summary = $this->sessions->summary($session);

        return view('sales.cash-sessions.close', [
            'session' => $session,
            'summary' => $summary,
            'suggestedCashLeft' => round(min((float) $session->opening_amount, max(0, (float) $summary['cash']['expected'])), 2),
        ]);
    }

    public function close(CloseCashSessionRequest $request, int $cashSession): RedirectResponse
    {
        $session = $this->findOperable($request, $cashSession);
        $session = $this->sessions->close($session, $request->validated(), $request->user());
        $difference = (float) $session->difference_total;

        return redirect()
            ->route('sales.cash-sessions.show', $session->id)
            ->with(
                abs($difference) < 0.01 ? 'success' : 'warning',
                abs($difference) < 0.01
                    ? 'Caixa '.$session->code.' fechado sem diferenças.'
                    : sprintf(
                        'Caixa %s fechado com %s de R$ %s. O gestor confere na lista de caixas.',
                        $session->code,
                        $difference > 0 ? 'sobra' : 'falta',
                        number_format(abs($difference), 2, ',', '.')
                    )
            );
    }

    public function review(Request $request, int $cashSession): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $session = $this->findVisible($request, $cashSession);
        $this->sessions->review($session, $request->user(), $validated['review_notes'] ?? null);

        return redirect()
            ->route('sales.cash-sessions.show', $session->id)
            ->with('success', 'Caixa '.$session->code.' conferido e encerrado.');
    }

    public function reopen(Request $request, int $cashSession): RedirectResponse
    {
        $session = $this->findVisible($request, $cashSession);
        $this->sessions->reopen($session, $request->user());

        return redirect()
            ->route('sales.cash-sessions.show', $session->id)
            ->with('success', 'Caixa '.$session->code.' reaberto. As taxas lançadas no fechamento foram canceladas.');
    }

    /**
     * Sessions of the operator, or any session of the clinic for reviewers.
     */
    private function findVisible(Request $request, int $id): CashSession
    {
        $session = CashSession::query()->findOrFail($id);
        $user = $request->user();

        abort_unless($session->user_id === $user->id || $user->can('cash-sessions.review'), 403);

        return $session;
    }

    /**
     * Only the operator (or a reviewer) moves or closes an open session.
     */
    private function findOperable(Request $request, int $id): CashSession
    {
        $session = $this->findVisible($request, $id);

        abort_unless($session->isOpen(), 404);

        return $session;
    }

    private function selectedClinicId(Request $request): int
    {
        if (! $this->tenant->isGlobal()) {
            return (int) $this->tenant->clinicId();
        }

        $requestedId = (int) $request->input('clinic_id');
        $clinicId = Clinic::query()
            ->where('active', true)
            ->when($requestedId > 0, fn ($query) => $query->whereKey($requestedId))
            ->orderBy('trade_name')
            ->value('id');

        abort_if(! $clinicId, 404, 'Nenhum estabelecimento ativo disponível.');

        return (int) $clinicId;
    }

    private function availableClinics(): Collection
    {
        return $this->tenant->isGlobal()
            ? Clinic::query()->where('active', true)->orderBy('trade_name')->orderBy('corporate_name')->get()
            : collect();
    }

    /**
     * Operators with at least one session in the clinic.
     */
    private function operators(int $clinicId): Collection
    {
        return User::query()
            ->whereIn('id', CashSession::query()->where('clinic_id', $clinicId)->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
