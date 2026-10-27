<?php

namespace App\Modules\Financial\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Financial\Models\FinancialAccount;
use App\Modules\Financial\Models\FinancialReconciliation;
use App\Modules\Financial\Models\FinancialStatementEntry;
use App\Modules\Financial\Models\FinancialStatementImport;
use App\Modules\Financial\Models\FinancialTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialBankReconciliationService
{
    public const ACCOUNT_TYPE_LABELS = [
        'checking' => 'Conta corrente',
        'savings' => 'Poupança',
        'payment' => 'Conta de pagamento',
        'cash' => 'Caixa físico',
        'other' => 'Outra conta',
    ];

    public const STATUS_LABELS = [
        'all' => 'Todos os lançamentos',
        'pending' => 'Pendentes',
        'reconciled' => 'Conciliados',
    ];

    public const GROUP_KIND_LABELS = [
        'one_to_one' => '1 extrato ↔ 1 lançamento',
        'one_to_many' => '1 extrato ↔ vários lançamentos',
        'many_to_one' => 'Vários extratos ↔ 1 lançamento',
        'net_settlement' => 'Liquidação líquida com tarifa',
    ];

    public function __construct(
        private readonly FinancialStatementParser $parser,
        private readonly AuditTrailService $audit,
    ) {}

    /** @return Collection<int, FinancialAccount> */
    public function accounts(int $clinicId): Collection
    {
        return FinancialAccount::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createAccount(int $clinicId, User $actor, array $data): FinancialAccount
    {
        $this->assertUniqueAccountName($clinicId, trim((string) $data['name']));
        $account = FinancialAccount::query()->withoutGlobalScope('clinic_tenant')->create([
            'clinic_id' => $clinicId,
            'name' => trim((string) $data['name']),
            'institution' => filled($data['institution'] ?? null) ? trim((string) $data['institution']) : null,
            'type' => $data['type'],
            'account_number_last4' => filled($data['account_number_last4'] ?? null)
                ? (string) $data['account_number_last4']
                : null,
            'active' => true,
        ]);

        $this->audit->record(
            'financial.bank-account.created',
            $account,
            [],
            ['name' => $account->name, 'type' => $account->type, 'active' => true],
            $actor,
            [],
            $clinicId,
            $account->name,
        );

        return $account;
    }

    /** @param array<string, mixed> $data */
    public function updateAccount(int $clinicId, int $accountId, User $actor, array $data): FinancialAccount
    {
        $account = $this->account($clinicId, $accountId);
        $name = trim((string) $data['name']);
        $this->assertUniqueAccountName($clinicId, $name, $account->id);
        $before = $account->only(['name', 'institution', 'type', 'account_number_last4', 'active']);
        $account->update([
            'name' => $name,
            'institution' => filled($data['institution'] ?? null) ? trim((string) $data['institution']) : null,
            'type' => $data['type'],
            'account_number_last4' => filled($data['account_number_last4'] ?? null)
                ? (string) $data['account_number_last4']
                : null,
            'active' => (bool) ($data['active'] ?? false),
        ]);

        $this->audit->record(
            'financial.bank-account.updated',
            $account,
            $before,
            $account->only(['name', 'institution', 'type', 'account_number_last4', 'active']),
            $actor,
            [],
            $clinicId,
            $account->name,
        );

        return $account;
    }

    /**
     * @return array{import: FinancialStatementImport, imported: int, duplicates: int}
     */
    public function importStatement(int $clinicId, int $accountId, User $actor, UploadedFile $file): array
    {
        $account = $this->account($clinicId, $accountId);
        abort_unless($account->active, 422, 'A conta selecionada está inativa.');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $duplicateFile = FinancialStatementImport::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->where('financial_account_id', $account->id)
            ->where('file_hash', $fileHash)
            ->exists();
        if ($duplicateFile) {
            throw ValidationException::withMessages(['statement' => 'Este mesmo arquivo já foi importado para a conta selecionada.']);
        }

        $parsed = $this->parser->parse($file);

        return DB::transaction(function () use ($clinicId, $account, $actor, $file, $fileHash, $parsed): array {
            $statementImport = FinancialStatementImport::query()->withoutGlobalScope('clinic_tenant')->create([
                'clinic_id' => $clinicId,
                'financial_account_id' => $account->id,
                'imported_by_user_id' => $actor->id,
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'format' => $parsed['format'],
                'file_hash' => $fileHash,
            ]);
            $imported = 0;
            $duplicates = 0;
            $dates = [];

            foreach ($parsed['rows'] as $row) {
                $fingerprint = $this->fingerprint($account->id, $row);
                $exists = FinancialStatementEntry::query()
                    ->withoutGlobalScope('clinic_tenant')
                    ->where('financial_account_id', $account->id)
                    ->where('fingerprint', $fingerprint)
                    ->exists();
                if ($exists) {
                    $duplicates++;

                    continue;
                }

                FinancialStatementEntry::query()->withoutGlobalScope('clinic_tenant')->create([
                    'clinic_id' => $clinicId,
                    'financial_account_id' => $account->id,
                    'financial_statement_import_id' => $statementImport->id,
                    'posted_at' => $row['posted_at'],
                    'description' => $row['description'],
                    'amount' => $this->decimalFromCents($row['amount_cents']),
                    'external_id' => $row['external_id'],
                    'fingerprint' => $fingerprint,
                ]);
                $dates[] = $row['posted_at'];
                $imported++;
            }

            $statementImport->update([
                'period_start' => $dates === [] ? null : min($dates),
                'period_end' => $dates === [] ? null : max($dates),
                'imported_rows' => $imported,
                'duplicate_rows' => $duplicates,
            ]);

            $this->audit->record(
                'financial.bank-statement.imported',
                $statementImport,
                [],
                [],
                $actor,
                [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'format' => $parsed['format'],
                    'imported_rows' => $imported,
                    'duplicate_rows' => $duplicates,
                    'period_start' => $statementImport->period_start?->format('Y-m-d'),
                    'period_end' => $statementImport->period_end?->format('Y-m-d'),
                ],
                $clinicId,
                'Extrato · '.$account->name,
            );

            return ['import' => $statementImport, 'imported' => $imported, 'duplicates' => $duplicates];
        });
    }

    /**
     * @return array{
     *   filters: array{clinic_id: int, account_id: ?int, from: string, to: string, status: string},
     *   accounts: Collection<int, FinancialAccount>, selected_account: ?FinancialAccount,
     *   entries: ?LengthAwarePaginator, stats: array<string, int>, imports: Collection<int, FinancialStatementImport>,
     *   group_entry_options: Collection<int, FinancialStatementEntry>,
     *   group_transaction_options: Collection<int, FinancialTransaction>
     * }
     */
    public function report(int $clinicId, ?int $accountId, ?string $from, ?string $to, ?string $status): array
    {
        $accounts = $this->accounts($clinicId);
        $selectedAccount = $accountId
            ? $accounts->firstWhere('id', $accountId)
            : $accounts->firstWhere('active', true);
        abort_if($accountId && ! $selectedAccount, 404, 'Conta financeira não encontrada.');

        $filters = $this->normalizeFilters($clinicId, $selectedAccount?->id, $from, $to, $status);
        if (! $selectedAccount) {
            return [
                'filters' => $filters,
                'accounts' => $accounts,
                'selected_account' => null,
                'entries' => null,
                'stats' => $this->emptyStats(),
                'imports' => collect(),
                'group_entry_options' => collect(),
                'group_transaction_options' => collect(),
            ];
        }

        $base = $this->entryQuery($filters);
        $allRows = (clone $base)->with('activeReconciliations')->get();
        $filtered = $this->applyStatus((clone $base), $filters['status']);
        $entries = $filtered
            ->with([
                'activeReconciliations.financialTransaction.category',
                'activeReconciliations.reconciledBy',
                'latestReconciliation.reversedBy',
                'statementImport',
            ])
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $this->attachSuggestions($clinicId, $entries, $filters);

        return [
            'filters' => $filters,
            'accounts' => $accounts,
            'selected_account' => $selectedAccount,
            'entries' => $entries,
            'stats' => $this->stats($allRows),
            'imports' => FinancialStatementImport::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->where('financial_account_id', $selectedAccount->id)
                ->with('importedBy')
                ->latest('id')
                ->limit(8)
                ->get(),
            'group_entry_options' => $this->groupEntryOptions($base),
            'group_transaction_options' => $this->groupTransactionOptions($clinicId, $filters),
        ];
    }

    /** @param array<string, mixed> $data */
    public function reconcile(int $clinicId, User $actor, array $data): FinancialReconciliation
    {
        return DB::transaction(function () use ($clinicId, $actor, $data): FinancialReconciliation {
            $entry = FinancialStatementEntry::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->where('financial_account_id', (int) $data['account_id'])
                ->lockForUpdate()
                ->findOrFail((int) $data['entry_id']);
            $transaction = FinancialTransaction::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->lockForUpdate()
                ->findOrFail((int) $data['transaction_id']);

            if ($transaction->status !== 'paid' || ! $transaction->paid_at) {
                throw ValidationException::withMessages(['transaction_id' => 'Somente lançamentos pagos ou recebidos podem ser conciliados.']);
            }

            $entryType = $this->entryType($entry);
            if ($transaction->type !== $entryType || $this->moneyCents($transaction->amount) !== abs($this->moneyCents($entry->amount))) {
                throw ValidationException::withMessages(['transaction_id' => 'A conciliação exige o mesmo tipo e valor entre o extrato e o livro financeiro.']);
            }

            $entryBusy = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->where('financial_statement_entry_id', $entry->id)
                ->where('status', 'confirmed')
                ->exists();
            $transactionBusy = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->where('financial_transaction_id', $transaction->id)
                ->where('status', 'confirmed')
                ->exists();
            if ($entryBusy || $transactionBusy) {
                throw ValidationException::withMessages(['transaction_id' => 'O lançamento do extrato ou do livro já possui uma conciliação ativa.']);
            }

            $distance = abs($entry->posted_at->diffInDays(CarbonImmutable::parse($transaction->paid_at)));
            if ($distance > 7 && blank($data['note'] ?? null)) {
                throw ValidationException::withMessages([
                    'note' => 'Explique a conferência manual quando as datas estiverem separadas por mais de 7 dias.',
                ]);
            }

            $reconciliation = FinancialReconciliation::query()->withoutGlobalScope('clinic_tenant')->create([
                'clinic_id' => $clinicId,
                'financial_statement_entry_id' => $entry->id,
                'financial_transaction_id' => $transaction->id,
                'reconciled_by_user_id' => $actor->id,
                'match_kind' => $distance <= 7 ? 'suggested' : 'manual',
                'status' => 'confirmed',
                'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
                'reconciled_at' => now(),
            ]);

            $this->audit->record(
                'financial.bank-reconciliation.confirmed',
                $reconciliation,
                [],
                [],
                $actor,
                [
                    'account_id' => $entry->financial_account_id,
                    'statement_entry_id' => $entry->id,
                    'financial_transaction_id' => $transaction->id,
                    'amount_cents' => abs($this->moneyCents($entry->amount)),
                    'date_distance_days' => $distance,
                ],
                $clinicId,
                'Conciliação bancária #'.$reconciliation->id,
            );

            return $reconciliation;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, FinancialReconciliation>
     */
    public function reconcileGroup(int $clinicId, User $actor, array $data): Collection
    {
        return DB::transaction(function () use ($clinicId, $actor, $data): Collection {
            $entryIds = collect($data['entry_ids'])
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $transactionIds = collect($data['transaction_ids'])
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $groupKind = (string) $data['group_kind'];
            $this->assertGroupShape($groupKind, $entryIds->count(), $transactionIds->count());

            $entries = FinancialStatementEntry::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->where('financial_account_id', (int) $data['account_id'])
                ->whereIn('id', $entryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($entries->count() !== $entryIds->count()) {
                throw ValidationException::withMessages([
                    'entry_ids' => 'Um ou mais lançamentos do extrato não pertencem à conta e ao estabelecimento selecionados.',
                ]);
            }

            $transactions = FinancialTransaction::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $transactionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($transactions->count() !== $transactionIds->count()) {
                throw ValidationException::withMessages([
                    'transaction_ids' => 'Um ou mais lançamentos VetFlow não pertencem ao estabelecimento selecionado.',
                ]);
            }
            if ($transactions->contains(fn (FinancialTransaction $transaction): bool => $transaction->status !== 'paid' || ! $transaction->paid_at)) {
                throw ValidationException::withMessages([
                    'transaction_ids' => 'Todos os lançamentos VetFlow do grupo devem estar pagos ou recebidos.',
                ]);
            }

            $entryBusy = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->whereIn('financial_statement_entry_id', $entryIds)
                ->where('status', 'confirmed')
                ->exists();
            $transactionBusy = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->whereIn('financial_transaction_id', $transactionIds)
                ->where('status', 'confirmed')
                ->exists();
            if ($entryBusy || $transactionBusy) {
                throw ValidationException::withMessages([
                    'transaction_ids' => 'Um item selecionado já participa de outra conciliação ativa.',
                ]);
            }

            $statementCents = $entries->sum(fn (FinancialStatementEntry $entry): int => $this->moneyCents($entry->amount));
            $transactionCents = $transactions->sum(fn (FinancialTransaction $transaction): int => $this->transactionSignedCents($transaction));
            $validComposition = match ($groupKind) {
                'one_to_many' => $transactions->every(
                    fn (FinancialTransaction $transaction): bool => $transaction->type === $this->entryType($entries->first())
                ),
                'many_to_one' => $entries->every(
                    fn (FinancialStatementEntry $entry): bool => $this->entryType($entry) === $transactions->first()->type
                ),
                'net_settlement' => $transactions->pluck('type')->unique()->sort()->values()->all() === ['expense', 'income'],
                default => false,
            };
            if (! $validComposition || $statementCents !== $transactionCents) {
                throw ValidationException::withMessages([
                    'transaction_ids' => 'A composição deve respeitar o formato escolhido e fechar exatamente o valor líquido do extrato.',
                ]);
            }

            $dateDistances = $transactions->map(function (FinancialTransaction $transaction) use ($entries): int {
                return (int) $entries->min(fn (FinancialStatementEntry $entry): int => (int) abs(
                    $entry->posted_at->diffInDays(CarbonImmutable::parse($transaction->paid_at))
                ));
            });
            $maxDateDistance = (int) $dateDistances->max();
            $groupUuid = (string) Str::uuid();
            $reconciledAt = now();
            $note = trim((string) $data['note']);
            $pairs = match ($groupKind) {
                'many_to_one' => $entries->map(fn (FinancialStatementEntry $entry): array => [$entry, $transactions->first()]),
                default => $transactions->map(fn (FinancialTransaction $transaction): array => [$entries->first(), $transaction]),
            };

            $reconciliations = $pairs->map(function (array $pair) use (
                $clinicId,
                $actor,
                $groupUuid,
                $groupKind,
                $note,
                $reconciledAt,
            ): FinancialReconciliation {
                [$entry, $transaction] = $pair;

                return FinancialReconciliation::query()->withoutGlobalScope('clinic_tenant')->create([
                    'clinic_id' => $clinicId,
                    'group_uuid' => $groupUuid,
                    'financial_statement_entry_id' => $entry->id,
                    'financial_transaction_id' => $transaction->id,
                    'reconciled_by_user_id' => $actor->id,
                    'match_kind' => 'grouped',
                    'group_kind' => $groupKind,
                    'status' => 'confirmed',
                    'note' => $note,
                    'reconciled_at' => $reconciledAt,
                ]);
            })->values();

            $first = $reconciliations->first();
            $this->audit->record(
                'financial.bank-reconciliation.group-confirmed',
                $first,
                [],
                [],
                $actor,
                [
                    'account_id' => (int) $data['account_id'],
                    'group_uuid' => $groupUuid,
                    'group_kind' => $groupKind,
                    'statement_entry_ids' => $entryIds->all(),
                    'financial_transaction_ids' => $transactionIds->all(),
                    'statement_cents' => $statementCents,
                    'transaction_cents' => $transactionCents,
                    'max_date_distance_days' => $maxDateDistance,
                    'member_count' => $reconciliations->count(),
                ],
                $clinicId,
                'Grupo de conciliação '.substr($groupUuid, 0, 8),
            );

            return $reconciliations;
        });
    }

    public function reverse(int $clinicId, int $reconciliationId, User $actor, string $reason): FinancialReconciliation
    {
        return DB::transaction(function () use ($clinicId, $reconciliationId, $actor, $reason): FinancialReconciliation {
            $reconciliation = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->lockForUpdate()
                ->findOrFail($reconciliationId);
            if ($reconciliation->status !== 'confirmed') {
                throw ValidationException::withMessages(['reason' => 'Esta conciliação já foi desfeita.']);
            }

            $members = FinancialReconciliation::query()
                ->withoutGlobalScope('clinic_tenant')
                ->where('clinic_id', $clinicId)
                ->when(
                    filled($reconciliation->group_uuid),
                    fn (Builder $query): Builder => $query->where('group_uuid', $reconciliation->group_uuid),
                    fn (Builder $query): Builder => $query->whereKey($reconciliation->id),
                )
                ->lockForUpdate()
                ->get();
            if ($members->contains(fn (FinancialReconciliation $member): bool => $member->status !== 'confirmed')) {
                throw ValidationException::withMessages(['reason' => 'Este grupo já foi desfeito ou está inconsistente.']);
            }

            $changes = [
                'status' => 'reversed',
                'reversed_by_user_id' => $actor->id,
                'reversal_reason' => trim($reason),
                'reversed_at' => now(),
            ];
            foreach ($members as $member) {
                $member->update($changes);
            }

            $this->audit->record(
                'financial.bank-reconciliation.reversed',
                $reconciliation,
                ['status' => 'confirmed'],
                ['status' => 'reversed'],
                $actor,
                [
                    'reason' => trim($reason),
                    'group_uuid' => $reconciliation->group_uuid,
                    'group_kind' => $reconciliation->group_kind ?? 'one_to_one',
                    'member_count' => $members->count(),
                ],
                $clinicId,
                filled($reconciliation->group_uuid)
                    ? 'Grupo de conciliação '.substr((string) $reconciliation->group_uuid, 0, 8)
                    : 'Conciliação bancária #'.$reconciliation->id,
            );

            return $reconciliation->fresh();
        });
    }

    /** @return array{filters: array<string, mixed>, account: FinancialAccount, rows: Collection<int, FinancialStatementEntry>} */
    public function exportRows(int $clinicId, int $accountId, ?string $from, ?string $to, ?string $status): array
    {
        $account = $this->account($clinicId, $accountId);
        $filters = $this->normalizeFilters($clinicId, $account->id, $from, $to, $status);

        return [
            'filters' => $filters,
            'account' => $account,
            'rows' => $this->applyStatus($this->entryQuery($filters), $filters['status'])
                ->with([
                    'activeReconciliations.financialTransaction.category',
                    'activeReconciliations.reconciledBy',
                    'latestReconciliation.reversedBy',
                ])
                ->orderByDesc('posted_at')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    private function account(int $clinicId, int $accountId): FinancialAccount
    {
        return FinancialAccount::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->findOrFail($accountId);
    }

    private function assertUniqueAccountName(int $clinicId, string $name, ?int $ignoreId = null): void
    {
        $exists = FinancialAccount::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn (Builder $query): Builder => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Já existe uma conta com este nome no estabelecimento.']);
        }
    }

    /** @param array{posted_at: string, description: string, amount_cents: int, external_id: ?string} $row */
    private function fingerprint(int $accountId, array $row): string
    {
        $source = filled($row['external_id'])
            ? 'id|'.mb_strtolower((string) $row['external_id'])
            : implode('|', [
                $row['posted_at'],
                $row['amount_cents'],
                mb_strtolower(trim(preg_replace('/\s+/u', ' ', $row['description']) ?? $row['description'])),
            ]);

        return hash('sha256', $accountId.'|'.$source);
    }

    /** @return array{clinic_id: int, account_id: ?int, from: string, to: string, status: string} */
    private function normalizeFilters(int $clinicId, ?int $accountId, ?string $from, ?string $to, ?string $status): array
    {
        $end = $to ? CarbonImmutable::createFromFormat('Y-m-d', $to) : CarbonImmutable::today();
        $start = $from ? CarbonImmutable::createFromFormat('Y-m-d', $from) : $end->subDays(59);

        return [
            'clinic_id' => $clinicId,
            'account_id' => $accountId,
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'status' => array_key_exists((string) $status, self::STATUS_LABELS) ? (string) $status : 'all',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function entryQuery(array $filters): Builder
    {
        return FinancialStatementEntry::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $filters['clinic_id'])
            ->where('financial_account_id', $filters['account_id'])
            ->whereBetween('posted_at', [$filters['from'], $filters['to']]);
    }

    private function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => $query->whereDoesntHave('reconciliations', fn (Builder $relation): Builder => $relation->where('status', 'confirmed')),
            'reconciled' => $query->whereHas('reconciliations', fn (Builder $relation): Builder => $relation->where('status', 'confirmed')),
            default => $query,
        };
    }

    /** @param Collection<int, FinancialStatementEntry> $rows @return array<string, int> */
    private function stats(Collection $rows): array
    {
        $credits = $rows->filter(fn (FinancialStatementEntry $entry): bool => $this->moneyCents($entry->amount) > 0);
        $debits = $rows->filter(fn (FinancialStatementEntry $entry): bool => $this->moneyCents($entry->amount) < 0);
        $reconciled = $rows->filter(fn (FinancialStatementEntry $entry): bool => $entry->activeReconciliations->isNotEmpty());
        $pending = $rows->filter(fn (FinancialStatementEntry $entry): bool => $entry->activeReconciliations->isEmpty());
        $reconciledCents = $reconciled->sum(fn (FinancialStatementEntry $entry): int => abs($this->moneyCents($entry->amount)));
        $totalMovementCents = $rows->sum(fn (FinancialStatementEntry $entry): int => abs($this->moneyCents($entry->amount)));
        $activeGroups = $reconciled
            ->flatMap(fn (FinancialStatementEntry $entry): Collection => $entry->activeReconciliations)
            ->filter(fn (FinancialReconciliation $match): bool => filled($match->group_uuid))
            ->unique('group_uuid');
        $groupedEntryCount = $reconciled->filter(fn (FinancialStatementEntry $entry): bool => $entry->activeReconciliations
            ->contains(fn (FinancialReconciliation $match): bool => ($match->group_kind ?? 'one_to_one') !== 'one_to_one'))
            ->count();
        $today = CarbonImmutable::today();
        $pendingAge = $pending->map(fn (FinancialStatementEntry $entry): int => $entry->posted_at->isFuture()
            ? 0
            : (int) $entry->posted_at->diffInDays($today));

        return [
            'entries_count' => $rows->count(),
            'credits_cents' => $credits->sum(fn (FinancialStatementEntry $entry): int => $this->moneyCents($entry->amount)),
            'debits_cents' => abs($debits->sum(fn (FinancialStatementEntry $entry): int => $this->moneyCents($entry->amount))),
            'net_cents' => $rows->sum(fn (FinancialStatementEntry $entry): int => $this->moneyCents($entry->amount)),
            'reconciled_count' => $reconciled->count(),
            'reconciled_group_count' => $activeGroups->count(),
            'grouped_entry_count' => $groupedEntryCount,
            'pending_count' => $pending->count(),
            'pending_recent_count' => $pendingAge->filter(fn (int $days): bool => $days <= 7)->count(),
            'pending_attention_count' => $pendingAge->filter(fn (int $days): bool => $days >= 8 && $days <= 30)->count(),
            'pending_critical_count' => $pendingAge->filter(fn (int $days): bool => $days > 30)->count(),
            'reconciled_cents' => $reconciledCents,
            'total_movement_cents' => $totalMovementCents,
            'reconciled_count_basis_points' => $this->coverageBasisPoints($reconciled->count(), $rows->count()),
            'reconciled_value_basis_points' => $this->coverageBasisPoints($reconciledCents, $totalMovementCents),
        ];
    }

    /** @return array<string, int> */
    private function emptyStats(): array
    {
        return [
            'entries_count' => 0,
            'credits_cents' => 0,
            'debits_cents' => 0,
            'net_cents' => 0,
            'reconciled_count' => 0,
            'reconciled_group_count' => 0,
            'grouped_entry_count' => 0,
            'pending_count' => 0,
            'pending_recent_count' => 0,
            'pending_attention_count' => 0,
            'pending_critical_count' => 0,
            'reconciled_cents' => 0,
            'total_movement_cents' => 0,
            'reconciled_count_basis_points' => 0,
            'reconciled_value_basis_points' => 0,
        ];
    }

    private function coverageBasisPoints(int $covered, int $total): int
    {
        return $total > 0 ? (int) round(($covered / $total) * 10000) : 0;
    }

    /** @return Collection<int, FinancialStatementEntry> */
    private function groupEntryOptions(Builder $base): Collection
    {
        return (clone $base)
            ->whereDoesntHave('reconciliations', fn (Builder $query): Builder => $query->where('status', 'confirmed'))
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /** @param array<string, mixed> $filters @return Collection<int, FinancialTransaction> */
    private function groupTransactionOptions(int $clinicId, array $filters): Collection
    {
        return FinancialTransaction::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [
                CarbonImmutable::parse($filters['from'])->subDays(7)->startOfDay(),
                CarbonImmutable::parse($filters['to'])->addDays(7)->endOfDay(),
            ])
            ->whereDoesntHave('bankReconciliations', fn (Builder $query): Builder => $query->where('status', 'confirmed'))
            ->with('category')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get();
    }

    private function assertGroupShape(string $groupKind, int $entryCount, int $transactionCount): void
    {
        $valid = match ($groupKind) {
            'one_to_many' => $entryCount === 1 && $transactionCount >= 2,
            'many_to_one' => $entryCount >= 2 && $transactionCount === 1,
            'net_settlement' => $entryCount === 1 && $transactionCount >= 2,
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'group_kind' => match ($groupKind) {
                    'one_to_many' => 'Selecione exatamente um lançamento bancário e pelo menos dois lançamentos VetFlow.',
                    'many_to_one' => 'Selecione pelo menos dois lançamentos bancários e exatamente um lançamento VetFlow.',
                    'net_settlement' => 'Selecione exatamente um lançamento bancário e pelo menos dois lançamentos VetFlow.',
                    default => 'Escolha um formato de conciliação em grupo válido.',
                },
            ]);
        }
    }

    /** @param array<string, mixed> $filters */
    private function attachSuggestions(int $clinicId, LengthAwarePaginator $entries, array $filters): void
    {
        $pending = $entries->getCollection()
            ->filter(fn (FinancialStatementEntry $entry): bool => $entry->activeReconciliations->isEmpty());
        if ($pending->isEmpty()) {
            return;
        }

        $candidates = FinancialTransaction::query()
            ->withoutGlobalScope('clinic_tenant')
            ->where('clinic_id', $clinicId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [
                CarbonImmutable::parse($filters['from'])->subDays(7)->startOfDay(),
                CarbonImmutable::parse($filters['to'])->addDays(7)->endOfDay(),
            ])
            ->whereDoesntHave('bankReconciliations', fn (Builder $query): Builder => $query->where('status', 'confirmed'))
            ->with('category')
            ->get();

        foreach ($pending as $entry) {
            $expectedType = $this->entryType($entry);
            $amount = abs($this->moneyCents($entry->amount));
            $matches = $candidates
                ->filter(fn (FinancialTransaction $transaction): bool => $transaction->type === $expectedType
                    && $this->moneyCents($transaction->amount) === $amount
                    && abs($entry->posted_at->diffInDays(CarbonImmutable::parse($transaction->paid_at))) <= 7)
                ->sortBy(fn (FinancialTransaction $transaction): string => str_pad(
                    (string) abs($entry->posted_at->diffInDays(CarbonImmutable::parse($transaction->paid_at))),
                    3,
                    '0',
                    STR_PAD_LEFT,
                ).'|'.str_pad((string) $transaction->id, 12, '0', STR_PAD_LEFT))
                ->take(5)
                ->values();
            $entry->setAttribute('suggested_matches', $matches);
        }
    }

    private function entryType(FinancialStatementEntry $entry): string
    {
        return $this->moneyCents($entry->amount) > 0 ? 'income' : 'expense';
    }

    private function transactionSignedCents(FinancialTransaction $transaction): int
    {
        $cents = $this->moneyCents($transaction->amount);

        return $transaction->type === 'income' ? $cents : -$cents;
    }

    private function moneyCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function decimalFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
