<div class="form-grid">
  <div class="field full">
    <label for="tutor_id">Tutor responsável</label>
    <select id="tutor_id" name="tutor_id" required>
      <option value="">Selecione o tutor</option>
      @foreach($tutors as $tutor)
        <option
          value="{{ $tutor->id }}"
          @selected((string) old('tutor_id', $patient->tutor_id ?? '') === (string) $tutor->id)
        >
          {{ $tutor->name }}{{ $tutor->cpf ? ' — '.$tutor->cpf : '' }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $patient->name ?? '') }}" required>
  </div>
  <div class="field">
    <label for="species">Especie</label>
    <input id="species" name="species" value="{{ old('species', $patient->species ?? '') }}">
  </div>
  <div class="field">
    <label for="breed">Raca</label>
    <input id="breed" name="breed" value="{{ old('breed', $patient->breed ?? '') }}">
  </div>
  <div class="field">
    <label for="gender">Sexo</label>
    <input id="gender" name="gender" value="{{ old('gender', $patient->gender ?? '') }}">
  </div>
  <div class="field">
    <label for="birth_date">Nascimento</label>
    <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', isset($patient) && $patient?->birth_date ? $patient->birth_date->format('Y-m-d') : '') }}">
  </div>
  <div class="field">
    <label for="weight">Peso</label>
    <input id="weight" name="weight" type="number" step="0.01" value="{{ old('weight', $patient->weight ?? '') }}">
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $patient->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('patients.index') }}">Cancelar</a>
    </div>
  </div>
</div>
