@extends('layouts.admin')

@section('title', 'Internação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Internação de {{ $hospitalization->patient?->name }}</h1>
      <p>Atualize o acompanhamento operacional sem alterar o prontuário original.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('hospitalizations.index') }}">Voltar</a>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('hospitalizations.update', $hospitalization->id) }}">
    @csrf
    @method('PUT')
    @include('hospitalizations.form')
  </form>

  @if($hospitalization->status === 'hospitalized')
    <form class="panel" method="POST" action="{{ route('hospitalizations.evolutions.store', $hospitalization->id) }}">
      @csrf
      <div class="panel-heading">
        <div>
          <h2>Nova evolução</h2>
          <p>Adicione uma observação imutável ao diário desta internação.</p>
        </div>
      </div>
      <div class="form-grid">
        <div class="field">
          <label for="evolution_observed_at">Data e hora da observação</label>
          <input id="evolution_observed_at" type="datetime-local" name="observed_at" required value="{{ old('observed_at', now()->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="field"><label for="evolution_weight">Peso (kg)</label><input id="evolution_weight" type="number" step="0.01" min="0" max="9999.99" name="weight" value="{{ old('weight') }}"></div>
        <div class="field"><label for="evolution_temperature">Temperatura (°C)</label><input id="evolution_temperature" type="number" step="0.1" min="20" max="50" name="temperature" value="{{ old('temperature') }}"></div>
        <div class="field"><label for="evolution_heart_rate">Frequência cardíaca (bpm)</label><input id="evolution_heart_rate" type="number" min="1" max="400" name="heart_rate" value="{{ old('heart_rate') }}"></div>
        <div class="field"><label for="evolution_respiratory_rate">Frequência respiratória (mpm)</label><input id="evolution_respiratory_rate" type="number" min="1" max="300" name="respiratory_rate" value="{{ old('respiratory_rate') }}"></div>
        <div class="field full">
          <label for="evolution_notes">Evolução observada</label>
          <textarea id="evolution_notes" name="notes" required maxlength="10000" rows="6">{{ old('notes') }}</textarea>
          <small>Registre fatos observados. Condutas, prescrições e interpretações continuam nos respectivos documentos clínicos.</small>
        </div>
      </div>
      <button type="submit">Registrar evolução</button>
    </form>
  @else
    <section class="panel">
      <h2>Diário encerrado</h2>
      <p class="muted">Novas evoluções ficam bloqueadas após alta ou cancelamento. O histórico abaixo permanece disponível para consulta.</p>
    </section>
  @endif

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Diário de evoluções</h2>
        <p>Registros append-only ordenados da observação mais recente para a mais antiga.</p>
      </div>
      <span class="badge muted-badge">{{ $hospitalization->evolutions->count() }} {{ $hospitalization->evolutions->count() === 1 ? 'registro' : 'registros' }}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Observado em</th><th>Responsável</th><th>Sinais vitais</th><th>Evolução</th></tr></thead>
        <tbody>
          @forelse($hospitalization->evolutions as $evolution)
            <tr>
              <td>{{ optional($evolution->observed_at)->format('d/m/Y H:i') }}</td>
              <td>{{ $evolution->recordedBy?->name ?? '-' }}</td>
              <td>
                @if($evolution->weight !== null)<span class="badge muted-badge">{{ $evolution->weight }} kg</span>@endif
                @if($evolution->temperature !== null)<span class="badge muted-badge">{{ $evolution->temperature }} °C</span>@endif
                @if($evolution->heart_rate !== null)<span class="badge muted-badge">{{ $evolution->heart_rate }} bpm</span>@endif
                @if($evolution->respiratory_rate !== null)<span class="badge muted-badge">{{ $evolution->respiratory_rate }} mpm</span>@endif
                @if($evolution->weight === null && $evolution->temperature === null && $evolution->heart_rate === null && $evolution->respiratory_rate === null)<span class="muted">Não informados</span>@endif
              </td>
              <td>{!! nl2br(e($evolution->notes)) !!}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="muted">Nenhuma evolução registrada nesta internação.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
