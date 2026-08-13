@extends('layouts.admin')

@section('title', 'Espécies - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Espécies</h1><p>Catálogo clínico compartilhado e opções próprias da clínica.</p></div>
    <div class="actions">
      <a class="button secondary" href="{{ route('patients.index') }}" data-history-back>← Voltar</a>
      <a class="button secondary" href="{{ route('patient-catalog.specialties', array_filter(['clinic_id' => $selectedClinicId])) }}">Minhas espécies de atuação</a>
      <a class="button secondary" href="{{ route('patient-catalog.breeds', array_filter(['clinic_id' => $selectedClinicId])) }}">Ver raças</a>
      <a class="button secondary" href="{{ route('patient-catalog.coats', array_filter(['clinic_id' => $selectedClinicId])) }}">Ver pelagens</a>
    </div>
  </header>

  @if($requiresClinic)
    <div class="panel"><div class="panel-body"><form method="GET" action="{{ route('patient-catalog.species') }}" class="form-grid" data-catalog-auto-submit>
      <div class="field"><label for="catalog-clinic">Clínica para personalização</label><select id="catalog-clinic" name="clinic_id" data-auto-submit-select><option value="">Somente catálogo padrão</option>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>
      <noscript><div class="field full"><button type="submit">Abrir catálogo</button></div></noscript>
    </form></div></div>
  @endif

  <div class="content-grid catalog-layout">
    <div class="panel">
      <div class="panel-heading"><div><h2>Catálogo disponível</h2><p>{{ $speciesRows->count() }} espécies no seu perfil de atuação.</p></div></div>
      <div class="table-wrap"><table><thead><tr><th>Espécie</th><th>Grupo</th><th>Origem</th><th>Raças/variedades</th><th>Pelagens/padrões</th></tr></thead><tbody>
        @forelse($speciesRows as $species)
          <tr><td><strong>{{ $species->name }}</strong></td><td>{{ $categories[$species->category] ?? $species->category }}</td><td><span class="badge {{ $species->system ? 'muted-badge' : 'success' }}">{{ $species->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td><td><a href="{{ route('patient-catalog.breeds', array_filter(['clinic_id' => $selectedClinicId, 'species_id' => $species->id])) }}">{{ $species->breeds_count }} cadastradas</a></td><td><a href="{{ route('patient-catalog.coats', array_filter(['clinic_id' => $selectedClinicId, 'species_id' => $species->id])) }}">{{ $species->coats_count }} cadastradas</a></td></tr>
        @empty<tr><td colspan="5" class="muted">Nenhuma espécie disponível. Revise suas espécies de atuação.</td></tr>@endforelse
      </tbody></table></div>
    </div>

    <div class="panel catalog-create-card"><div class="panel-heading"><div><h2>Adicionar espécie</h2><p>Uma opção própria e reutilizável.</p></div></div><div class="panel-body">
      @if($requiresClinic && ! $selectedClinicId)
        <div class="alert-soft">Selecione uma clínica acima para adicionar uma espécie própria.</div>
      @else
        <form method="POST" action="{{ route('patient-catalog.species.store') }}" class="form-grid">@csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <div class="field full"><label for="species-name">Nome</label><input id="species-name" name="name" value="{{ old('name') }}" maxlength="120" required></div>
          <div class="field full"><label for="species-category">Grupo</label><select id="species-category" name="category" required>@foreach($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
          <div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
        </form>
      @endif
    </div></div>
  </div>
@endsection
