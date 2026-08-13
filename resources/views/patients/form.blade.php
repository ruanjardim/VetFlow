<div class="form-section">
  <div class="panel-heading">
    <div>
      <h2>Vínculo e identificação</h2>
      <p>Associe o pet ao responsável antes de iniciar o atendimento clínico.</p>
    </div>
  </div>
  <div class="form-grid">
    <div class="field full">
      <label for="tutor_id">Responsável</label>
      <select id="tutor_id" name="tutor_id" required>
        <option value="">Selecione o responsável</option>
        @foreach($tutors as $tutor)
          <option
            value="{{ $tutor->id }}"
            data-clinic-id="{{ $tutor->clinic_id }}"
            @selected((string) old('tutor_id', $patient->tutor_id ?? '') === (string) $tutor->id)
          >
            {{ $tutor->name }}{{ $tutor->cpf ? ' — '.$tutor->cpf : '' }}
          </option>
        @endforeach
      </select>
      <div class="field-hint">Não encontrou a pessoa? Cadastre o responsável antes de salvar o paciente.</div>
    </div>
    <div class="field">
      <label for="name">Nome do pet</label>
      <input id="name" name="name" value="{{ old('name', $patient->name ?? '') }}" autocomplete="off" required>
    </div>
    <div class="field">
      @php($selectedSpeciesChoice = (string) old('species_choice', $taxonomySelection['species'] ?? ''))
      <label for="species_choice">Espécie</label>
      <select id="species_choice" name="species_choice" data-patient-species>
        <option value="">Selecione a espécie</option>
        @foreach($speciesCategories as $category => $categoryLabel)
          @php($categorySpecies = $speciesCatalog->where('category', $category))
          @if($categorySpecies->isNotEmpty())
            <optgroup label="{{ $categoryLabel }}">
              @foreach($categorySpecies as $speciesOption)
                <option value="{{ $speciesOption->id }}" data-clinic-id="{{ $speciesOption->clinic_id }}" @selected($selectedSpeciesChoice === (string) $speciesOption->id)>
                  {{ $speciesOption->name }}{{ $speciesOption->system ? '' : ' — cadastro da clínica' }}
                </option>
              @endforeach
            </optgroup>
          @endif
        @endforeach
        <option value="other" @selected($selectedSpeciesChoice === 'other')>Outra espécie — cadastrar</option>
      </select>
      <div class="field-hint">Inclui animais de companhia, aves, exóticos, silvestres e grandes animais.</div>
    </div>
    <div class="field" data-new-species-field @if($selectedSpeciesChoice !== 'other') hidden @endif>
      <label for="new_species">Nome da nova espécie</label>
      <input id="new_species" name="new_species" value="{{ old('new_species', $taxonomySelection['new_species'] ?? '') }}" maxlength="120" autocomplete="off">
      <div class="field-hint">Ela ficará disponível somente para esta clínica.</div>
    </div>
    <div class="field">
      @php($selectedBreedChoice = (string) old('breed_choice', $taxonomySelection['breed'] ?? ''))
      <label for="breed_choice">Raça ou variedade</label>
      <select id="breed_choice" name="breed_choice" data-patient-breed>
        <option value="">Selecione após escolher a espécie</option>
        @foreach($speciesCatalog as $speciesOption)
          @foreach($speciesOption->breeds as $breedOption)
            <option value="{{ $breedOption->id }}" data-species-id="{{ $speciesOption->id }}" data-clinic-id="{{ $breedOption->clinic_id }}" @selected($selectedBreedChoice === (string) $breedOption->id)>
              {{ $breedOption->name }}{{ $breedOption->system ? '' : ' — cadastro da clínica' }}
            </option>
          @endforeach
        @endforeach
        <option value="other" @selected($selectedBreedChoice === 'other')>Outra raça ou variedade — cadastrar</option>
      </select>
      <div class="field-hint">A lista é filtrada pela espécie selecionada.</div>
    </div>
    <div class="field" data-new-breed-field @if($selectedBreedChoice !== 'other' && $selectedSpeciesChoice !== 'other') hidden @endif>
      <label for="new_breed">Nome da nova raça ou variedade</label>
      <input id="new_breed" name="new_breed" value="{{ old('new_breed', $taxonomySelection['new_breed'] ?? '') }}" maxlength="120" autocomplete="off">
      <div class="field-hint">Use também para linhagens ou variedades não listadas.</div>
    </div>
    <div class="field">
      <label for="gender">Sexo</label>
      <input id="gender" name="gender" list="gender-suggestions" value="{{ old('gender', $patient->gender ?? '') }}" maxlength="50">
      <datalist id="gender-suggestions">
        <option value="Macho">
        <option value="Fêmea">
      </datalist>
    </div>
  </div>
</div>

<div class="form-section">
  <div class="panel-heading">
    <div>
      <h2>Dados clínicos iniciais</h2>
      <p>Registre a referência inicial. As avaliações de cada consulta ficam no prontuário.</p>
    </div>
  </div>
  <div class="form-grid">
    <div class="field">
      <label for="birth_date">Data de nascimento</label>
      <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', isset($patient) && $patient?->birth_date ? $patient->birth_date->format('Y-m-d') : '') }}">
    </div>
    <div class="field">
      <label for="weight">Peso de referência (kg)</label>
      <input id="weight" name="weight" type="number" min="0.01" max="999999.99" step="0.01" value="{{ old('weight', $patient->weight ?? '') }}">
    </div>
    <div class="field full">
      <label for="notes">Observações</label>
      <textarea id="notes" name="notes">{{ old('notes', $patient->notes ?? '') }}</textarea>
    </div>
    <div class="field full">
      <div class="actions">
        <button type="submit">Salvar paciente</button>
        <a class="button secondary" href="{{ route('patients.index') }}">Cancelar</a>
      </div>
    </div>
  </div>
</div>
