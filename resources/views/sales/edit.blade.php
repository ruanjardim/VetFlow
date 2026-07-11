@extends('layouts.admin')

@section('title', 'Editar venda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar venda</h1>
      <p>{{ $item->code }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.receipt', $item->id) }}">Comprovante</a>
      @if($item->status === 'completed')
        <a class="button secondary" href="{{ route('sales.returns.create', $item->id) }}">Devolver</a>
        <form class="inline" action="{{ route('sales.cancel', $item->id) }}" method="POST">
          @csrf
          @method('PATCH')
          <button class="danger" type="submit" data-confirm="Cancelar esta venda e estornar estoque/financeiro?">Cancelar venda</button>
        </form>
      @endif
      <a class="button secondary" href="{{ route('sales.cashier') }}">Caixa do dia</a>
    </div>
  </header>

  @if($item->status === 'cancelled')
    <div class="alert error">
      Venda cancelada em {{ optional($item->cancelled_at)->format('d/m/Y H:i') }}.
      @if($item->cancellation_reason)
        Motivo: {{ $item->cancellation_reason }}
      @endif
    </div>
  @elseif($item->status === 'returned')
    <div class="alert success">Venda totalmente devolvida e estornada.</div>
  @elseif((float) $item->return_total > 0)
    <div class="alert success">Esta venda possui devolucao parcial de R$ {{ number_format((float) $item->return_total, 2, ',', '.') }}.</div>
  @endif

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('sales.form', ['sale' => $item])
      </form>
    </div>
  </div>
@endsection
