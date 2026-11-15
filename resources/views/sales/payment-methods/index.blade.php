@extends('layouts.admin')

@section('title', 'Formas de pagamento - VetFlow')

@section('content')
  @php
    $percent = fn ($value) => number_format((float) $value, 2, ',', '.').'%';
    $settlement = fn ($days) => (int) $days === 0 ? 'Na hora' : ((int) $days === 1 ? '1 dia' : (int) $days.' dias');
    $clinicQuery = $requiresClinic ? ['clinic_id' => $selectedClinicId] : [];
  @endphp

  <header class="topbar">
    <div>
      <h1>Formas de pagamento</h1>
      <p>Uma forma para cada maquininha e tipo de cartão, com taxa e prazo de repasse.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.receivables') }}">Recebíveis de cartão</a>
      <a class="button" href="{{ route('sales.payment-methods.create', $clinicQuery) }}">Nova forma de pagamento</a>
    </div>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('sales.payment-methods.index') }}" class="panel panel-body">
      <div class="field">
        <label for="payment-method-clinic">Estabelecimento</label>
        <select id="payment-method-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="alert-soft">
    <span>
      <strong>Como funciona:</strong> cadastre, por exemplo, "Rede Visa Crédito" e "Rede Visa Débito" com a taxa e o prazo do contrato da maquininha.
      No PDV, o operador escolhe a forma exata. Cada recebimento guarda a taxa, o valor líquido e a data prevista do repasse,
      que aparecem em <a href="{{ route('sales.receivables') }}">Recebíveis de cartão</a>. Formas inativas saem do PDV, mas continuam nos recebimentos antigos.
    </span>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Forma</th>
            <th>Tipo</th>
            <th>Taxa</th>
            <th>Repasse</th>
            <th>Parcelas</th>
            <th>NSU</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($methods as $method)
            <tr>
              <td>
                <strong>{{ $method->name }}</strong>
                @if($method->acquirer || $method->card_brand)
                  <div class="muted">{{ trim(($method->acquirer ?: '').' '.($method->card_brand ?: '')) }}</div>
                @endif
              </td>
              <td>{{ $method->kindLabel() }}</td>
              <td>{{ $method->feeSummary() }}</td>
              <td>
                {{ $settlement($method->settlement_days) }}
                @if($method->allowsInstallments())
                  <div class="muted">{{ $method->installmentSettlement() === 'upfront' ? 'Parcelas antecipadas' : 'Uma parcela por mês' }}</div>
                @endif
              </td>
              <td>{{ $method->allowsInstallments() ? 'Até '.$method->maxInstallments().'x' : 'À vista' }}</td>
              <td>{{ $method->requires_reference ? 'Obrigatório' : 'Opcional' }}</td>
              <td><span class="badge {{ $method->active ? 'success' : 'muted-badge' }}">{{ $method->active ? 'Ativa' : 'Inativa' }}</span></td>
              <td>
                <a class="button secondary" href="{{ route('sales.payment-methods.edit', array_merge(['paymentMethod' => $method->id], $clinicQuery)) }}">Editar</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="muted">Nenhuma forma de pagamento cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
