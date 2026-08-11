@extends('layouts.admin')

@section('title', 'Vacinação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Vacinação</h1>
      <p>Carteira de vacinação e próximas doses dos pacientes.</p>
    </div>
    <a class="button" href="{{ route('vaccinations.create') }}">Nova vacina</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Paciente</th><th>Vacina</th><th>Status</th><th>Agendada</th><th>Aplicada</th><th>Próxima dose</th><th>Ações</th></tr></thead>
        <tbody>
          @forelse($vaccinations as $vaccination)
            <tr>
              <td>{{ $vaccination->patient?->name ?? '-' }}</td>
              <td>{{ $vaccination->vaccine_name }}</td>
              <td>{{ ['scheduled' => 'Agendada', 'applied' => 'Aplicada', 'skipped' => 'Não aplicada'][$vaccination->status] ?? $vaccination->status }}</td>
              <td>{{ optional($vaccination->scheduled_for)->format('d/m/Y') }}</td>
              <td>{{ optional($vaccination->applied_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td>{{ optional($vaccination->next_due_at)->format('d/m/Y') ?: '-' }}</td>
              <td><a class="button secondary" href="{{ route('vaccinations.edit', $vaccination->id) }}">Editar</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhuma vacina registrada.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $vaccinations->links() }}
@endsection
