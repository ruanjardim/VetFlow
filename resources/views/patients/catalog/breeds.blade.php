@extends('layouts.admin')

@section('title', 'Raças e variedades - VetFlow')

@section('content')
  <header class="topbar"><div><h1>Raças e variedades</h1><p>Opções vinculadas à espécie correta.</p></div><div class="actions"><a class="button secondary" href="{{ route('patient-catalog.species', array_filter(['clinic_id' => $selectedClinicId])) }}">Espécies</a><a class="button secondary" href="{{ route('patients.index') }}">Pacientes</a></div></header>

  <div class="panel"><div class="panel-body"><form method="GET" action="{{ route('patient-catalog.breeds') }}" class="form-grid">
    @if($requiresClinic)<div class="field"><label for="breed-clinic">Clínica</label><select id="breed-clinic" name="clinic_id"><option value="">Somente catálogo padrão</option>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected((string) $selectedClinicId === (string) $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>@endif
    <div class="field"><label for="breed-species">Espécie</label><select id="breed-species" name="species_id">@foreach($speciesRows as $species)<option value="{{ $species->id }}" @selected($selectedSpecies?->id === $species->id)>{{ $species->name }}</option>@endforeach</select></div>
    <div class="field full"><button type="submit">Atualizar lista</button></div>
  </form></div></div>

  <div class="content-grid catalog-layout">
    <div class="panel"><div class="panel-heading"><div><h2>{{ $selectedSpecies?->name ?? 'Selecione uma espécie' }}</h2><p>{{ $breedRows->count() }} raças ou variedades disponíveis.</p></div></div><div class="table-wrap"><table><thead><tr><th>Nome</th><th>Origem</th></tr></thead><tbody>
      @forelse($breedRows as $breed)<tr><td><strong>{{ $breed->name }}</strong></td><td><span class="badge {{ $breed->system ? 'muted-badge' : 'success' }}">{{ $breed->system ? 'Padrão VetFlow' : 'Da clínica' }}</span></td></tr>@empty<tr><td colspan="2" class="muted">Nenhuma raça cadastrada. O campo “Outra” continua disponível no paciente.</td></tr>@endforelse
    </tbody></table></div></div>

    <div class="panel catalog-create-card"><div class="panel-heading"><div><h2>Adicionar raça ou variedade</h2><p>Vinculada a {{ $selectedSpecies?->name ?? 'uma espécie' }}.</p></div></div><div class="panel-body">
      @if(! $selectedSpecies || ($requiresClinic && ! $selectedClinicId))
        <div class="alert-soft">Selecione uma clínica e uma espécie para adicionar uma opção própria.</div>
      @else
        <form method="POST" action="{{ route('patient-catalog.breeds.store') }}" class="form-grid">@csrf
          <input type="hidden" name="animal_species_id" value="{{ $selectedSpecies->id }}">@if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <div class="field full"><label for="breed-name">Nome</label><input id="breed-name" name="name" value="{{ old('name') }}" maxlength="120" required></div><div class="field full"><button type="submit">Adicionar ao catálogo</button></div>
        </form>
      @endif
    </div></div>
  </div>
@endsection
