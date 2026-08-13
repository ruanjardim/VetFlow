@extends('layouts.admin')

@section('title', 'Pelagens e padrões - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Pelagens e padrões</h1><p>Pelagem, plumagem, coloração ou morfo vinculado à espécie correta.</p></div>
    <div class="actions">
      <a class="button secondary" href="{{ route('patient-catalog.species', array_filter(['clinic_id' => $selectedClinicId])) }}">← Voltar</a>
      <a class="button secondary" href="{{ route('patients.index') }}">Pacientes</a>
    </div>
  </header>

  <div class="panel"><div class="panel-body">
    <form method="GET" action="{{ route('patient-catalog.coats') }}" class="form-grid" data-catalog-auto-submit>
      @if($requiresClinic)
        <div class="field">
          <label for="coat-clinic">Clínica</label>
          <select id="coat-clinic" name="clinic_id" data-auto-submit-select>
            <option value="">Somente catálogo padrão</option>
            @foreach($clinics as $clinic)
              <option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="field">
        <label for="coat-species">Espécie</label>
        <select id="coat-species" name="species_id" data-auto-submit-select>
          @foreach($speciesRows as $species)
            <option value="{{ $species->id }}" @selected($selectedSpecies?->id === $species->id)>{{ $species->name }}</option>
          @endforeach
        </select>
        <div class="field-hint">A lista muda automaticamente ao selecionar outra espécie.</div>
      </div>
      <noscript><div class="field full"><button type="submit">Atualizar lista</button></div></noscript>
    </form>
  </div></div>

  <div class="content-grid catalog-layout">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>{{ $selectedSpecies?->name ?? 'Selecione uma espécie' }}</h2>
          <p>{{ $coatRows->count() }} pelagens ou padrões disponíveis.</p>
        </div>
      </div>
      @if($coatRows->isNotEmpty())
        <div class="panel-body catalog-search"><label for="coat-catalog-search">Buscar no catálogo</label><input id="coat-catalog-search" type="search" placeholder="Digite parte do nome" data-catalog-search></div>
      @endif
      <div class="table-wrap"><table data-catalog-table><thead><tr><th>Nome</th><th>Origem</th></tr></thead><tbody>
        @forelse($coatRows as $coat)
          <tr data-catalog-row><td><strong>{{ $coat->name }}</strong></td><td><span class="badge {{ $coat->system ? 'muted-badge' : 'success' }}">{{ $coat->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td></tr>
        @empty
          <tr><td colspan="2" class="muted">Nenhuma opção cadastrada. O campo “Outra” continua disponível no paciente.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>

    <div class="panel catalog-create-card">
      <div class="panel-heading"><div><h2>Adicionar pelagem ou padrão</h2><p>Uma opção própria para {{ $selectedSpecies?->name ?? 'a espécie selecionada' }}.</p></div></div>
      <div class="panel-body">
        @if(! $selectedSpecies || ($requiresClinic && ! $selectedClinicId))
          <div class="alert-soft">Selecione uma clínica e uma espécie para adicionar uma opção própria.</div>
        @else
          <form method="POST" action="{{ route('patient-catalog.coats.store') }}" class="form-grid">@csrf
            <input type="hidden" name="animal_species_id" value="{{ $selectedSpecies->id }}">
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <div class="field full"><label for="coat-name">Nome</label><input id="coat-name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
            <div class="field full"><div class="field-hint">Para aves, répteis e peixes, use também para plumagem, coloração, padrão ou morfo.</div></div>
            <div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
          </form>
        @endif
      </div>
    </div>
  </div>
@endsection
