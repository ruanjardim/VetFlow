@extends('layouts.admin')

@section('title', 'Pacote '.$petPackage->code.' - VetFlow')

@php($status = $petPackage->effectiveStatus())

@section('content')
  <header class="topbar">
    <div>
      <h1>{{ $petPackage->name }} · {{ $petPackage->patient?->name }}</h1>
      <p>{{ $petPackage->code }} · {{ $petPackage->tutor?->name ?? 'Sem responsável' }} · <span class="badge {{ ['active' => 'success', 'pending_payment' => 'warning', 'expired' => 'danger'][$status] ?? 'muted-badge' }}">{{ $petPackage->statusLabel() }}</span></p>
    </div>
    <div class="actions">
      @if($status === 'pending_payment')
        @can('sales.manage')
          <a class="button" href="{{ route('sales.create', ['pet_package_id' => $petPackage->id]) }}">Receber no PDV</a>
          <form method="POST" action="{{ route('pet-packages.activate', $petPackage->id) }}">@csrf @method('PATCH')<button type="submit" class="secondary">Liberar (pago fora do PDV)</button></form>
        @endcan
      @endif
      @if(! in_array($petPackage->status, ['cancelled'], true))
        <form method="POST" action="{{ route('pet-packages.cancel', $petPackage->id) }}" data-confirm="Cancelar o pacote {{ $petPackage->code }}?">@csrf @method('PATCH')<button type="submit" class="secondary">Cancelar pacote</button></form>
      @endif
      @if(in_array($status, ['active'], true))
        <a class="button" href="{{ route('service-orders.create', ['status' => 'scheduled', 'patient_id' => $petPackage->patient_id, 'tutor_id' => $petPackage->tutor_id]) }}">Agendar uso</a>
      @endif
    </div>
  </header>

  <section class="grid stats">
    <div class="stat"><span>Preço</span><strong>R$ {{ number_format((float) $petPackage->price, 2, ',', '.') }}</strong></div>
    <div class="stat"><span>Saldo</span><strong>{{ $petPackage->remainingTotal() }} de {{ $petPackage->totalQuantity() }}</strong></div>
    <div class="stat"><span>Validade</span><strong>{{ $petPackage->starts_on->format('d/m') }} a {{ $petPackage->expires_on?->format('d/m/Y') ?? 'sem fim' }}</strong></div>
    <div class="stat"><span>Venda</span><strong>{{ $petPackage->sale?->code ?? '—' }}</strong></div>
  </section>

  <div class="panel">
    <h2>Saldo por serviço</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Serviço</th><th>Contratado</th><th>Usado</th><th>Restante</th><th>Valor por sessão</th></tr></thead>
        <tbody>
          @foreach($petPackage->balances as $balance)
            <tr>
              <td>{{ $balance->service_name }}</td>
              <td>{{ $balance->quantity }}</td>
              <td>{{ $balance->usages->sum('quantity') }}</td>
              <td><strong>{{ $balance->remaining() }}</strong></td>
              <td>R$ {{ number_format((float) $balance->unit_value, 2, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel">
    <h2>Uso nas comandas</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Comanda</th><th>Serviço</th><th>Agendado para</th><th>Situação</th></tr></thead>
        <tbody>
          @php($usages = $petPackage->balances->flatMap(fn ($balance) => $balance->usages->map(fn ($usage) => [$balance, $usage]))->sortBy(fn ($pair) => $pair[1]->serviceOrder?->scheduled_at))
          @forelse($usages as [$balance, $usage])
            <tr>
              <td>@if($usage->serviceOrder)<a href="{{ route('service-orders.edit', $usage->service_order_id) }}">{{ $usage->serviceOrder->code }}</a>@else - @endif</td>
              <td>{{ $usage->quantity }}× {{ $balance->service_name }}</td>
              <td>{{ optional($usage->serviceOrder?->scheduled_at ?? $usage->used_at)->format('d/m/Y H:i') }}</td>
              <td>{{ $usage->serviceOrder?->statusLabel() ?? '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="4">Nenhum uso ainda. Ao agendar/abrir comanda para este pet com um serviço do pacote, o saldo é abatido automaticamente.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($petPackage->notes)
    <div class="panel"><h2>Observações</h2><p>{!! nl2br(e($petPackage->notes)) !!}</p></div>
  @endif
@endsection
