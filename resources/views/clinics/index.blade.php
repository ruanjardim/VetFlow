@extends('layouts.admin')

@section('title', 'Clinicas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Clinicas</h1>
      <p>Unidades e redes atendidas pelo VetFlow.</p>
    </div>
    <a class="button" href="{{ route('clinics.create') }}">Nova clinica</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome fantasia</th>
            <th>Documento</th>
            <th>Cidade</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($clinics as $clinic)
            <tr>
              <td>{{ $clinic->trade_name ?? $clinic->corporate_name }}</td>
              <td>{{ $clinic->cnpj }}</td>
              <td>{{ $clinic->city }}</td>
              <td>{{ $clinic->active ? 'Ativa' : 'Inativa' }}</td>
              <td>
                <a class="button secondary" href="{{ route('clinics.edit', $clinic->id) }}">Editar</a>
                <form class="inline" action="{{ route('clinics.destroy', $clinic->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover esta clinica?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="muted">Nenhuma clinica cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $clinics->links() }}
@endsection
