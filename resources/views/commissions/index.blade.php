@extends('layouts.admin')

@section('title', 'Comissoes - VetFlow')

@section('content')
  @php
    $period = $preview['period'];
    $summary = $preview['summary'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $basisLabel = fn ($basis) => $basis === 'gross_profit' ? 'Margem bruta' : 'Valor liquido vendido';
    $recognitionLabel = fn ($recognition) => $recognition === 'receipt_date' ? 'Data do recebimento' : 'Data da venda';
  @endphp

  <header class="topbar">
    <div>
      <h1>Comissoes</h1>
      <p>Regras e previa de comissoes por vendedor.</p>
    </div>
    <a class="button" href="{{ route('commissions.create') }}">Nova regra</a>
  </header>

  <div class="alert-soft">
    <strong>Controle antes do pagamento</strong>
    <span>Os valores abaixo sao uma previa. O VetFlow ainda nao gera contas a pagar nem baixa comissoes automaticamente.</span>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('commissions.index') }}" class="form-grid">
        <div class="field">
          <label for="from">De</label>
          <input id="from" name="from" type="date" value="{{ $period['from'] }}">
        </div>
        <div class="field">
          <label for="to">Ate</label>
          <input id="to" name="to" type="date" value="{{ $period['to'] }}">
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Atualizar previa</button>
            <a class="button secondary" href="{{ route('commissions.index') }}">Mes atual</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <section class="grid stats">
    <div class="stat"><span>Regras ativas</span><strong>{{ $summary['rules_count'] }}</strong></div>
    <div class="stat"><span>Vendas elegiveis</span><strong>{{ $summary['sales_count'] }}</strong></div>
    <div class="stat"><span>Base comissionavel</span><strong>{{ $money($summary['base_amount']) }}</strong></div>
    <div class="stat"><span>Comissao estimada</span><strong>{{ $money($summary['commission_amount']) }}</strong></div>
  </section>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Previa de {{ $period['label'] }}</h2>
        <p>Aplica as regras ativas ao periodo consultado.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Vendedor</th><th>Regra</th><th>Criterio</th><th>Vendas</th><th>Base</th><th>Estimativa</th></tr>
        </thead>
        <tbody>
          @forelse($preview['rules'] as $row)
            @php($rule = $row['rule'])
            <tr>
              <td>
                <strong>{{ $rule->seller?->name ?? '-' }}</strong>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $rule->seller?->clinic?->trade_name ?? $rule->seller?->clinic?->corporate_name ?? '-' }}</div>
                @endif
              </td>
              <td>{{ $rule->name }}<div class="muted">{{ number_format((float) $rule->percentage, 2, ',', '.') }}%</div></td>
              <td>{{ $basisLabel($rule->basis) }}<div class="muted">{{ $recognitionLabel($rule->recognition) }}{{ $rule->requires_paid ? ' · Quitada' : ' · Parcial permitida' }}</div></td>
              <td>{{ $row['sales_count'] }}</td>
              <td>{{ $money($row['base_amount']) }}</td>
              <td><strong>{{ $money($row['commission_amount']) }}</strong></td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhuma regra ativa para este periodo.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Regras cadastradas</h2>
        <p>Uma regra ativa por vendedor em cada periodo de vigencia.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Vendedor</th><th>Regra</th><th>Base</th><th>Vigencia</th><th>Status</th><th>Acoes</th></tr>
        </thead>
        <tbody>
          @forelse($rules as $rule)
            <tr>
              <td>
                <strong>{{ $rule->seller?->name ?? '-' }}</strong>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $rule->seller?->clinic?->trade_name ?? $rule->seller?->clinic?->corporate_name ?? '-' }}</div>
                @endif
              </td>
              <td>{{ $rule->name }}<div class="muted">{{ number_format((float) $rule->percentage, 2, ',', '.') }}%</div></td>
              <td>{{ $basisLabel($rule->basis) }}<div class="muted">{{ $recognitionLabel($rule->recognition) }}</div></td>
              <td>{{ $rule->starts_on->format('d/m/Y') }}{{ $rule->ends_on ? ' a '.$rule->ends_on->format('d/m/Y') : ' em diante' }}</td>
              <td><span class="badge {{ $rule->active ? 'success' : 'muted-badge' }}">{{ $rule->active ? 'Ativa' : 'Inativa' }}</span></td>
              <td><a class="button secondary" href="{{ route('commissions.edit', $rule->id) }}">Editar</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhuma regra cadastrada.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $rules->withQueryString()->links() }}
@endsection
