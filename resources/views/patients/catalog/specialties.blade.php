@extends('layouts.admin')

@section('title', 'Espécies de atuação - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Minhas espécies de atuação</h1><p>Personalize o catálogo exibido no seu dia a dia clínico.</p></div>
    <div class="actions"><a class="button secondary" href="{{ route('patient-catalog.species', array_filter(['clinic_id' => $selectedClinicId])) }}" data-history-back>← Voltar</a></div>
  </header>

  @if($requiresClinic)
    <div class="panel"><div class="panel-body"><form method="GET" action="{{ route('patient-catalog.specialties') }}" class="form-grid" data-catalog-auto-submit>
      <div class="field"><label for="specialty-clinic">Clínica</label><select id="specialty-clinic" name="clinic_id" data-auto-submit-select><option value="">Somente catálogo padrão</option>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>
      <noscript><div class="field full"><button type="submit">Abrir catálogo</button></div></noscript>
    </form></div></div>
  @endif

  <div class="panel">
    <div class="panel-heading"><div><h2>Selecione o que você atende</h2><p>Marque Canino, Felino ou qualquer outra espécie. Você pode ampliar essa lista quando sua atuação evoluir.</p></div></div>
    <div class="panel-body">
      <div class="alert-soft">Se nenhuma opção for marcada, o VetFlow continuará exibindo todas as espécies. A preferência reduz seletores e catálogos, mas não oculta pacientes nem prontuários históricos.</div>
      <form method="POST" action="{{ route('patient-catalog.specialties.update', array_filter(['clinic_id' => $selectedClinicId])) }}" data-specialty-form>@csrf @method('PUT')
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        @foreach($categories as $category => $categoryLabel)
          @php($categorySpecies = $speciesRows->where('category', $category))
          @if($categorySpecies->isNotEmpty())
            <section class="specialty-section">
              <h3>{{ $categoryLabel }}</h3>
              <div class="specialty-grid">
                @foreach($categorySpecies as $species)
                  <label class="specialty-option">
                    <input type="checkbox" name="species_ids[]" value="{{ $species->id }}" @checked(in_array((int) $species->id, $selectedSpeciesIds, true))>
                    <span><strong>{{ $species->name }}</strong><small>{{ $species->system ? 'Padrão VetFlow' : 'Cadastro da clínica' }}</small></span>
                  </label>
                @endforeach
              </div>
            </section>
          @endif
        @endforeach
        <div class="specialty-actions"><span data-specialty-count></span><button type="submit">Salvar espécies de atuação</button></div>
      </form>
    </div>
  </div>
@endsection
