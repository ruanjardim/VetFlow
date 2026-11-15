@extends('layouts.admin')

@section('title', 'Editar responsável - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar responsável</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  @can('sales.manage')
    @php($balance = app(\App\Modules\Sales\Services\CustomerBalanceService::class)->summary($item->id))
    <div class="alert-soft tutor-balance">
      <span>
        <strong>Saldo:</strong>
        @if($balance['debt'] > 0)
          deve R$ {{ number_format($balance['debt'], 2, ',', '.') }} em {{ $balance['open_sales'] }} {{ $balance['open_sales'] === 1 ? 'venda' : 'vendas' }}
        @else
          nada em aberto
        @endif
        · crédito de R$ {{ number_format($balance['credit'], 2, ',', '.') }}.
        <a href="{{ route('sales.customer-balances.show', $item->id) }}">Ver saldo, receber ou lançar adiantamento</a>
      </span>
    </div>
  @endcan

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('tutores.update', $item->id) }}" data-tutor-form>
        @csrf
        @method('PUT')
        @include('tutors.form', ['tutor' => $item])
      </form>
    </div>
  </div>
@endsection
