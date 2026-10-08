@extends('layouts.admin')

@section('title', 'Configuração da agenda de Banho e Tosa - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Configuração da agenda</h1>
      <p>A agenda interna aceita horários durante as 24 horas do dia. Ajuste a grade e registre somente as indisponibilidades reais.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('service-orders.agenda', array_filter(['clinic_id' => auth()->user()?->clinic_id === null ? $clinic?->id : null])) }}">Voltar à agenda</a>
    </div>
  </header>

  @if($clinics->isNotEmpty())
    <section class="panel">
      <div class="panel-body">
        <form method="GET" action="{{ route('service-orders.grooming-settings') }}" class="form-grid compact-filter-grid">
          <div class="field">
            <label for="settings_clinic">Clínica</label>
            <select id="settings_clinic" name="clinic_id">
              @foreach($clinics as $option)
                <option value="{{ $option->id }}" @selected($clinic?->id === $option->id)>{{ $option->trade_name ?? $option->corporate_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="field"><button type="submit">Selecionar</button></div>
        </form>
      </div>
    </section>
  @endif

  @if(! $clinic)
    <div class="panel"><div class="panel-body"><p>Selecione uma clínica para configurar a agenda.</p></div></div>
  @else
    <section class="panel">
      <div class="panel-heading">
        <div><span class="eyebrow">Agenda 24 horas</span><h2>{{ $clinic->trade_name ?? $clinic->corporate_name }}</h2></div>
      </div>
      <div class="panel-body">
        <p>Os horários de 00:00 a 23:59 ficam disponíveis para a equipe da loja ou clínica agendar. Conflitos com outro atendimento e os bloqueios cadastrados abaixo continuam sendo respeitados.</p>
        <form method="POST" action="{{ route('service-orders.grooming-settings.update') }}">
          @csrf
          @method('PUT')
          @if(auth()->user()?->clinic_id === null)<input type="hidden" name="clinic_id" value="{{ $clinic->id }}">@endif
          <div class="field" style="max-width: 260px; margin-bottom: 1rem;">
            <label for="slot_minutes">Intervalo da grade</label>
            <select id="slot_minutes" name="slot_minutes">
              @foreach($slotOptions as $minutes)
                <option value="{{ $minutes }}" @selected((int) old('slot_minutes', $clinicSchedule['slot_minutes']) === $minutes)>{{ $minutes }} minutos</option>
              @endforeach
            </select>
          </div>
          <div class="actions" style="margin-top: 1rem;"><button type="submit">Salvar intervalo da grade</button></div>
        </form>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><span class="eyebrow">Exceções</span><h2>Folgas, feriados e bloqueios</h2></div></div>
      <div class="panel-body">
        <form method="POST" action="{{ route('service-orders.grooming-settings.blocks.store') }}" class="form-grid">
          @csrf
          @if(auth()->user()?->clinic_id === null)<input type="hidden" name="clinic_id" value="{{ $clinic->id }}">@endif
          <div class="field">
            <label for="block_user_id">Aplicar a</label>
            <select id="block_user_id" name="user_id">
              <option value="">Toda a clínica</option>
              @foreach($professionals as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
            </select>
          </div>
          <div class="field"><label for="block_starts_at">Início</label><input id="block_starts_at" name="starts_at" type="datetime-local" required value="{{ old('starts_at') }}"></div>
          <div class="field"><label for="block_ends_at">Fim</label><input id="block_ends_at" name="ends_at" type="datetime-local" required value="{{ old('ends_at') }}"></div>
          <div class="field"><label for="block_reason">Motivo</label><input id="block_reason" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="Feriado, folga, manutenção..."></div>
          <div class="field"><button type="submit">Registrar bloqueio</button></div>
        </form>

        <div class="table-wrap" style="margin-top: 1rem;">
          <table>
            <thead><tr><th>Período</th><th>Escopo</th><th>Motivo</th><th>Registrado por</th><th></th></tr></thead>
            <tbody>
              @forelse($blocks as $block)
                <tr>
                  <td>{{ $block->starts_at->format('d/m/Y H:i') }} – {{ $block->ends_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $block->user?->name ?? 'Toda a clínica' }}</td>
                  <td>{{ $block->reason ?? 'Sem motivo informado' }}</td>
                  <td>{{ $block->creator?->name ?? 'Operador removido' }}</td>
                  <td>
                    <form method="POST" action="{{ route('service-orders.grooming-settings.blocks.destroy', $block) }}">
                      @csrf
                      @method('DELETE')
                      @if(auth()->user()?->clinic_id === null)<input type="hidden" name="clinic_id" value="{{ $clinic->id }}">@endif
                      <button type="submit" class="secondary">Remover</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5">Nenhum bloqueio futuro registrado.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>
  @endif
@endsection
