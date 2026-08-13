<div class="form-grid">
  @if(isset($medicalRecord))
    <div class="field">
      <label>Consulta vinculada</label>
      <input value="{{ $medicalRecord->appointment?->title }} — {{ optional($medicalRecord->appointment?->scheduled_at)->format('d/m/Y H:i') }}" disabled>
    </div>
    <div class="field">
      <label>Paciente</label>
      <input value="{{ $medicalRecord->patient?->name }}" disabled>
    </div>
  @else
    <div class="field">
      <label for="appointment_id">Consulta</label>
      <select id="appointment_id" name="appointment_id" required>
        <option value="">Selecione</option>
        @foreach($appointments as $appointment)
          <option value="{{ $appointment->id }}" data-patient-id="{{ $appointment->patient_id }}" data-species-id="{{ $appointment->patient?->animal_species_id }}" @selected((int) old('appointment_id', $preselectedAppointmentId ?? 0) === $appointment->id)>
            {{ $appointment->title }} — {{ optional($appointment->scheduled_at)->format('d/m/Y H:i') }} ({{ $appointment->patient?->name }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="field">
      <label for="patient_id">Paciente</label>
      <select id="patient_id" name="patient_id" required>
        <option value="">Selecione</option>
        @foreach($patients as $patient)
          <option value="{{ $patient->id }}" data-species-id="{{ $patient->animal_species_id }}" @selected((int) old('patient_id', $preselectedPatientId ?? 0) === $patient->id)>{{ $patient->name }}</option>
        @endforeach
      </select>
    </div>
  @endif

  <div class="field">
    <label for="examined_at">Data e hora do atendimento</label>
    <input id="examined_at" name="examined_at" type="datetime-local" value="{{ old('examined_at', isset($medicalRecord) && $medicalRecord->examined_at ? $medicalRecord->examined_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required>
  </div>
  <div class="field">
    <label for="weight">Peso (kg)</label>
    <input id="weight" name="weight" type="number" min="0" max="9999.99" step="0.01" value="{{ old('weight', $medicalRecord->weight ?? '') }}">
  </div>
  <div class="field">
    <label for="temperature">Temperatura (°C)</label>
    <input id="temperature" name="temperature" type="number" min="20" max="50" step="0.1" value="{{ old('temperature', $medicalRecord->temperature ?? '') }}">
  </div>
  <div class="field">
    <label for="heart_rate">Frequência cardíaca (bpm)</label>
    <input id="heart_rate" name="heart_rate" type="number" min="1" max="400" value="{{ old('heart_rate', $medicalRecord->heart_rate ?? '') }}">
  </div>
  <div class="field">
    <label for="respiratory_rate">Frequência respiratória (mpm)</label>
    <input id="respiratory_rate" name="respiratory_rate" type="number" min="1" max="300" value="{{ old('respiratory_rate', $medicalRecord->respiratory_rate ?? '') }}">
  </div>
  <div class="field">
    <label for="hydration">Hidratação</label>
    <input id="hydration" name="hydration" value="{{ old('hydration', $medicalRecord->hydration ?? '') }}" placeholder="Ex.: adequada, 5% desidratação">
  </div>
  <div class="field full">
    <label for="chief_complaint">Queixa principal</label>
    <textarea id="chief_complaint" name="chief_complaint">{{ old('chief_complaint', $medicalRecord->chief_complaint ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label for="anamnesis">Anamnese</label>
    <textarea id="anamnesis" name="anamnesis">{{ old('anamnesis', $medicalRecord->anamnesis ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label for="clinical_findings">Achados clínicos</label>
    <textarea id="clinical_findings" name="clinical_findings">{{ old('clinical_findings', $medicalRecord->clinical_findings ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="pathology-picker" data-pathology-picker data-fixed-species-id="{{ isset($medicalRecord) ? $medicalRecord->patient?->animal_species_id : '' }}">
      <label for="pathology-search">Patologias padronizadas</label>
      <input id="pathology-search" type="search" placeholder="Busque pelo nome da patologia" data-pathology-search>
      <select id="pathology_ids" name="pathology_ids[]" multiple size="9" data-pathology-select>
        @foreach($pathologyRows as $pathology)
          <option
            value="{{ $pathology->id }}"
            data-species-ids="{{ $pathology->species->pluck('id')->join(',') }}"
            @selected(in_array($pathology->id, array_map('intval', (array) $selectedPathologyIds)))
          >{{ $pathology->name }}{{ $pathology->system ? '' : ' — da clínica' }}</option>
        @endforeach
      </select>
      <small>Selecione uma ou mais opções. A lista acompanha a espécie do paciente e os itens gerais aparecem para todos.</small>
      <div class="field" data-new-pathology-field>
        <label for="new_pathology">Outra patologia</label>
        <input id="new_pathology" name="new_pathology" value="{{ old('new_pathology') }}" maxlength="160" placeholder="Digite para cadastrar e reutilizar na clínica">
      </div>
    </div>
  </div>
  <div class="field full">
    <label for="diagnosis">Diagnóstico</label>
    <textarea id="diagnosis" name="diagnosis">{{ old('diagnosis', $medicalRecord->diagnosis ?? '') }}</textarea>
    <small>Campo livre preservado para hipótese diagnóstica, diferenciais e contexto clínico.</small>
  </div>
  <div class="field full">
    <label for="treatment_plan">Plano terapêutico</label>
    <textarea id="treatment_plan" name="treatment_plan">{{ old('treatment_plan', $medicalRecord->treatment_plan ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label for="prescription_notes">Orientações e prescrição anotada</label>
    <textarea id="prescription_notes" name="prescription_notes">{{ old('prescription_notes', $medicalRecord->prescription_notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <label for="notes">Observações adicionais</label>
    <textarea id="notes" name="notes">{{ old('notes', $medicalRecord->notes ?? '') }}</textarea>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar prontuário</button>
      <a class="button secondary" href="{{ route('medical-records.index') }}">Cancelar</a>
    </div>
  </div>
</div>

@if(! isset($medicalRecord))
  <script>
    document.getElementById('appointment_id').addEventListener('change', function () {
      const patientId = this.options[this.selectedIndex].dataset.patientId;

      if (patientId) {
        document.getElementById('patient_id').value = patientId;
      }
    });
  </script>
@endif
