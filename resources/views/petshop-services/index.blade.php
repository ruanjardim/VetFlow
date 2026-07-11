@extends('layouts.admin')

@section('title', 'Servicos PetShop - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Servicos PetShop</h1>
      <p>Banho, tosa, pacotes e servicos comerciais.</p>
    </div>
    <a class="button" href="{{ route('petshop-services.create') }}">Novo servico</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Servico</th>
            <th>Categoria</th>
            <th>Preco base</th>
            <th>Pequeno</th>
            <th>Medio</th>
            <th>Grande</th>
            <th>Duracao</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($petShopServices as $service)
            <tr>
              <td>
                <strong>{{ $service->name }}</strong>
                <div class="muted">{{ $service->requires_appointment ? 'Agenda' : 'Balcao' }}</div>
              </td>
              <td>{{ $service->category }}</td>
              <td>R$ {{ number_format((float) $service->base_price, 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) ($service->small_price ?? $service->base_price), 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) ($service->medium_price ?? $service->base_price), 2, ',', '.') }}</td>
              <td>R$ {{ number_format((float) ($service->large_price ?? $service->base_price), 2, ',', '.') }}</td>
              <td>{{ $service->duration_minutes ? $service->duration_minutes.' min' : '-' }}</td>
              <td>{{ $service->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <a class="button secondary" href="{{ route('petshop-services.edit', $service->id) }}">Editar</a>
                <form class="inline" action="{{ route('petshop-services.destroy', $service->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este servico?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="muted">Nenhum servico PetShop cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $petShopServices->links() }}
@endsection
