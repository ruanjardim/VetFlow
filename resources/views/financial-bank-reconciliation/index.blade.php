@extends('layouts.admin')

@section('title', 'Conciliação bancária - VetFlow')

@section('content')
  @php
    $money = fn (int $cents) => number_format($cents / 100, 2, ',', '.');
    $percentage = fn (int $basisPoints) => number_format($basisPoints / 100, 1, ',', '.').'%';
    $routeFilters = array_filter([
      'clinic_id' => $requiresClinic ? $selectedClinicId : null,
      'account_id' => $filters['account_id'],
      'from' => $filters['from'],
      'to' => $filters['to'],
      'status' => $filters['status'],
    ], fn ($value) => $value !== null && $value !== '');
    $selectedGroupEntries = collect(old('entry_ids', []))->map(fn ($id) => (string) $id);
    $selectedGroupTransactions = collect(old('transaction_ids', []))->map(fn ($id) => (string) $id);
  @endphp

  <header class="topbar">
    <div>
      <h1>Conciliação bancária assistida</h1>
      <p>Compare extratos com lançamentos pagos no VetFlow, sempre com confirmação humana.</p>
    </div>
    <div class="actions">
      @if($selected_account)
        <a class="button secondary" href="{{ route('financial-bank-reconciliation.export', $routeFilters) }}">Exportar CSV</a>
      @endif
      <a class="button secondary" href="{{ route('financial-transactions.cash-flow', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Fluxo de caixa</a>
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Lançamentos</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Conferência controlada.</strong>
    <span>Importar ou sugerir uma correspondência não altera o extrato nem o lançamento financeiro. Apenas uma confirmação humana registra a conciliação.</span>
  </div>

  <section class="content-grid">
    <div class="panel panel-body">
      <h2>Nova conta financeira</h2>
      <form method="POST" action="{{ route('financial-bank-reconciliation.accounts.store') }}" class="stacked-form">
        @csrf
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        <div>
          <label for="bank_account_name">Nome de identificação</label>
          <input id="bank_account_name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Ex.: Conta principal" required>
        </div>
        <div>
          <label for="bank_account_institution">Instituição</label>
          <input id="bank_account_institution" name="institution" value="{{ old('institution') }}" maxlength="100" placeholder="Ex.: Banco do Brasil">
        </div>
        <div>
          <label for="bank_account_type">Tipo</label>
          <select id="bank_account_type" name="type" required>
            @foreach($accountTypeLabels as $value => $label)
              <option value="{{ $value }}" @selected(old('type', 'checking') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="bank_account_last4">Últimos 4 dígitos</label>
          <input id="bank_account_last4" name="account_number_last4" value="{{ old('account_number_last4') }}" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="1234">
        </div>
        <button type="submit">Cadastrar conta</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Contas cadastradas</h2>
          <p>Contas inativas preservam todo o histórico e não aceitam novos extratos.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Conta</th><th>Tipo</th><th>Status</th><th>Editar</th></tr></thead>
          <tbody>
            @forelse($accounts as $account)
              <tr>
                <td>
                  <strong>{{ $account->name }}</strong>
                  <div class="muted">{{ $account->institution ?: 'Instituição não informada' }}{{ $account->account_number_last4 ? ' · final '.$account->account_number_last4 : '' }}</div>
                </td>
                <td>{{ $accountTypeLabels[$account->type] ?? $account->type }}</td>
                <td><span class="badge {{ $account->active ? 'success' : '' }}">{{ $account->active ? 'Ativa' : 'Inativa' }}</span></td>
                <td>
                  <details>
                    <summary class="button secondary">Editar</summary>
                    <form method="POST" action="{{ route('financial-bank-reconciliation.accounts.update', $account->id) }}" class="stacked-form panel-body">
                      @csrf
                      @method('PUT')
                      @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
                      <label>Nome<input name="name" value="{{ $account->name }}" maxlength="100" required></label>
                      <label>Instituição<input name="institution" value="{{ $account->institution }}" maxlength="100"></label>
                      <label>Tipo
                        <select name="type">
                          @foreach($accountTypeLabels as $value => $label)<option value="{{ $value }}" @selected($account->type === $value)>{{ $label }}</option>@endforeach
                        </select>
                      </label>
                      <label>Últimos 4 dígitos<input name="account_number_last4" value="{{ $account->account_number_last4 }}" pattern="[0-9]{4}" maxlength="4"></label>
                      <label class="checkbox-row"><input type="checkbox" name="active" value="1" @checked($account->active)><span>Conta ativa</span></label>
                      <button type="submit">Salvar conta</button>
                    </form>
                  </details>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="muted">Nenhuma conta financeira cadastrada.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  @if($accounts->isNotEmpty())
    <form method="GET" action="{{ route('financial-bank-reconciliation.index') }}" class="panel panel-body">
      <div class="filter-grid compact-filter-grid">
        @if($requiresClinic)
          <div>
            <label for="reconciliation_clinic">Estabelecimento</label>
            <select id="reconciliation_clinic" name="clinic_id" required>
              @foreach($clinics as $clinic)
                <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div>
          <label for="reconciliation_account">Conta</label>
          <select id="reconciliation_account" name="account_id" required>
            @foreach($accounts as $account)
              <option value="{{ $account->id }}" @selected($filters['account_id'] === $account->id)>{{ $account->name }}{{ $account->active ? '' : ' · inativa' }}</option>
            @endforeach
          </select>
        </div>
        <div><label for="reconciliation_from">De</label><input id="reconciliation_from" name="from" type="date" value="{{ $filters['from'] }}" required></div>
        <div><label for="reconciliation_to">Até</label><input id="reconciliation_to" name="to" type="date" value="{{ $filters['to'] }}" required></div>
        <div>
          <label for="reconciliation_status">Situação</label>
          <select id="reconciliation_status" name="status">
            @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach
          </select>
        </div>
        <div class="filter-actions"><button type="submit">Atualizar visão</button></div>
      </div>
    </form>
  @endif

  @if($selected_account)
    <section class="content-grid">
      <div class="panel panel-body">
        <h2>Importar extrato</h2>
        <p class="muted">Aceita CSV/TXT com colunas data, descrição e valor, ou OFX. Limite de 5 MB e 5.000 linhas. O arquivo não é armazenado.</p>
        @if($selected_account->active)
          <form method="POST" action="{{ route('financial-bank-reconciliation.imports.store') }}" enctype="multipart/form-data" class="stacked-form">
            @csrf
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <input type="hidden" name="account_id" value="{{ $selected_account->id }}">
            <div><label for="statement_file">Arquivo do extrato</label><input id="statement_file" type="file" name="statement" accept=".csv,.txt,.ofx" required></div>
            <button type="submit">Importar e verificar duplicidades</button>
          </form>
        @else
          <div class="alert warning">Ative a conta para importar novos extratos.</div>
        @endif
      </div>

      <div class="panel">
        <div class="panel-heading"><div><h2>Últimas importações</h2><p>Resumo permanente, sem retenção do arquivo original.</p></div></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Data</th><th>Arquivo</th><th>Período</th><th>Resultado</th><th>Responsável</th></tr></thead>
            <tbody>
              @forelse($imports as $import)
                <tr>
                  <td>{{ $import->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $import->original_filename }} <span class="badge">{{ strtoupper($import->format) }}</span></td>
                  <td>{{ $import->period_start?->format('d/m/Y') ?: '-' }} a {{ $import->period_end?->format('d/m/Y') ?: '-' }}</td>
                  <td>{{ $import->imported_rows }} novo(s) · {{ $import->duplicate_rows }} ignorado(s)</td>
                  <td>{{ $import->importedBy?->name ?: 'Sistema' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="muted">Nenhum extrato importado para esta conta.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>

    @if($group_entry_options->isNotEmpty() && $group_transaction_options->isNotEmpty())
      <section class="panel panel-body">
        <details @if(old('group_kind')) open @endif>
          <summary><strong>Conciliação em grupo</strong> <span class="muted">— composição exata e confirmação humana</span></summary>
          <div class="alert info" style="margin-top: 1rem;">
            <strong>Sem baixa automática.</strong>
            <span>Os lançamentos selecionados precisam estar pagos, livres de outra conciliação e fechar exatamente em centavos. A operação apenas registra o vínculo e a justificativa.</span>
          </div>
          <form method="POST" action="{{ route('financial-bank-reconciliation.reconciliation-groups.store') }}" class="stacked-form">
            @csrf
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <input type="hidden" name="account_id" value="{{ $selected_account->id }}">
            <input type="hidden" name="from" value="{{ $filters['from'] }}"><input type="hidden" name="to" value="{{ $filters['to'] }}"><input type="hidden" name="status" value="{{ $filters['status'] }}">
            <div>
              <label for="reconciliation_group_kind">Formato da composição</label>
              <select id="reconciliation_group_kind" name="group_kind" required>
                <option value="one_to_many" @selected(old('group_kind') === 'one_to_many')>1 extrato ↔ vários lançamentos do mesmo tipo</option>
                <option value="many_to_one" @selected(old('group_kind') === 'many_to_one')>Vários extratos do mesmo tipo ↔ 1 lançamento</option>
                <option value="net_settlement" @selected(old('group_kind') === 'net_settlement')>1 extrato ↔ receitas menos despesas existentes</option>
              </select>
              <small class="muted">Liquidação líquida exige pelo menos uma receita e uma despesa, por exemplo uma tarifa já registrada.</small>
            </div>
            <div class="content-grid">
              <div>
                <label for="reconciliation_group_entries">Lançamentos do extrato</label>
                <select id="reconciliation_group_entries" name="entry_ids[]" multiple size="8" required>
                  @foreach($group_entry_options as $option)
                    <option value="{{ $option->id }}" @selected($selectedGroupEntries->contains((string) $option->id))>
                      #{{ $option->id }} · {{ $option->posted_at->format('d/m/Y') }} · {{ $option->description }} · R$ {{ number_format((float) $option->amount, 2, ',', '.') }}
                    </option>
                  @endforeach
                </select>
                <small class="muted">Use Ctrl ou Shift para selecionar mais de um item.</small>
              </div>
              <div>
                <label for="reconciliation_group_transactions">Lançamentos VetFlow</label>
                <select id="reconciliation_group_transactions" name="transaction_ids[]" multiple size="8" required>
                  @foreach($group_transaction_options as $option)
                    <option value="{{ $option->id }}" @selected($selectedGroupTransactions->contains((string) $option->id))>
                      #{{ $option->id }} · {{ $option->paid_at->format('d/m/Y') }} · {{ $option->type === 'income' ? 'Receita' : 'Despesa' }} · {{ $option->description }} · R$ {{ number_format((float) $option->amount, 2, ',', '.') }}
                    </option>
                  @endforeach
                </select>
                <small class="muted">São mostrados somente itens pagos e ainda não conciliados no período ampliado em sete dias.</small>
              </div>
            </div>
            <div>
              <label for="reconciliation_group_note">Justificativa da composição</label>
              <textarea id="reconciliation_group_note" name="note" minlength="10" maxlength="500" required placeholder="Explique a origem do agrupamento ou da tarifa líquida.">{{ old('note') }}</textarea>
            </div>
            <button type="submit">Confirmar grupo conciliado</button>
          </form>
        </details>
      </section>
    @endif

    <section class="grid stats">
      <div class="stat"><span>Créditos no extrato</span><strong>R$ {{ $money($stats['credits_cents']) }}</strong></div>
      <div class="stat"><span>Débitos no extrato</span><strong>R$ {{ $money($stats['debits_cents']) }}</strong></div>
      <div class="stat"><span>Movimento líquido</span><strong>R$ {{ $money($stats['net_cents']) }}</strong><small>Não é saldo de abertura da conta.</small></div>
      <div class="stat"><span>Cobertura conciliada</span><strong>{{ $percentage($stats['reconciled_count_basis_points']) }}</strong><small>{{ $stats['reconciled_count'] }}/{{ $stats['entries_count'] }} lançamentos · {{ $percentage($stats['reconciled_value_basis_points']) }} do valor · {{ $stats['reconciled_group_count'] }} grupo(s), {{ $stats['grouped_entry_count'] }} item(ns) em composição</small></div>
      <div class="stat{{ $stats['pending_count'] > 0 ? ' stat--alert' : '' }}"><span>Pendentes</span><strong>{{ $stats['pending_count'] }}</strong><small>{{ $stats['pending_recent_count'] }} até 7 dias · {{ $stats['pending_attention_count'] }} de 8 a 30 · {{ $stats['pending_critical_count'] }} acima de 30</small></div>
    </section>

    <section class="panel">
      <div class="panel-heading">
        <div><h2>Lançamentos do extrato</h2><p>{{ $selected_account->name }} · {{ $statusLabels[$filters['status']] }} · {{ \Carbon\CarbonImmutable::parse($filters['from'])->format('d/m/Y') }} a {{ \Carbon\CarbonImmutable::parse($filters['to'])->format('d/m/Y') }}</p></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Data</th><th>Descrição no banco</th><th>Valor</th><th>Situação</th><th>Correspondência no VetFlow</th><th>Ação</th></tr></thead>
          <tbody>
            @forelse($entries as $entry)
              @php
                $matches = $entry->activeReconciliations;
                $match = $matches->first();
                $latest = $entry->latestReconciliation;
                $suggestions = collect($entry->getAttribute('suggested_matches'));
                $groupKind = $match?->group_kind ?? 'one_to_one';
                $groupTransactions = $matches->map(fn ($item) => $item->financialTransaction)->filter()->unique('id')->values();
              @endphp
              <tr>
                <td>{{ $entry->posted_at->format('d/m/Y') }}</td>
                <td><strong>{{ $entry->description }}</strong><div class="muted">{{ $entry->external_id ? 'ID '.$entry->external_id : 'Identificador derivado do conteúdo' }}</div></td>
                <td><strong>R$ {{ number_format((float) $entry->amount, 2, ',', '.') }}</strong><div class="muted">{{ (float) $entry->amount > 0 ? 'Crédito' : 'Débito' }}</div></td>
                <td><span class="badge {{ $match ? 'success' : 'warning' }}">{{ $match ? 'Conciliado' : 'Pendente' }}</span></td>
                <td>
                  @if($match)
                    <span class="badge">{{ $groupKindLabels[$groupKind] ?? $groupKind }}</span>
                    @if($match->group_uuid)<div class="muted">Grupo {{ substr($match->group_uuid, 0, 8) }}</div>@endif
                    @foreach($groupTransactions as $groupTransaction)
                      <div style="margin-top: .5rem;">
                        <strong>#{{ $groupTransaction->id }} · {{ $groupTransaction->description }}</strong>
                        <div class="muted">{{ $groupTransaction->type === 'income' ? 'Receita' : 'Despesa' }} · {{ $groupTransaction->category?->name ?: 'Sem categoria' }} · pago em {{ $groupTransaction->paid_at->format('d/m/Y') }} · R$ {{ number_format((float) $groupTransaction->amount, 2, ',', '.') }}</div>
                      </div>
                    @endforeach
                    <div class="muted">Confirmado por {{ $match->reconciledBy?->name ?: 'Sistema' }} em {{ $match->reconciled_at->format('d/m/Y H:i') }}</div>
                  @elseif($suggestions->isNotEmpty())
                    @if($suggestions->count() > 1)
                      <div class="alert warning"><strong>{{ $suggestions->count() }} correspondências possíveis.</strong> Confira a origem antes de escolher; o VetFlow não seleciona automaticamente em caso de ambiguidade.</div>
                    @endif
                    <form method="POST" action="{{ route('financial-bank-reconciliation.reconciliations.store') }}" class="stacked-form">
                      @csrf
                      @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
                      <input type="hidden" name="account_id" value="{{ $selected_account->id }}">
                      <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                      <input type="hidden" name="from" value="{{ $filters['from'] }}"><input type="hidden" name="to" value="{{ $filters['to'] }}"><input type="hidden" name="status" value="{{ $filters['status'] }}">
                      <label>Possíveis correspondências
                        <select name="transaction_id" required>
                          @if($suggestions->count() > 1)<option value="">Escolha após conferir</option>@endif
                          @foreach($suggestions as $suggestion)
                            <option value="{{ $suggestion->id }}">{{ $suggestion->paid_at->format('d/m/Y') }} · {{ $suggestion->description }} · R$ {{ number_format((float) $suggestion->amount, 2, ',', '.') }}</option>
                          @endforeach
                        </select>
                      </label>
                      <input name="note" maxlength="500" placeholder="Observação opcional">
                      <button type="submit">Confirmar correspondência</button>
                    </form>
                  @else
                    <p class="muted">Nenhum lançamento pago com mesmo tipo e valor em até 7 dias. Se você já conferiu o livro, informe o ID para uma correspondência manual; o servidor ainda exigirá tipo e valor idênticos.</p>
                    <form method="POST" action="{{ route('financial-bank-reconciliation.reconciliations.store') }}" class="stacked-form">
                      @csrf
                      @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
                      <input type="hidden" name="account_id" value="{{ $selected_account->id }}">
                      <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                      <input type="hidden" name="from" value="{{ $filters['from'] }}"><input type="hidden" name="to" value="{{ $filters['to'] }}"><input type="hidden" name="status" value="{{ $filters['status'] }}">
                      <label>ID do lançamento VetFlow<input type="number" name="transaction_id" min="1" required></label>
                      <input name="note" maxlength="500" placeholder="Motivo ou referência da conferência manual">
                      <button type="submit">Confirmar pelo ID</button>
                    </form>
                  @endif
                  @if(!$match && $latest?->status === 'reversed')
                    <div class="alert warning">
                      <strong>Última conciliação desfeita.</strong>
                      {{ $latest->reversal_reason }}
                      <div class="muted">Por {{ $latest->reversedBy?->name ?: 'Sistema' }} em {{ $latest->reversed_at?->format('d/m/Y H:i') }}.</div>
                    </div>
                  @endif
                </td>
                <td>
                  @if($match)
                    <form method="POST" action="{{ route('financial-bank-reconciliation.reconciliations.reverse', $match->id) }}" class="stacked-form">
                      @csrf
                      @method('PATCH')
                      @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
                      <input type="hidden" name="account_id" value="{{ $selected_account->id }}">
                      <input type="hidden" name="from" value="{{ $filters['from'] }}"><input type="hidden" name="to" value="{{ $filters['to'] }}"><input type="hidden" name="status" value="{{ $filters['status'] }}">
                      <input name="reason" minlength="10" maxlength="500" placeholder="Motivo para desfazer" required>
                      <button class="secondary" type="submit">{{ $groupKind === 'one_to_one' ? 'Desfazer' : 'Desfazer grupo' }}</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="muted">Nenhum lançamento de extrato encontrado para os filtros selecionados.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($entries)<div class="panel-body">{{ $entries->links() }}</div>@endif
    </section>
  @elseif($accounts->isEmpty())
    <div class="panel empty-state">
      <h2>Cadastre a primeira conta financeira</h2>
      <p>Depois disso, importe um extrato CSV ou OFX para iniciar a conferência assistida.</p>
    </div>
  @endif
@endsection
