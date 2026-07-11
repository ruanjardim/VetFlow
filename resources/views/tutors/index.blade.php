@extends('layouts.admin')

@section('title', 'Tutores - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Tutores</h1>
      <p>Responsaveis pelos pacientes.</p>
    </div>
    <a class="button" href="{{ route('tutores.create') }}">Novo tutor</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Telefone</th>
            <th>E-mail</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($tutors as $tutor)
            <tr>
              <td>{{ $tutor->name }}</td>
              <td>{{ $tutor->phone }}</td>
              <td>{{ $tutor->email }}</td>
              <td>{{ $tutor->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <a class="button secondary" href="{{ route('tutores.edit', $tutor->id) }}">Editar</a>
                <form class="inline" action="{{ route('tutores.destroy', $tutor->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este tutor?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="muted">Nenhum tutor cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $tutors->links() }}
@endsection
