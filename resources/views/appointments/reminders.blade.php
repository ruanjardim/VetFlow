@extends('layouts.admin')

@section('title', 'Lembretes de consultas - VetFlow')

@section('content')
  @php
    $period = $summary['period'];
    $stats = $summary['stats'];
    $state = $summary['filter']['state'];
  @endphp

  <header class="topbar">
    <div>
      <h1>Lembretes de consultas</h1>
      <p>Prepare o contato e registre a resposta do responsavel.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('schedules.index') }}">Agenda</a>
      <a class="button secondary" href="{{ route('appointments.index') }}">Consultas</a>
      <a class="button" href="{{ route('appointments.create') }}">Nova consulta</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Envio assistido</strong>
    <span>O VetFlow prepara a mensagem e abre o WhatsApp, mas nao envia nada automaticamente. Registre o resultado somente depois de realizar o contato.</span>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('appointments.reminders') }}" class="form-grid">
        <div class="field">
          <label for="from">De</label>
          <input id="from" name="from" type="date" value="{{ $period['from'] }}">
        </div>
        <div class="field">
          <label for="to">Ate</label>
          <input id="to" name="to" type="date" value="{{ $period['to'] }}">
        </div>
        <div class="field">
          <label for="state">Ultimo contato</label>
          <select id="state" name="state">
            <option value="all" @selected($state === 'all')>Todos</option>
            <option value="pending" @selected($state === 'pending')>Pendentes</option>
            @foreach($outcomeLabels as $value => $label)
              @continue($value === 'cancelled')
              <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Atualizar fila</button>
            <a class="button secondary" href="{{ route('appointments.reminders') }}">Proximos 3 dias</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <section class="grid stats">
    <div class="stat"><span>Consultas no periodo</span><strong>{{ $stats['appointments'] }}</strong></div>
    <div class="stat"><span>Aguardando contato</span><strong>{{ $stats['pending'] }}</strong></div>
    <div class="stat"><span>Presencas confirmadas</span><strong>{{ $stats['confirmed'] }}</strong></div>
    <div class="stat"><span>Precisam de retorno</span><strong>{{ $stats['needs_follow_up'] }}</strong></div>
  </section>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Fila de {{ $period['label'] }}</h2>
        <p>Exibindo: {{ $summary['filter']['state_label'] }}.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="appointment-reminders-table">
        <thead>
          <tr>
            <th>Consulta</th>
            <th>Paciente e responsavel</th>
            <th>Contato</th>
            <th>Ultimo lembrete</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($summary['appointments'] as $row)
            @php
              $appointment = $row['appointment'];
              $tutor = $appointment->tutor;
              $latest = $row['latest_reminder'];
              $defaultChannel = $row['whatsapp_url']
                ? 'whatsapp'
                : ($tutor?->phone ? 'phone' : ($tutor?->email ? 'email' : 'other'));
            @endphp
            <tr>
              <td>
                <strong>{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</strong>
                <div>{{ $appointment->title }}</div>
                <span class="badge {{ $appointment->status === 'confirmed' ? 'success' : 'muted-badge' }}">
                  {{ $appointment->status === 'confirmed' ? 'Confirmada' : 'Agendada' }}
                </span>
              </td>
              <td>
                <strong>{{ $appointment->patient?->name ?? 'Paciente nao informado' }}</strong>
                <div class="muted">{{ $tutor?->name ?? 'Responsavel nao informado' }}</div>
              </td>
              <td class="reminder-contact">
                @if($tutor?->phone_secondary)
                  <div><strong>WhatsApp:</strong> {{ $tutor->phone_secondary }}</div>
                @endif
                @if($tutor?->phone)
                  <div><strong>Telefone:</strong> {{ $tutor->phone }}</div>
                @endif
                @if($tutor?->email)
                  <div><strong>E-mail:</strong> {{ $tutor->email }}</div>
                @endif
                @unless($row['has_contact'])
                  <span class="badge warning">Contato nao cadastrado</span>
                @endunless
              </td>
              <td>
                <span class="badge {{ $row['state'] === 'confirmed' ? 'success' : ($row['state'] === 'pending' ? 'muted-badge' : 'warning') }}">
                  {{ $row['state_label'] }}
                </span>
                @if($latest)
                  <div class="muted">{{ $channelLabels[$latest->channel] ?? $latest->channel }} · {{ $latest->contacted_at->format('d/m H:i') }}</div>
                  <div class="muted">{{ $latest->recordedBy?->name ?? 'Usuario removido' }}</div>
                @endif
              </td>
              <td>
                <div class="reminder-actions">
                  @if($row['whatsapp_url'])
                    <a class="button secondary" href="{{ $row['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
                  @endif
                  <a class="button secondary" href="{{ route('appointments.edit', $appointment->id) }}">Ver consulta</a>
                </div>

                <details class="reminder-details">
                  <summary>Registrar contato</summary>
                  <form class="reminder-form" method="POST" action="{{ route('appointments.reminders.store', $appointment->id) }}">
                    @csrf
                    <input type="hidden" name="return_from" value="{{ $period['from'] }}">
                    <input type="hidden" name="return_to" value="{{ $period['to'] }}">
                    <input type="hidden" name="return_state" value="{{ $state }}">
                    <div class="field">
                      <label for="channel-{{ $appointment->id }}">Canal</label>
                      <select id="channel-{{ $appointment->id }}" name="channel" required>
                        @foreach($channelLabels as $value => $label)
                          <option value="{{ $value }}" @selected($defaultChannel === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field">
                      <label for="outcome-{{ $appointment->id }}">Resultado</label>
                      <select id="outcome-{{ $appointment->id }}" name="outcome" required>
                        @foreach($outcomeLabels as $value => $label)
                          <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="field full">
                      <label for="notes-{{ $appointment->id }}">Observacao</label>
                      <textarea id="notes-{{ $appointment->id }}" name="notes" maxlength="1000" placeholder="Opcional"></textarea>
                    </div>
                    <div class="field full">
                      <button type="submit">Salvar contato</button>
                    </div>
                  </form>
                </details>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="muted">Nenhuma consulta encontrada para os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
