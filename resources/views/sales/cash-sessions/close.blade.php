@extends('layouts.admin')

@section('title', 'Fechar caixa '.$session->code.' - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $moneyInput = fn ($value) => number_format((float) $value, 2, ',', '');
    $cash = $summary['cash'];
    $methods = $summary['methods'];
  @endphp

  <header class="topbar">
    <div>
      <h1>Fechar caixa {{ $session->code }}</h1>
      <p>Conte o dinheiro da gaveta e confira os totais de cada maquininha e do Pix. As diferenças ficam registradas para o gestor conferir.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.cash-sessions.show', $session->id) }}">Voltar ao caixa</a>
    </div>
  </header>

  <form method="POST" action="{{ route('sales.cash-sessions.close.store', $session->id) }}" data-cash-close-form>
    @csrf
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Dinheiro</h2>
          <p>
            Esperado {{ $money($cash['expected']) }}: fundo {{ $money($cash['opening']) }}
            + recebido {{ $money($cash['received']) }} − troco {{ $money($cash['change']) }}
            + suprimentos {{ $money($cash['supplies']) }} − sangrias {{ $money($cash['withdrawals']) }}
            − despesas {{ $money($cash['expenses']) }} − estornos {{ $money($cash['refunds']) }}.
          </p>
        </div>
      </div>
      <div class="panel-body form-grid">
        <div class="field">
          <label for="counted_cash">Dinheiro contado na gaveta</label>
          <input id="counted_cash" name="counted_cash" type="text" inputmode="decimal" placeholder="0,00" value="{{ old('counted_cash') }}" data-money-input data-cash-close-counted data-expected="{{ $cash['expected'] }}" required autofocus>
          <small class="muted" data-cash-close-difference></small>
        </div>
        <div class="field">
          <label for="cash_left">Deixar na gaveta para o próximo caixa</label>
          <input id="cash_left" name="cash_left" type="text" inputmode="decimal" value="{{ old('cash_left', $moneyInput($suggestedCashLeft)) }}" data-money-input>
          <small class="muted">Vira o fundo de troco sugerido na próxima abertura. O resto é recolhido.</small>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Outras formas</h2>
          <p>Já vem preenchido com o esperado. Ajuste pelo relatório da maquininha ou do banco, se for diferente.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Forma</th>
              <th>Qtd</th>
              <th>Esperado</th>
              <th>Conferido</th>
            </tr>
          </thead>
          <tbody>
            @forelse($methods as $row)
              <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['count'] }}</td>
                <td>{{ $money($row['expected']) }}</td>
                <td>
                  <input name="counted_methods[{{ $row['key'] }}]" type="text" inputmode="decimal" value="{{ old('counted_methods.'.$row['key'], $moneyInput($row['expected'])) }}" aria-label="Conferido em {{ $row['label'] }}" data-money-input>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhum recebimento fora do dinheiro neste caixa.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if(count($summary['fees_by_machine']) > 0)
        <div class="panel-body">
          <p class="muted">
            Ao fechar, as taxas viram uma despesa paga por maquininha:
            @foreach($summary['fees_by_machine'] as $fee)
              <strong>{{ $fee['label'] }}</strong> {{ $money($fee['amount']) }}@if(! $loop->last) · @endif
            @endforeach
          </p>
        </div>
      @endif
    </div>

    <div class="panel">
      <div class="panel-body form-grid">
        <div class="field full">
          <label for="close_notes">Observação (opcional)</label>
          <textarea id="close_notes" name="notes" rows="2" maxlength="2000" placeholder="Explique diferenças, se houver">{{ old('notes') }}</textarea>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Fechar caixa</button>
            <a class="button secondary" href="{{ route('sales.cash-sessions.show', $session->id) }}">Cancelar</a>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection
