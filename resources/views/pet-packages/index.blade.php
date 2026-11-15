@extends('layouts.admin')

@section('title', 'Pacotes vendidos - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Pacotes vendidos</h1>
      <p>Saldo de cada pet, validade e uso nas comandas.</p>
    </div>
    <div class="actions">
      @can('petshop-services.manage')<a class="button secondary" href="{{ route('petshop-packages.index') }}">Modelos de pacote</a>@endcan
      <a class="button" href="{{ route('pet-packages.create') }}">Vender pacote</a>
    </div>
  </header>

  <section class="grid stats" aria-label="Pacotes por situação">
    <a class="stat {{ $filter === null ? 'stat--selected' : '' }}" href="{{ route('pet-packages.index', array_filter(['q' => $search])) }}"><span>Todos</span><strong>{{ $counts->sum() }}</strong></a>
    @foreach(\App\Modules\PetShopServices\Models\PetPackage::STATUS_LABELS as $status => $label)
      <a class="stat {{ $filter === $status ? 'stat--selected' : '' }}" href="{{ route('pet-packages.index', array_filter(['status' => $status, 'q' => $search])) }}"><span>{{ $label }}</span><strong>{{ $counts[$status] }}</strong></a>
    @endforeach
  </section>

  <form method="GET" action="{{ route('pet-packages.index') }}" class="panel grooming-agenda-toolbar">
    @if($filter)<input type="hidden" name="status" value="{{ $filter }}">@endif
    <label for="q" class="sr-only">Buscar</label>
    <input id="q" name="q" value="{{ $search }}" placeholder="Pet, responsável ou código">
    <button type="submit">Buscar</button>
  </form>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Pet / responsável</th>
            <th>Pacote</th>
            <th>Saldo</th>
            <th>Validade</th>
            <th>Situação</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($petPackages as $petPackage)
            @php($status = $petPackage->effectiveStatus())
            <tr>
              <td>{{ $petPackage->code }}</td>
              <td><strong>{{ $petPackage->patient?->name ?? '-' }}</strong><div class="muted">{{ $petPackage->tutor?->name ?? '' }}</div></td>
              <td>{{ $petPackage->name }}<div class="muted">R$ {{ number_format((float) $petPackage->price, 2, ',', '.') }}</div></td>
              <td>{{ $petPackage->remainingTotal() }} de {{ $petPackage->totalQuantity() }}</td>
              <td>{{ $petPackage->expires_on ? $petPackage->expires_on->format('d/m/Y') : 'Sem validade' }}</td>
              <td><span class="badge {{ ['active' => 'success', 'pending_payment' => 'warning', 'expired' => 'danger', 'cancelled' => 'muted-badge', 'consumed' => 'muted-badge'][$status] ?? '' }}">{{ $petPackage->statusLabel() }}</span></td>
              <td class="actions">
                @if($status === 'pending_payment')
                  @can('sales.manage')<a class="button" href="{{ route('sales.create', ['pet_package_id' => $petPackage->id]) }}">Receber no PDV</a>@endcan
                @endif
                <a class="button secondary" href="{{ route('pet-packages.show', $petPackage->id) }}">Detalhes</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7">Nenhum pacote encontrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
