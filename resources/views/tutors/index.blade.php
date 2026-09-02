@extends('layouts.admin')

@section('title', 'Responsáveis - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Responsáveis</h1>
      <p>Pessoas responsáveis pelos pacientes.</p>
    </div>
    <a class="button" href="{{ route('tutores.create') }}">Novo responsável</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Telefone</th>
            <th>E-mail</th>
            <th>Localidade</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($tutors as $tutor)
            <tr>
              <td>{{ $tutor->name }}</td>
              <td>{{ $tutor->phone }}</td>
              <td>{{ $tutor->email ?: '-' }}</td>
              <td>{{ collect([$tutor->city, $tutor->state])->filter()->implode(' / ') ?: '-' }}</td>
              <td>{{ $tutor->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <a class="button secondary" href="{{ route('tutores.edit', $tutor->id) }}">Editar</a>
                <form class="inline" action="{{ route('tutores.destroy', $tutor->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este responsável?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum responsável cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $tutors->links() }}
@endsection
