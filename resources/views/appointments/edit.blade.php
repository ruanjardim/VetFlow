@extends('layouts.admin')

@section('title', 'Editar consulta - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar consulta</h1>
      <p>{{ $item->title }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('appointments.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('appointments.form', ['appointment' => $item])
      </form>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Historico de lembretes</h2>
        <p>Contatos registrados para esta consulta.</p>
      </div>
      <a class="button secondary" href="{{ route('appointments.reminders', ['from' => $item->scheduled_at?->toDateString(), 'to' => $item->scheduled_at?->toDateString()]) }}">Abrir fila</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Data</th><th>Canal</th><th>Resultado</th><th>Destino</th><th>Registrado por</th><th>Observacao</th></tr>
        </thead>
        <tbody>
          @php
            $channels = ['whatsapp' => 'WhatsApp', 'phone' => 'Ligacao', 'email' => 'E-mail', 'other' => 'Outro'];
            $outcomes = ['contacted' => 'Aviso enviado', 'confirmed' => 'Presenca confirmada', 'no_answer' => 'Sem resposta', 'reschedule_requested' => 'Solicitou reagendamento', 'cancelled' => 'Consulta cancelada'];
          @endphp
          @forelse($item->reminders->sortByDesc('contacted_at') as $reminder)
            <tr>
              <td>{{ $reminder->contacted_at->format('d/m/Y H:i') }}</td>
              <td>{{ $channels[$reminder->channel] ?? $reminder->channel }}</td>
              <td>{{ $outcomes[$reminder->outcome] ?? $reminder->outcome }}</td>
              <td>{{ $reminder->destination_snapshot ?: '-' }}</td>
              <td>{{ $reminder->recordedBy?->name ?? 'Usuario removido' }}</td>
              <td>{{ $reminder->notes ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhum contato registrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
