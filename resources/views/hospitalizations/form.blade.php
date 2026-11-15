@php($selectedPatientId = old('patient_id', $hospitalization->patient_id ?? $preselectedPatientId ?? null))

<div class="form-grid" data-hospitalization-form>
  @if(isset($hospitalization))
    <div class="field"><label>Paciente</label><input value="{{ $hospitalization->patient?->name }}" disabled></div>
    <input type="hidden" name="patient_id" value="{{ $hospitalization->patient_id }}">
  @else
    <div class="field">
      <label for="patient_id">Paciente</label>
      <select id="patient_id" name="patient_id" required data-hospitalization-patient>
        <option value="">Selecione</option>
        @foreach($patients as $patient)
          <option value="{{ $patient->id }}" @selected((int) $selectedPatientId === $patient->id)>{{ $patient->name }}{{ $patient->tutor ? ' — '.$patient->tutor->name : '' }}</option>
        @endforeach
      </select>
    </div>
  @endif

  <div class="field"><label for="status">Status</label><select id="status" name="status" required>@foreach(['hospitalized' => 'Internado', 'discharged' => 'Alta registrada', 'cancelled' => 'Cancelada'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $hospitalization->status ?? 'hospitalized') === $value)>{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="admitted_at">Data e hora da admissão</label><input id="admitted_at" name="admitted_at" type="datetime-local" required value="{{ old('admitted_at', isset($hospitalization) && $hospitalization->admitted_at ? $hospitalization->admitted_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}"></div>
  <div class="field"><label for="expected_discharge_at">Previsão de alta</label><input id="expected_discharge_at" name="expected_discharge_at" type="datetime-local" value="{{ old('expected_discharge_at', isset($hospitalization) && $hospitalization->expected_discharge_at ? $hospitalization->expected_discharge_at->format('Y-m-d\TH:i') : '') }}"></div>
  <div class="field"><label for="discharged_at">Data e hora da alta</label><input id="discharged_at" name="discharged_at" type="datetime-local" value="{{ old('discharged_at', isset($hospitalization) && $hospitalization->discharged_at ? $hospitalization->discharged_at->format('Y-m-d\TH:i') : '') }}"><small>Obrigatória somente ao registrar alta.</small></div>
  <div class="field"><label for="accommodation">Leito ou setor</label><input id="accommodation" name="accommodation" maxlength="120" value="{{ old('accommodation', $hospitalization->accommodation ?? '') }}" placeholder="Ex.: Baia 02, isolamento"></div>
  <div class="field full">
    <label for="medical_record_id">Prontuário relacionado</label>
    <select id="medical_record_id" name="medical_record_id" data-hospitalization-medical-record>
      <option value="">Não vincular</option>
      @foreach($medicalRecords as $medicalRecord)
        <option value="{{ $medicalRecord->id }}" data-patient-id="{{ $medicalRecord->patient_id }}" @selected((int) old('medical_record_id', $hospitalization->medical_record_id ?? 0) === $medicalRecord->id)>#{{ $medicalRecord->id }} — {{ $medicalRecord->patient?->name }} — {{ optional($medicalRecord->examined_at)->format('d/m/Y') }}</option>
      @endforeach
    </select>
    <small>Opcional. O prontuário precisa pertencer ao mesmo paciente.</small>
  </div>
  <div class="field full"><label for="admission_reason">Motivo da internação</label><textarea id="admission_reason" name="admission_reason" required>{{ old('admission_reason', $hospitalization->admission_reason ?? '') }}</textarea></div>
  <div class="field full"><label for="clinical_notes">Acompanhamento operacional</label><textarea id="clinical_notes" name="clinical_notes">{{ old('clinical_notes', $hospitalization->clinical_notes ?? '') }}</textarea><small>Registre informações operacionais. Condutas e prescrições continuam no prontuário.</small></div>
  <div class="field full"><label for="discharge_notes">Observações de alta</label><textarea id="discharge_notes" name="discharge_notes">{{ old('discharge_notes', $hospitalization->discharge_notes ?? '') }}</textarea></div>
  <div class="field full"><div class="actions"><button type="submit">Salvar internação</button><a class="button secondary" href="{{ route('hospitalizations.index') }}">Cancelar</a></div></div>
</div>
