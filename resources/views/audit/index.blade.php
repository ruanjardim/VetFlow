@extends('layouts.admin')

@section('title', 'Auditoria - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Auditoria administrativa</h1>
      <p>Histórico somente leitura das alterações sensíveis registradas pelo VetFlow.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('audit-events.index') }}" class="form-grid audit-filters">
        <div class="field">
          <label for="audit-search">Colaborador, usuário ou clínica</label>
          <input id="audit-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar no histórico">
        </div>
        <div class="field">
          <label for="audit-event">Tipo de alteração</label>
          <select id="audit-event" name="event">
            <option value="">Todos os tipos</option>
            @foreach($eventLabels as $event => $label)
              <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="audit-from">De</label>
          <input id="audit-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="field">
          <label for="audit-to">Até</label>
          <input id="audit-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('audit-events.index') }}">Limpar</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  @php
    $fieldLabels = [
      'clinic_id' => 'Clínica',
      'name' => 'Nome',
      'email' => 'E-mail',
      'phone' => 'Telefone',
      'position' => 'Cargo',
      'active' => 'Status ativo',
      'roles' => 'Perfis',
      'brand_icon_mode' => 'Modo do ícone',
      'brand_icon_key' => 'Ícone',
    ];
    $formatAuditValue = static function ($value): string {
      if ($value === null || $value === '') return '—';
      if (is_bool($value)) return $value ? 'Sim' : 'Não';
      if (is_array($value)) return $value === [] ? '—' : implode(', ', $value);
      return (string) $value;
    };
  @endphp

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Alteração</th>
            <th>Responsável</th>
            <th>Clínica</th>
            <th>Registro</th>
            <th>Detalhes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($events as $auditEvent)
            <tr>
              <td>
                <strong>{{ $auditEvent->occurred_at?->format('d/m/Y H:i') }}</strong>
                <div class="muted">#{{ $auditEvent->id }}</div>
              </td>
              <td>{{ $eventLabels[$auditEvent->event] ?? $auditEvent->event }}</td>
              <td>
                {{ $auditEvent->actor?->name ?? 'Sistema' }}
                @if($auditEvent->actor?->email)<div class="muted">{{ $auditEvent->actor->email }}</div>@endif
              </td>
              <td>{{ $auditEvent->clinic?->trade_name ?? $auditEvent->clinic?->corporate_name ?? 'Global' }}</td>
              <td>{{ $auditEvent->subject_label ?: class_basename($auditEvent->subject_type).' #'.$auditEvent->subject_id }}</td>
              <td>
                @if($auditEvent->changes || $auditEvent->metadata)
                  <details class="audit-changes">
                    <summary>Ver alterações</summary>
                    @foreach($auditEvent->changes ?? [] as $field => $change)
                      <div>
                        <strong>{{ $fieldLabels[$field] ?? str($field)->headline() }}</strong>
                        <span>{{ $formatAuditValue($change['before'] ?? null) }} → {{ $formatAuditValue($change['after'] ?? null) }}</span>
                      </div>
                    @endforeach
                    @if(($auditEvent->metadata['password_changed'] ?? false) === true)
                      <div><strong>Senha</strong><span>Alterada; valor não armazenado</span></div>
                    @endif
                  </details>
                @else
                  <span class="muted">Sem diferenças</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhum evento de auditoria encontrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $events->links() }}
@endsection
