@extends('layouts.admin')

@section('title', 'Pacotes de banho e tosa - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Pacotes de banho e tosa</h1>
      <p>Modelos de pacote (ex.: Clubinho) com serviços, quantidade, preço e validade.</p>
    </div>
    <div class="actions">
      @can('service-orders.manage')<a class="button secondary" href="{{ route('pet-packages.index') }}">Pacotes vendidos</a>@endcan
      <a class="button" href="{{ route('petshop-packages.create') }}">Novo pacote</a>
    </div>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Pacote</th>
            <th>Serviços incluídos</th>
            <th>Preço</th>
            <th>Validade</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($packages as $package)
            <tr>
              <td><strong>{{ $package->name }}</strong>@if($package->description)<div class="muted">{{ $package->description }}</div>@endif</td>
              <td>{{ $package->items->map(fn ($item) => $item->quantity.'× '.($item->service?->name ?? 'Serviço'))->implode(', ') }}</td>
              <td>R$ {{ number_format((float) $package->price, 2, ',', '.') }}</td>
              <td>{{ $package->validity_days ? $package->validity_days.' dias' : 'Sem validade' }}</td>
              <td><span class="badge {{ $package->active ? 'success' : 'muted-badge' }}">{{ $package->active ? 'Ativo' : 'Inativo' }}</span></td>
              <td class="actions">
                @can('service-orders.manage')
                  @if($package->active)<a class="button" href="{{ route('pet-packages.create', ['petshop_package_id' => $package->id]) }}">Vender</a>@endif
                @endcan
                <a class="button secondary" href="{{ route('petshop-packages.edit', $package->id) }}">Editar</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6">Nenhum pacote cadastrado. Ex.: "Clubinho M — 4 banhos em 30 dias".</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
