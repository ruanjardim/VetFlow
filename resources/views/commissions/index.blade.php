@extends('layouts.admin')

@section('title', 'Comissões - VetFlow')

@section('content')
  @php
    $period = $preview['period'];
    $summary = $preview['summary'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $basisLabel = fn ($basis) => $basis === 'gross_profit' ? 'Margem bruta' : 'Valor líquido vendido';
    $recognitionLabel = fn ($recognition) => $recognition === 'receipt_date' ? 'Data do recebimento' : 'Data da venda';
  @endphp

  <header class="topbar">
    <div>
      <h1>Comissões</h1>
      <p>Apuração por vendedor, fechamento imutável e conta a pagar integrada ao Financeiro.</p>
    </div>
    <a class="button" href="{{ route('commissions.create') }}">Nova regra</a>
  </header>

  <div class="alert-soft">
    <strong>Fechamento financeiro controlado</strong>
    <span>Cada venda ou recebimento é fechado uma única vez. Devoluções e mudanças posteriores aparecem como ajuste na próxima apuração, sem reescrever o histórico.</span>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('commissions.index') }}" class="form-grid">
        <div class="field"><label for="from">De</label><input id="from" name="from" type="date" value="{{ $period['from'] }}"></div>
        <div class="field"><label for="to">Até</label><input id="to" name="to" type="date" value="{{ $period['to'] }}"></div>
        <div class="field full"><div class="actions"><button type="submit">Atualizar apuração</button><a class="button secondary" href="{{ route('commissions.index') }}">Mês atual</a></div></div>
      </form>
    </div>
  </div>

  <section class="grid stats">
    <div class="stat"><span>Regras ativas</span><strong>{{ $summary['rules_count'] }}</strong></div>
    <div class="stat"><span>Vendas apuradas</span><strong>{{ $summary['sales_count'] }}</strong></div>
    <div class="stat"><span>Base comissionável</span><strong>{{ $money($summary['base_amount']) }}</strong></div>
    <div class="stat"><span>Comissão projetada</span><strong>{{ $money($summary['commission_amount']) }}</strong></div>
    <div class="stat"><span>Disponível para fechar</span><strong>{{ $money($summary['pending_amount']) }}</strong></div>
  </section>

  @if($summary['adjustments_count'] > 0)
    <div class="alert warning"><strong>{{ $summary['adjustments_count'] }} ajuste(s) identificado(s).</strong> Devoluções, cancelamentos ou mudanças posteriores reduziram comissões já fechadas. Os valores aparecem discriminados abaixo.</div>
  @endif

  <div class="panel">
    <div class="panel-heading"><div><h2>Fechar por vendedor</h2><p>O fechamento reúne todas as regras do vendedor no período e gera uma conta a pagar categorizada como Comissão.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Vendedor</th><th>Regras</th><th>Vendas</th><th>Projetada</th><th>Ajustes</th><th>A fechar</th><th>Fechamento</th></tr></thead>
        <tbody>
          @forelse($preview['sellers'] as $row)
            <tr>
              <td><strong>{{ $row['seller']?->name ?? '-' }}</strong>@if(auth()->user()?->clinic_id === null)<div class="muted">{{ $row['seller']?->clinic?->trade_name ?? $row['seller']?->clinic?->corporate_name ?? '-' }}</div>@endif</td>
              <td>{{ $row['rules_count'] }}</td><td>{{ $row['sales_count'] }}</td><td>{{ $money($row['commission_amount']) }}</td>
              <td>@if($row['adjustments_count'] > 0)<span class="badge danger">{{ $row['adjustments_count'] }}</span>@else - @endif</td>
              <td><strong>{{ $money($row['pending_amount']) }}</strong><div class="muted">{{ $row['pending_count'] }} lançamento(s)</div></td>
              <td>
                @if($row['pending_amount'] > 0 && $row['seller'])
                  <form method="POST" action="{{ route('commissions.settle') }}" class="stacked-form" data-confirm="Fechar {{ $money($row['pending_amount']) }} de {{ $row['seller']->name }} e gerar a conta a pagar?">
                    @csrf
                    <input type="hidden" name="seller_user_id" value="{{ $row['seller']->id }}"><input type="hidden" name="from" value="{{ $period['from'] }}"><input type="hidden" name="to" value="{{ $period['to'] }}">
                    <label class="sr-only" for="due_{{ $row['seller']->id }}">Vencimento</label><input id="due_{{ $row['seller']->id }}" name="due_date" type="date" value="{{ today()->addDays(5)->toDateString() }}" required title="Vencimento da conta a pagar">
                    <label class="sr-only" for="notes_{{ $row['seller']->id }}">Observação</label><input id="notes_{{ $row['seller']->id }}" name="notes" type="text" maxlength="1000" placeholder="Observação opcional">
                    <label class="checkbox-row"><input type="checkbox" name="confirm_settlement" value="1" required><span>Revisei os valores</span></label>
                    <button type="submit">Fechar e gerar conta</button>
                  </form>
                @elseif($row['pending_amount'] < 0)
                  <span class="muted">Saldo apenas de ajuste; aguarde novas comissões.</span>
                @else
                  <span class="muted">Nada novo a fechar.</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma regra ativa para este período.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading"><div><h2>Composição disponível</h2><p>Somente valores ainda não fechados ou ajustes posteriores. A coluna “Já fechado” evita pagamento duplicado.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Data</th><th>Vendedor</th><th>Venda</th><th>Origem</th><th>Regra</th><th>Base atual</th><th>Projetada</th><th>Já fechado</th><th>A fechar / ajustar</th></tr></thead>
        <tbody>
          @forelse($preview['contributions'] as $row)
            <tr>
              <td>{{ \Illuminate\Support\Carbon::parse($row['source_date'])->format('d/m/Y') }}</td><td>{{ $row['rule']->seller?->name ?? '-' }}</td>
              <td><a href="{{ route('sales.edit', $row['sale_id']) }}">{{ $row['sale_code'] }}</a></td><td>{{ $row['source_label'] }}</td>
              <td>{{ $row['rule']->name }}<div class="muted">{{ number_format((float) $row['rule']->percentage, 2, ',', '.') }}%</div></td>
              <td>{{ $money($row['base_amount']) }}</td><td>{{ $money($row['commission_amount']) }}</td><td>{{ $money($row['settled_amount']) }}</td>
              <td><strong>{{ $money($row['pending_amount']) }}</strong>@if($row['pending_amount'] < 0)<div><span class="badge danger">Ajuste</span></div>@endif</td>
            </tr>
          @empty
            <tr><td colspan="9" class="muted">Nenhuma comissão nova ou ajuste pendente neste período.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading"><div><h2>Prévia por regra</h2><p>Valores atuais calculados pelas regras vigentes no período consultado.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Vendedor</th><th>Regra</th><th>Critério</th><th>Vendas</th><th>Base</th><th>Projetada</th><th>Pendente</th></tr></thead><tbody>
      @forelse($preview['rules'] as $row)
        @php($rule = $row['rule'])
        <tr><td><strong>{{ $rule->seller?->name ?? '-' }}</strong></td><td>{{ $rule->name }}<div class="muted">{{ number_format((float) $rule->percentage, 2, ',', '.') }}%</div></td><td>{{ $basisLabel($rule->basis) }}<div class="muted">{{ $recognitionLabel($rule->recognition) }}{{ $rule->requires_paid ? ' · Quitada' : ' · Parcial permitida' }}</div></td><td>{{ $row['sales_count'] }}</td><td>{{ $money($row['base_amount']) }}</td><td><strong>{{ $money($row['commission_amount']) }}</strong></td><td>{{ $money($row['pending_amount']) }}</td></tr>
      @empty
        <tr><td colspan="7" class="muted">Nenhuma regra ativa para este período.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-heading"><div><h2>Fechamentos recentes</h2><p>Histórico imutável ligado à conta a pagar correspondente.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Vendedor</th><th>Período</th><th>Vendas</th><th>Lançamentos</th><th>Total</th><th>Vencimento</th><th>Conta a pagar</th><th>Responsável</th></tr></thead><tbody>
      @forelse($settlements as $settlement)
        <tr>
          <td>{{ $settlement->seller?->name ?? '-' }}</td><td>{{ $settlement->period_start->format('d/m/Y') }} a {{ $settlement->period_end->format('d/m/Y') }}</td><td>{{ $settlement->sales_count }}</td><td>{{ $settlement->entries_count }}</td><td><strong>{{ $money($settlement->total) }}</strong></td><td>{{ $settlement->due_date->format('d/m/Y') }}</td>
          <td>@if($settlement->financialTransaction){{ ['paid' => 'Paga', 'pending' => 'Em aberto', 'cancelled' => 'Cancelada'][$settlement->financialTransaction->status] ?? $settlement->financialTransaction->status }}@if(auth()->user()?->hasPermission('financial.manage'))<div><a href="{{ route('financial-transactions.index', ['financial_category_id' => $settlement->financialTransaction->financial_category_id]) }}">Abrir Financeiro</a></div>@endif @else <span class="badge danger">Vínculo indisponível</span>@endif</td>
          <td>{{ $settlement->createdBy?->name ?? 'Sistema' }}<div class="muted">{{ $settlement->created_at->format('d/m/Y H:i') }}</div></td>
        </tr>
      @empty
        <tr><td colspan="8" class="muted">Nenhum fechamento de comissão de vendas registrado.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>

  <div class="panel nested-panel">
    <div class="panel-heading"><div><h2>Regras cadastradas</h2><p>Uma regra ativa por vendedor em cada período de vigência.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Vendedor</th><th>Regra</th><th>Base</th><th>Vigência</th><th>Status</th><th>Ações</th></tr></thead><tbody>
      @forelse($rules as $rule)
        <tr><td><strong>{{ $rule->seller?->name ?? '-' }}</strong>@if(auth()->user()?->clinic_id === null)<div class="muted">{{ $rule->seller?->clinic?->trade_name ?? $rule->seller?->clinic?->corporate_name ?? '-' }}</div>@endif</td><td>{{ $rule->name }}<div class="muted">{{ number_format((float) $rule->percentage, 2, ',', '.') }}%</div></td><td>{{ $basisLabel($rule->basis) }}<div class="muted">{{ $recognitionLabel($rule->recognition) }}</div></td><td>{{ $rule->starts_on->format('d/m/Y') }}{{ $rule->ends_on ? ' a '.$rule->ends_on->format('d/m/Y') : ' em diante' }}</td><td><span class="badge {{ $rule->active ? 'success' : 'muted-badge' }}">{{ $rule->active ? 'Ativa' : 'Inativa' }}</span></td><td><a class="button secondary" href="{{ route('commissions.edit', $rule->id) }}">Editar</a></td></tr>
      @empty
        <tr><td colspan="6" class="muted">Nenhuma regra cadastrada.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>

  {{ $rules->withQueryString()->links() }}
@endsection
