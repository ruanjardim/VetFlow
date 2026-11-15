@extends('layouts.admin')

@section('title', 'Patologias - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Patologias</h1>
      <p>Catálogo clínico padronizado por espécie, com opções próprias da clínica.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('medical-records.index') }}" data-history-back>← Voltar</a>
      <a class="button secondary" href="{{ route('medical-records.index') }}">Prontuários</a>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('pathology-catalog.index') }}" class="form-grid" data-catalog-auto-submit>
        @if($requiresClinic)
          <div class="field">
            <label for="pathology-clinic">Clínica</label>
            <select id="pathology-clinic" name="clinic_id" data-auto-submit-select>
              <option value="">Somente catálogo padrão</option>
              @foreach($clinics as $clinic)
                <option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="field">
          <label for="pathology-species">Espécie</label>
          <select id="pathology-species" name="species_id" data-auto-submit-select>
            <option value="">Todas as espécies</option>
            @foreach($speciesRows as $species)
              <option value="{{ $species->id }}" @selected($selectedSpecies?->id === $species->id)>{{ $species->name }}</option>
            @endforeach
          </select>
        </div>
        <noscript><div class="field full"><button type="submit">Aplicar filtro</button></div></noscript>
      </form>
    </div>
  </div>

  <div class="content-grid catalog-layout">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Catálogo disponível</h2>
          <p>{{ $pathologyRows->count() }} {{ $pathologyRows->count() === 1 ? 'patologia disponível' : 'patologias disponíveis' }}{{ $selectedSpecies ? ' para '.$selectedSpecies->name : '' }}.</p>
        </div>
      </div>
      <div class="panel-body catalog-search">
        <label for="pathology-catalog-search">Buscar no catálogo</label>
        <input id="pathology-catalog-search" type="search" placeholder="Digite parte do nome ou da espécie" data-catalog-search>
      </div>
      <div class="table-wrap">
        <table data-catalog-table>
          <thead><tr><th>Patologia</th><th>Espécies</th><th>Origem</th></tr></thead>
          <tbody>
            @forelse($pathologyRows as $pathology)
              <tr data-catalog-row>
                <td><strong>{{ $pathology->name }}</strong></td>
                <td>{{ $pathology->species->isEmpty() ? 'Todas as espécies' : $pathology->species->pluck('name')->join(', ') }}</td>
                <td><span class="badge {{ $pathology->system ? 'muted-badge' : 'success' }}">{{ $pathology->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td>
              </tr>
            @empty
              <tr><td colspan="3" class="muted">Nenhuma patologia disponível para o filtro escolhido.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <aside class="panel catalog-create-card">
      <div class="panel-heading">
        <div><h2>Adicionar patologia</h2><p>Uma opção própria e reutilizável pela clínica.</p></div>
      </div>
      <div class="panel-body">
        @if($requiresClinic && ! $selectedClinicId)
          <div class="alert-soft">Selecione uma clínica acima para adicionar uma patologia própria.</div>
        @else
          <form method="POST" action="{{ route('pathology-catalog.store') }}" class="form-grid">
            @csrf
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <input type="hidden" name="return_species_id" value="{{ $selectedSpecies?->id }}">
            <div class="field full">
              <label for="pathology-name">Nome</label>
              <input id="pathology-name" name="name" value="{{ old('name') }}" maxlength="160" required>
            </div>
            <div class="field full">
              <label for="pathology-species-ids">Espécies relacionadas</label>
              <select id="pathology-species-ids" name="species_ids[]" multiple size="10">
                @foreach($speciesRows as $species)
                  <option value="{{ $species->id }}" @selected(in_array($species->id, old('species_ids', $selectedSpecies ? [$selectedSpecies->id] : [])))>{{ $species->name }}</option>
                @endforeach
              </select>
              <small>Deixe sem seleção para disponibilizar a todas as espécies. Use Ctrl ou ⌘ para marcar várias.</small>
            </div>
            <div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
          </form>
        @endif
      </div>
    </aside>
  </div>

  <p class="catalog-reference-note">Referências do catálogo padrão: lista sanitária do MAPA e listas de doenças da OMSA. O catálogo apoia a padronização do registro e não substitui o julgamento clínico do médico-veterinário.</p>
@endsection
