@extends('layouts.admin')

@section('title', 'Exames - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Exames</h1><p>Catálogo clínico para solicitações estruturadas, com opções próprias da clínica.</p></div>
    <div class="actions"><a class="button secondary" href="{{ route('medical-records.index') }}" data-history-back>← Voltar</a><a class="button secondary" href="{{ route('pathology-catalog.index', array_filter(['clinic_id' => $selectedClinicId])) }}">Patologias</a></div>
  </header>

  <div class="panel"><div class="panel-body"><form method="GET" action="{{ route('exam-catalog.index') }}" class="form-grid" data-catalog-auto-submit>
    @if($requiresClinic)
      <div class="field"><label for="exam-clinic">Clínica</label><select id="exam-clinic" name="clinic_id" data-auto-submit-select><option value="">Somente catálogo padrão</option>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label for="exam-species">Espécie</label><select id="exam-species" name="species_id" data-auto-submit-select><option value="">Todas as espécies</option>@foreach($speciesRows as $species)<option value="{{ $species->id }}" @selected($selectedSpecies?->id === $species->id)>{{ $species->name }}</option>@endforeach</select></div>
    <noscript><div class="field full"><button type="submit">Aplicar filtro</button></div></noscript>
  </form></div></div>

  <div class="content-grid catalog-layout">
    <section class="panel">
      <div class="panel-heading"><div><h2>Catálogo disponível</h2><p>{{ $examRows->count() }} {{ $examRows->count() === 1 ? 'exame disponível' : 'exames disponíveis' }}{{ $selectedSpecies ? ' para '.$selectedSpecies->name : '' }}.</p></div></div>
      <div class="panel-body catalog-search"><label for="exam-catalog-search">Buscar no catálogo</label><input id="exam-catalog-search" type="search" placeholder="Digite parte do nome, categoria ou espécie" data-catalog-search></div>
      <div class="table-wrap"><table data-catalog-table><thead><tr><th>Exame</th><th>Categoria</th><th>Espécies</th><th>Origem</th></tr></thead><tbody>
        @forelse($examRows as $exam)
          <tr data-catalog-row><td><strong>{{ $exam->name }}</strong></td><td>{{ $exam->category ?: 'Não categorizado' }}</td><td>{{ $exam->species->isEmpty() ? 'Todas as espécies' : $exam->species->pluck('name')->join(', ') }}</td><td><span class="badge {{ $exam->system ? 'muted-badge' : 'success' }}">{{ $exam->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td></tr>
        @empty
          <tr><td colspan="4" class="muted">Nenhum exame disponível para o filtro escolhido.</td></tr>
        @endforelse
      </tbody></table></div>
    </section>

    <aside class="panel catalog-create-card"><div class="panel-heading"><div><h2>Adicionar exame</h2><p>Uma opção própria e reutilizável pela clínica.</p></div></div><div class="panel-body">
      @if($requiresClinic && ! $selectedClinicId)
        <div class="alert-soft">Selecione uma clínica acima para adicionar um exame próprio.</div>
      @else
        <form method="POST" action="{{ route('exam-catalog.store') }}" class="form-grid">@csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <input type="hidden" name="return_species_id" value="{{ $selectedSpecies?->id }}">
          <div class="field full"><label for="exam-name">Nome</label><input id="exam-name" name="name" value="{{ old('name') }}" maxlength="160" required></div>
          <div class="field full"><label for="exam-category">Categoria (opcional)</label><input id="exam-category" name="category" value="{{ old('category') }}" maxlength="80" placeholder="Ex.: Laboratorial, Imagem"></div>
          <div class="field full"><label for="exam-species-ids">Espécies relacionadas</label><select id="exam-species-ids" name="species_ids[]" multiple size="10">@foreach($speciesRows as $species)<option value="{{ $species->id }}" @selected(in_array($species->id, old('species_ids', $selectedSpecies ? [$selectedSpecies->id] : [])))>{{ $species->name }}</option>@endforeach</select><small>Deixe sem seleção para disponibilizar a todas as espécies. Use Ctrl ou ⌘ para marcar várias.</small></div>
          <div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
        </form>
      @endif
    </div></aside>
  </div>

  <p class="catalog-reference-note">O catálogo padroniza a solicitação registrada no prontuário. A indicação, a coleta e a interpretação permanecem sob responsabilidade do médico-veterinário.</p>
@endsection
