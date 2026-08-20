@extends('layouts.admin')

@section('title', 'Prescrições - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Prescrições</h1>
      <p>Documentos terapêuticos vinculados ao prontuário e ao paciente.</p>
    </div>
    <a class="button" href="{{ route('prescriptions.create') }}">Nova prescrição</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Paciente</th>
            <th>Prontuário</th>
            <th>Status</th>
            <th>Responsável pelo registro</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($prescriptions as $prescription)
            @php($statusClass = match($prescription->status) { 'finalized' => 'success', 'cancelled' => 'danger', default => 'warning' })
            <tr>
              <td>{{ optional($prescription->prescribed_at)->format('d/m/Y H:i') }}</td>
              <td>{{ $prescription->patient?->name ?? '-' }}</td>
              <td>#{{ $prescription->medical_record_id }}</td>
              <td><span class="badge {{ $statusClass }}">{{ \App\Modules\Prescriptions\Models\Prescription::STATUS_LABELS[$prescription->status] ?? $prescription->status }}</span></td>
              <td>{{ $prescription->createdBy?->name ?? '-' }}</td>
              <td>
                <a class="button secondary" href="{{ route('prescriptions.show', $prescription->id) }}">Abrir</a>
                @if($prescription->isDraft())
                  <a class="button secondary" href="{{ route('prescriptions.edit', $prescription->id) }}">Editar</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhuma prescrição registrada.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $prescriptions->links() }}
@endsection
