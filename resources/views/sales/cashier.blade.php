@extends('layouts.admin')

@section('title', 'Caixa do dia - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $quantity = fn ($value) => number_format((float) $value, 3, ',', '.');
  @endphp

  <header class="topbar">
    <div>
      <h1>Caixa do dia</h1>
      <p>Resumo operacional do PDV em {{ $period['label'] }}.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.profitability', ['from' => $period['from'], 'to' => $period['to']]) }}">Rentabilidade</a>
      <a class="button" href="{{ route('sales.cashier.close', ['from' => $period['from'], 'to' => $period['to']]) }}">Fechar caixa</a>
      <a class="button secondary" href="{{ route('sales.index') }}">Ver vendas</a>
      <a class="button" href="{{ route('sales.create') }}">Nova venda</a>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('sales.cashier') }}" class="form-grid">
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
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('sales.cashier') }}">Hoje</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Vendas concluidas</span>
      <strong>{{ $stats['sales_count'] }}</strong>
    </div>
    <div class="stat">
      <span>Total vendido</span>
      <strong>{{ $money($stats['total']) }}</strong>
    </div>
    <div class="stat">
      <span>Recebido no caixa</span>
      <strong>{{ $money($stats['received']) }}</strong>
    </div>
    <div class="stat">
      <span>Ticket medio</span>
      <strong>{{ $money($stats['average_ticket']) }}</strong>
    </div>
  </div>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Subtotal</span>
      <strong>{{ $money($stats['subtotal']) }}</strong>
    </div>
    <div class="stat">
      <span>Descontos</span>
      <strong>{{ $money($stats['discount']) }}</strong>
    </div>
    <div class="stat">
      <span>Acrescimos</span>
      <strong>{{ $money($stats['additions']) }}</strong>
    </div>
    <div class="stat">
      <span>Pendente nas vendas</span>
      <strong>{{ $money($stats['pending']) }}</strong>
    </div>
    <div class="stat">
      <span>Rascunhos no periodo</span>
      <strong>{{ $stats['draft_sales_count'] }}</strong>
    </div>
  </div>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Dinheiro recebido</span>
      <strong>{{ $money($stats['cash_received']) }}</strong>
    </div>
    <div class="stat">
      <span>Recebido sem dinheiro</span>
      <strong>{{ $money($stats['non_cash_received']) }}</strong>
    </div>
    <div class="stat">
      <span>Troco</span>
      <strong>{{ $money($stats['change']) }}</strong>
    </div>
    <div class="stat">
      <span>Estornos em dinheiro</span>
      <strong>{{ $money($stats['cash_refunds']) }}</strong>
    </div>
  </div>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>Dinheiro esperado</span>
      <strong>{{ $money($stats['cash_drawer']) }}</strong>
    </div>
    <div class="stat">
      <span>Estornos totais</span>
      <strong>{{ $money($stats['refunds']) }}</strong>
    </div>
    <div class="stat">
      <span>Recebido liquido</span>
      <strong>{{ $money($stats['net_received']) }}</strong>
    </div>
    <div class="stat">
      <span>Devolucoes</span>
      <strong>{{ $money($stats['returns']) }}</strong>
    </div>
  </div>

  <div class="content-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Recebimentos por forma</h2>
          <p>Valores recebidos no periodo filtrado.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Forma</th>
              <th>Qtd</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($summary['payments_by_method'] as $method)
              <tr>
                <td>{{ $method['label'] }}</td>
                <td>{{ $method['count'] }}</td>
                <td>{{ $money($method['amount']) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="muted">Nenhum recebimento no periodo.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Itens mais vendidos</h2>
          <p>Produtos, servicos e avulsos por faturamento.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Qtd</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($summary['top_items'] as $item)
              <tr>
                <td>
                  <strong>{{ $item['description'] }}</strong>
                  <div class="muted">{{ $item['type'] === 'service' ? 'Servico' : ($item['type'] === 'product' ? 'Produto' : 'Avulso') }}</div>
                </td>
                <td>{{ $quantity($item['quantity']) }}</td>
                <td>{{ $money($item['total']) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="muted">Nenhum item vendido no periodo.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="content-grid">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Vendas recentes</h2>
          <p>Ultimas vendas concluidas no periodo.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Codigo</th>
              <th>Cliente</th>
              <th>Pagamento</th>
              <th>Total</th>
              <th>Data</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            @forelse($summary['recent_sales'] as $sale)
              <tr>
                <td><strong>{{ $sale->code }}</strong></td>
                <td>{{ $sale->tutor?->name ?? $sale->patient?->name ?? '-' }}</td>
                <td>
                  @if($sale->payment_status === 'paid')
                    <span class="badge success">Pago</span>
                  @elseif($sale->payment_status === 'partial')
                    <span class="badge warning">Parcial</span>
                  @else
                    <span class="badge muted-badge">Pendente</span>
                  @endif
                </td>
                <td>{{ $money($sale->total) }}</td>
                <td>{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</td>
                <td>
                  <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Abrir</a>
                  <a class="button secondary" href="{{ route('sales.receipt', $sale->id) }}">Comprovante</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="muted">Nenhuma venda concluida no periodo.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Pendencias de recebimento</h2>
          <p>Vendas concluidas ainda nao quitadas.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Venda</th>
              <th>Cliente</th>
              <th>Falta</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            @forelse($summary['open_sales'] as $sale)
              @php($balance = max(0, (float) $sale->total - (float) $sale->paid_total))
              <tr>
                <td><strong>{{ $sale->code }}</strong></td>
                <td>{{ $sale->tutor?->name ?? $sale->patient?->name ?? '-' }}</td>
                <td>{{ $money($balance) }}</td>
                <td>
                  <a class="button secondary" href="{{ route('sales.edit', $sale->id) }}">Abrir</a>
                  <a class="button secondary" href="{{ route('sales.receipt', $sale->id) }}">Comprovante</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhuma pendencia de venda.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Desempenho por operador</h2>
        <p>Base para acompanhar vendas e recebimentos antes de configurar regras de comissao.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Operador</th>
            <th>Vendas</th>
            <th>Vendido</th>
            <th>Recebido no periodo</th>
            <th>Pendente nas vendas</th>
            <th>Margem bruta</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['seller_performance'] as $seller)
            <tr>
              <td><strong>{{ $seller['seller_name'] }}</strong></td>
              <td>{{ $seller['sales_count'] }}</td>
              <td>{{ $money($seller['sold_total']) }}</td>
              <td>{{ $money($seller['received']) }}</td>
              <td>{{ $money($seller['pending']) }}</td>
              <td>
                {{ $money($seller['gross_profit']) }}
                @if($seller['gross_margin_percent'] !== null)
                  <div class="muted">{{ number_format($seller['gross_margin_percent'], 2, ',', '.') }}%</div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhuma venda ou recebimento no periodo.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>Fechamentos recentes</h2>
        <p>Conferencias de caixa salvas.</p>
      </div>
      <a class="button secondary" href="{{ route('sales.cashier.close', ['from' => $period['from'], 'to' => $period['to']]) }}">Novo fechamento</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Periodo</th>
            <th>Total esperado</th>
            <th>Total conferido</th>
            <th>Diferenca total</th>
            <th>Por forma</th>
            <th>Status</th>
            <th>Fechado em</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['closures'] as $closure)
            <tr>
              <td>{{ $closure->period_from->format('d/m/Y') }} a {{ $closure->period_to->format('d/m/Y') }}</td>
              <td>{{ $money($closure->expected_total) }}</td>
              <td>{{ $money($closure->counted_total) }}</td>
              <td>{{ $money($closure->total_difference) }}</td>
              <td>
                @forelse(data_get($closure->metadata, 'payment_reconciliation', []) as $method)
                  @if(abs((float) ($method['expected'] ?? 0)) >= 0.01 || abs((float) ($method['counted'] ?? 0)) >= 0.01 || abs((float) ($method['difference'] ?? 0)) >= 0.01)
                    <div>
                      <strong>{{ $method['label'] ?? $method['method'] }}:</strong>
                      {{ $money($method['counted'] ?? 0) }}
                      @if(abs((float) ($method['difference'] ?? 0)) >= 0.01)
                        <span class="badge warning">{{ $money($method['difference']) }}</span>
                      @endif
                    </div>
                  @endif
                @empty
                  <span class="muted">Fechamento anterior</span>
                @endforelse
              </td>
              <td>
                @if($closure->status === 'balanced')
                  <span class="badge success">Conferido</span>
                @else
                  <span class="badge warning">Diferenca</span>
                @endif
              </td>
              <td>{{ $closure->closed_at->format('d/m/Y H:i') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhum fechamento registrado ainda.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
