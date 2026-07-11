<div class="form-grid">
  <div class="field">
    <label for="title">Titulo</label>
    <input id="title" name="title" value="{{ old('title', $schedule->title ?? '') }}">
  </div>
  <div class="field">
    <label for="type">Tipo</label>
    <input id="type" name="type" value="{{ old('type', $schedule->type ?? '') }}">
  </div>
  <div class="field">
    <label for="scheduled_date">Data</label>
    <input id="scheduled_date" name="scheduled_date" type="date" value="{{ old('scheduled_date', $schedule->scheduled_date ?? '') }}">
  </div>
  <div class="field">
    <label for="scheduled_time">Hora</label>
    <input id="scheduled_time" name="scheduled_time" type="time" value="{{ old('scheduled_time', $schedule->scheduled_time ?? '') }}">
  </div>
  <div class="field">
    <label for="patient_id">Paciente ID</label>
    <input id="patient_id" name="patient_id" type="number" value="{{ old('patient_id', $schedule->patient_id ?? '') }}">
  </div>
  <div class="field">
    <label for="tutor_id">Tutor ID</label>
    <input id="tutor_id" name="tutor_id" type="number" value="{{ old('tutor_id', $schedule->tutor_id ?? '') }}">
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      @foreach(['agendado', 'confirmado', 'concluido', 'cancelado'] as $status)
        <option value="{{ $status }}" @selected(old('status', $schedule->status ?? 'agendado') === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
  </div>
  <div class="field full">
    <label for="notes">Observacoes</label>
    <textarea id="notes" name="notes">{{ old('notes', $schedule->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar</button>
      <a class="button secondary" href="{{ route('schedules.index') }}">Cancelar</a>
    </div>
  </div>
</div>
