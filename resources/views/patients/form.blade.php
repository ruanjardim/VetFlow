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
      <label for="species">Espécie</label>
      <input id="species" name="species" list="species-suggestions" value="{{ old('species', $patient->species ?? '') }}" autocomplete="off">
      <datalist id="species-suggestions">
        @foreach(['Canino', 'Felino', 'Equino', 'Bovino', 'Caprino', 'Ovino', 'Suíno', 'Coelho', 'Roedor', 'Ave', 'Réptil', 'Peixe', 'Silvestre', 'Exótico'] as $suggestedSpecies)
          <option value="{{ $suggestedSpecies }}">
        @endforeach
      </datalist>
      <div class="field-hint">Escolha uma sugestão ou informe qualquer outra espécie.</div>
    </div>
    <div class="field">
      <label for="breed">Raça</label>
      <input id="breed" name="breed" value="{{ old('breed', $patient->breed ?? '') }}">
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
