@extends('layouts.admin')

@section('title', 'Saldo de clientes - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $filter = $filters['filter'] ?? 'all';
  @endphp

  <header class="topbar">
    <div>
      <h1>Saldo de clientes</h1>
      <p>Quem está devendo vendas pagas depois (fiado) e quem tem crédito para usar.</p>
    </div>
    <div class="actions">
      <a class="button" href="{{ route('sales.create') }}">Ponto de venda</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <div class="stat">
      <span>A receber (fiado)</span>
      <strong>{{ $money($totals['debt']) }}</strong>
    </div>
    <div class="stat">
      <span>Clientes devendo</span>
      <strong>{{ $totals['debtors'] }}</strong>
    </div>
    <div class="stat">
      <span>Créditos de clientes</span>
      <strong>{{ $money($totals['credit']) }}</strong>
    </div>
    <div class="stat">
      <span>Clientes com crédito</span>
      <strong>{{ $totals['creditors'] }}</strong>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('sales.customer-balances.index') }}" class="form-grid">
        <div class="field">
          <label for="balance_filter">Mostrar</label>
          <select id="balance_filter" name="filter">
            <option value="all" @selected($filter === 'all')>Todos com saldo</option>
            <option value="debt" @selected($filter === 'debt')>Devendo</option>
            <option value="credit" @selected($filter === 'credit')>Com crédito</option>
          </select>
        </div>
        <div class="field">
          <label for="balance_search">Cliente</label>
          <input id="balance_search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nome do responsável">
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('sales.customer-balances.index') }}">Limpar</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Deve</th>
            <th>Vendas em aberto</th>
            <th>Desde</th>
            <th>Crédito</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($customers as $row)
            <tr>
              <td>
                <strong>{{ $row['tutor']->name }}</strong>
                @if($row['tutor']->phone_secondary || $row['tutor']->phone)
                  <div class="muted">{{ $row['tutor']->phone_secondary ?: $row['tutor']->phone }}</div>
                @endif
              </td>
              <td>{{ $row['debt'] > 0 ? $money($row['debt']) : '-' }}</td>
              <td>{{ $row['open_sales'] ?: '-' }}</td>
              <td>{{ $row['oldest'] ? \Illuminate\Support\Carbon::parse($row['oldest'])->format('d/m/Y') : '-' }}</td>
              <td>{{ abs($row['credit']) >= 0.01 ? $money($row['credit']) : '-' }}</td>
              <td><a class="button secondary" href="{{ route('sales.customer-balances.show', $row['tutor']->id) }}">Ver saldo</a></td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum cliente com saldo.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
