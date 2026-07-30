@extends('layouts.admin')

@section('title', 'Usuarios e acessos - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Usuarios e acessos</h1>
      <p>Colaboradores, clinicas e perfis operacionais.</p>
    </div>
    <a class="button" href="{{ route('access-users.create') }}">Novo colaborador</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Colaborador</th>
            <th>Clinica</th>
            <th>Cargo</th>
            <th>Perfis</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users as $accessUser)
            <tr>
              <td>
                <strong>{{ $accessUser->name }}</strong>
                <div class="muted">{{ $accessUser->email }}</div>
              </td>
              <td>{{ $accessUser->clinic?->trade_name ?? $accessUser->clinic?->corporate_name ?? 'Global' }}</td>
              <td>{{ $accessUser->position ?: 'Nao informado' }}</td>
              <td>
                <div class="badge-list">
                  @forelse($accessUser->roles as $role)
                    <span class="badge muted-badge">{{ $role->name }}</span>
                  @empty
                    <span class="badge warning">Sem perfil</span>
                  @endforelse
                </div>
              </td>
              <td>
                <span class="badge {{ $accessUser->active ? 'success' : 'danger' }}">
                  {{ $accessUser->active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td>
                <a class="button secondary" href="{{ route('access-users.edit', $accessUser->id) }}">Editar acesso</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum colaborador encontrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $users->links() }}
@endsection
