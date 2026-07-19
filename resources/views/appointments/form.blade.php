<div class="form-grid">
  <div class="field">
    <label for="title">Titulo</label>
    <input id="title" name="title" value="{{ old('title', $appointment->title ?? '') }}" required>
  </div>
  <div class="field">
    <label for="scheduled_at">Data e hora</label>
    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', isset($appointment) && $appointment?->scheduled_at ? $appointment->scheduled_at->format('Y-m-d\TH:i') : '') }}" required>
  </div>
  <div class="field">
    <label for="patient_id">Pet</label>
    <select id="patient_id" name="patient_id">
      <option value="">Selecione</option>
      @foreach($patients ?? [] as $patient)
        <option value="{{ $patient->id }}" @selected((int) old('patient_id', $appointment->patient_id ?? 0) === $patient->id)>
          {{ $patient->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="tutor_id">Tutor</label>
    <select id="tutor_id" name="tutor_id">
      <option value="">Selecione</option>
      @foreach($tutors ?? [] as $tutor)
        <option value="{{ $tutor->id }}" @selected((int) old('tutor_id', $appointment->tutor_id ?? 0) === $tutor->id)>
          {{ $tutor->name }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      @foreach(['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'] as $status)
        <option value="{{ $status }}" @selected(old('status', $appointment->status ?? 'scheduled') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
      @endforeach
    </select>
  </div>
  <div class="field full">
    <label for="description">Descricao</label>
    <textarea id="description" name="description">{{ old('description', $appointment->description ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('appointments.index') }}">Cancelar</a>
    </div>
  </div>
</div>
