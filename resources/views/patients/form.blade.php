@php
  $selectedNeutered = old(
    'neutered',
    isset($patient) && $patient?->neutered !== null ? ($patient->neutered ? '1' : '0') : ''
  );
  $selectedPedigree = old(
    'has_pedigree',
    isset($patient) && $patient?->has_pedigree !== null ? ($patient->has_pedigree ? '1' : '0') : ''
  );
@endphp

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
      @php($selectedCoatChoice = (string) old('coat_choice', $taxonomySelection['coat'] ?? ''))
      <label for="coat_choice">Pelagem, plumagem ou padrão</label>
      <select id="coat_choice" name="coat_choice" data-patient-coat>
        <option value="">Selecione após escolher a espécie</option>
        @foreach($speciesCatalog as $speciesOption)
          @foreach($speciesOption->coats as $coatOption)
            <option value="{{ $coatOption->id }}" data-species-id="{{ $speciesOption->id }}" data-clinic-id="{{ $coatOption->clinic_id }}" @selected($selectedCoatChoice === (string) $coatOption->id)>
              {{ $coatOption->name }}{{ $coatOption->system ? '' : ' — cadastro da clínica' }}
            </option>
          @endforeach
        @endforeach
        <option value="other" @selected($selectedCoatChoice === 'other')>Outra pelagem ou padrão — cadastrar</option>
      </select>
      <div class="field-hint">A lista muda automaticamente conforme a espécie.</div>
    </div>
    <div class="field" data-new-coat-field @if($selectedCoatChoice !== 'other' && $selectedSpeciesChoice !== 'other') hidden @endif>
      <label for="new_coat">Nome da nova pelagem ou padrão</label>
      <input id="new_coat" name="new_coat" value="{{ old('new_coat', $taxonomySelection['new_coat'] ?? '') }}" maxlength="120" autocomplete="off">
      <div class="field-hint">Use também para coloração, morfo ou padrão não listado.</div>
    </div>
    <div class="field">
      <label for="gender">Sexo</label>
      <input id="gender" name="gender" list="gender-suggestions" value="{{ old('gender', $patient->gender ?? '') }}" maxlength="50">
      <datalist id="gender-suggestions">
        <option value="Macho">
        <option value="Fêmea">
      </datalist>
    </div>
    <div class="field">
      <label for="neutered">Esterilização</label>
      <select id="neutered" name="neutered">
        <option value="" @selected($selectedNeutered === '')>Não informado</option>
        <option value="1" @selected((string) $selectedNeutered === '1')>Castrado / esterilizado</option>
        <option value="0" @selected((string) $selectedNeutered === '0')>Não castrado</option>
      </select>
      <div class="field-hint">Registros antigos permanecem como não informados até uma conferência humana.</div>
    </div>
    <div class="field">
      <label for="microchip">Microchip</label>
      <input id="microchip" name="microchip" value="{{ old('microchip', $patient->microchip ?? '') }}" maxlength="100" autocomplete="off">
      <div class="field-hint">Identificador informado pela clínica ou pelo responsável; não precisa ser exclusivo.</div>
    </div>
    <div class="field">
      <label for="has_pedigree">Pedigree</label>
      <select id="has_pedigree" name="has_pedigree">
        <option value="" @selected($selectedPedigree === '')>Não informado</option>
        <option value="1" @selected((string) $selectedPedigree === '1')>Possui pedigree</option>
        <option value="0" @selected((string) $selectedPedigree === '0')>Não possui pedigree</option>
      </select>
      <div class="field-hint">Registre somente a informação declarada; o VetFlow não certifica autenticidade.</div>
    </div>
    <div class="field">
      <label for="pedigree_number">Número do pedigree</label>
      <input id="pedigree_number" name="pedigree_number" value="{{ old('pedigree_number', $patient->pedigree_number ?? '') }}" maxlength="120" autocomplete="off">
      <div class="field-hint">Aceita letras, números, barras e outros caracteres do registro informado.</div>
    </div>
    <div class="field full">
      <label for="markings">Marcações de identificação</label>
      <textarea id="markings" name="markings" maxlength="1000" rows="3">{{ old('markings', $patient->markings ?? '') }}</textarea>
      <div class="field-hint">Descreva sinais visuais úteis para identificação, sem interpretação clínica.</div>
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
      <input id="weight" name="weight" type="number" min="0.01" max="999999.99" step="0.01" value="{{ old('weight', $patient->weight ?? '') }}" data-patient-weight>
    </div>
    <div class="field">
      <label for="size">Porte (banho e tosa)</label>
      <select id="size" name="size" data-patient-size>
        <option value="">Sugerir pelo peso</option>
        @foreach(\App\Modules\Patients\Support\PatientSize::LABELS as $sizeValue => $sizeLabel)
          <option value="{{ $sizeValue }}" @selected(old('size', $patient->size ?? '') === $sizeValue)>{{ $sizeLabel }}</option>
        @endforeach
      </select>
      <small class="muted" data-patient-size-hint>Define a tabela de preço do banho e tosa. Sem porte, usamos o peso: até 10 kg pequeno, até 25 kg médio, até 45 kg grande, acima gigante.</small>
    </div>
    <div class="field full patient-photo-input">
      <label for="photo">Foto do paciente</label>
      <div class="patient-photo-editor">
        <div class="patient-photo-frame patient-photo-frame-large">
          @if(isset($patient) && $patient?->photo_path)
            <img src="{{ route('patients.photo', $patient->id) }}" alt="Foto atual de {{ $patient->name }}">
          @else
            <span aria-hidden="true">🐾</span>
          @endif
        </div>
        <div class="patient-photo-controls">
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
          <small>JPEG, PNG ou WebP, até 5 MB. A imagem fica em armazenamento privado da clínica.</small>
          @if(isset($patient) && $patient?->photo_path)
            <label class="checkbox-row" for="remove_photo">
              <input id="remove_photo" name="remove_photo" type="checkbox" value="1" @checked(old('remove_photo'))>
              Remover a foto atual
            </label>
            <small>Uma nova foto substitui a atual; o novo envio tem prioridade sobre a opção de remoção.</small>
          @endif
        </div>
      </div>
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
