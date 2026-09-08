@extends('layouts.admin')

@section('title', 'Catálogo de vacinas - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Catálogo de vacinas</h1><p>Opções padronizadas e protocolos configuráveis pela clínica.</p></div>
    <div class="actions"><a class="button secondary" href="{{ route('vaccinations.index') }}">← Voltar</a></div>
  </header>

  <div class="panel"><div class="panel-body"><form method="GET" action="{{ route('vaccine-catalog.index') }}" class="form-grid" data-catalog-auto-submit>
    @if($requiresClinic)
      <div class="field"><label for="vaccine-clinic">Clínica</label><select id="vaccine-clinic" name="clinic_id" data-auto-submit-select><option value="">Somente catálogo padrão</option>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label for="vaccine-species">Espécie</label><select id="vaccine-species" name="species_id" data-auto-submit-select><option value="">Todas as espécies</option>@foreach($speciesRows as $species)<option value="{{ $species->id }}" @selected($selectedSpecies?->id === $species->id)>{{ $species->name }}</option>@endforeach</select></div>
    <noscript><div class="field full"><button type="submit">Aplicar filtro</button></div></noscript>
  </form></div></div>

  <div class="content-grid catalog-layout">
    <section class="panel">
      <div class="panel-heading"><div><h2>Catálogo disponível</h2><p>{{ $vaccineRows->count() }} {{ $vaccineRows->count() === 1 ? 'vacina disponível' : 'vacinas disponíveis' }}{{ $selectedSpecies ? ' para '.$selectedSpecies->name : '' }}.</p></div></div>
      <div class="panel-body catalog-search"><label for="vaccine-catalog-search">Buscar no catálogo</label><input id="vaccine-catalog-search" type="search" placeholder="Digite parte do nome ou espécie" data-catalog-search></div>
      <div class="table-wrap"><table data-catalog-table><thead><tr><th>Vacina</th><th>Espécies</th><th>Protocolo configurado</th><th>Origem</th></tr></thead><tbody>
        @forelse($vaccineRows as $vaccine)
          <tr data-catalog-row><td><strong>{{ $vaccine->name }}</strong></td><td>{{ $vaccine->species->isEmpty() ? 'Todas as espécies' : $vaccine->species->pluck('name')->join(', ') }}</td><td>{{ $vaccine->protocolLabel() }}</td><td><span class="badge {{ $vaccine->system ? 'muted-badge' : 'success' }}">{{ $vaccine->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td></tr>
        @empty
          <tr><td colspan="4" class="muted">Nenhuma vacina disponível para o filtro escolhido.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>

    <aside class="panel catalog-create-card"><div class="panel-heading"><div><h2>Adicionar vacina</h2><p>Uma opção própria e reutilizável pela clínica.</p></div></div><div class="panel-body">
      @if($requiresClinic && ! $selectedClinicId)
        <div class="alert-soft">Selecione uma clínica acima para adicionar uma vacina própria.</div>
      @else
        <form method="POST" action="{{ route('vaccine-catalog.store') }}" class="form-grid">@csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <input type="hidden" name="return_species_id" value="{{ $selectedSpecies?->id }}">
          <div class="field full"><label for="vaccine-name">Nome</label><input id="vaccine-name" name="name" value="{{ old('name') }}" maxlength="160" required></div>
          <div class="field"><label for="recommended-doses">Doses do esquema (opcional)</label><input id="recommended-doses" name="recommended_doses" type="number" min="1" max="99" value="{{ old('recommended_doses') }}"></div>
          <div class="field"><label for="recommended-interval">Intervalo entre doses (dias)</label><input id="recommended-interval" name="recommended_interval_days" type="number" min="1" max="3650" value="{{ old('recommended_interval_days') }}"></div>
          <div class="field full"><label for="vaccine-species-ids">Espécies relacionadas</label><select id="vaccine-species-ids" name="species_ids[]" multiple size="10">@foreach($speciesRows as $species)<option value="{{ $species->id }}" @selected(in_array($species->id, old('species_ids', $selectedSpecies ? [$selectedSpecies->id] : [])))>{{ $species->name }}</option>@endforeach</select><small>Deixe sem seleção para disponibilizar a todas as espécies. Use Ctrl ou ⌘ para marcar várias.</small></div>
          <div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
        </form>
      @endif
    </div></aside>
  </div>

  <p class="catalog-reference-note">O VetFlow não impõe protocolo clínico. Configure doses e intervalos conforme o produto, a bula e a orientação do médico-veterinário responsável.</p>
@endsection
