@php($selectedVaccineId = old('animal_vaccine_id', $vaccination->animal_vaccine_id ?? null))

<div class="form-grid" data-vaccination-form>
  <div class="field">
    <label for="patient_id">Paciente</label>
    <select id="patient_id" name="patient_id" required data-vaccination-patient>
      <option value="">Selecione</option>
      @foreach($patients as $patient)
        <option value="{{ $patient->id }}" data-species-id="{{ $patient->animal_species_id ?? '' }}" @selected((int) old('patient_id', $vaccination->patient_id ?? $preselectedPatientId ?? 0) === $patient->id)>{{ $patient->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="animal_vaccine_id">Vacina do catálogo</label>
    <select id="animal_vaccine_id" name="animal_vaccine_id" data-vaccine-select>
      <option value="">Informar manualmente</option>
      @foreach($vaccines as $vaccine)
        <option value="{{ $vaccine->id }}" data-vaccine-name="{{ $vaccine->name }}" data-species-ids="{{ $vaccine->species->pluck('id')->join(',') }}" data-recommended-interval-days="{{ $vaccine->recommended_interval_days ?? '' }}" @selected((int) $selectedVaccineId === $vaccine->id)>{{ $vaccine->name }}{{ $vaccine->species->isNotEmpty() ? ' · '.$vaccine->species->pluck('name')->join(', ') : '' }}</option>
      @endforeach
    </select>
    <small>Use uma opção padronizada ou cadastre uma nova no <a href="{{ route('vaccine-catalog.index') }}">catálogo de vacinas</a>.</small>
  </div>
  <div class="field">
    <label for="vaccine_name">Nome da vacina</label>
    <input id="vaccine_name" name="vaccine_name" value="{{ old('vaccine_name', $vaccination->vaccine_name ?? '') }}" data-vaccine-name-input>
    <small>Obrigatório apenas quando não houver seleção no catálogo.</small>
  </div>
  <div class="field"><label for="status">Status</label><select id="status" name="status" required>@foreach(['scheduled' => 'Agendada', 'applied' => 'Aplicada', 'skipped' => 'Não aplicada'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $vaccination->status ?? 'scheduled') === $value)>{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="scheduled_for">Data agendada</label><input id="scheduled_for" name="scheduled_for" type="date" value="{{ old('scheduled_for', isset($vaccination) && $vaccination->scheduled_for ? $vaccination->scheduled_for->format('Y-m-d') : now()->toDateString()) }}" required data-vaccination-scheduled></div>
  <div class="field"><label for="applied_at">Data e hora da aplicação</label><input id="applied_at" name="applied_at" type="datetime-local" value="{{ old('applied_at', isset($vaccination) && $vaccination->applied_at ? $vaccination->applied_at->format('Y-m-d\\TH:i') : '') }}" data-vaccination-applied></div>
  <div class="field"><label for="next_due_at">Próxima dose</label><input id="next_due_at" name="next_due_at" type="date" value="{{ old('next_due_at', isset($vaccination) && $vaccination->next_due_at ? $vaccination->next_due_at->format('Y-m-d') : '') }}" data-vaccination-next-due><small>Uma sugestão só é exibida quando o protocolo tiver sido configurado pela clínica; você pode alterar a data.</small></div>
  <div class="field"><label for="manufacturer">Fabricante</label><input id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $vaccination->manufacturer ?? '') }}"></div>
  <div class="field"><label for="batch_number">Lote</label><input id="batch_number" name="batch_number" value="{{ old('batch_number', $vaccination->batch_number ?? '') }}"></div>
  <div class="field full"><label for="medical_record_id">Prontuário relacionado</label><select id="medical_record_id" name="medical_record_id"><option value="">Não vincular</option>@foreach($medicalRecords as $medicalRecord)<option value="{{ $medicalRecord->id }}" @selected((int) old('medical_record_id', $vaccination->medical_record_id ?? 0) === $medicalRecord->id)>#{{ $medicalRecord->id }} — {{ $medicalRecord->patient?->name }} — {{ optional($medicalRecord->examined_at)->format('d/m/Y') }}</option>@endforeach</select></div>
  <div class="field full"><label for="notes">Observações</label><textarea id="notes" name="notes">{{ old('notes', $vaccination->notes ?? '') }}</textarea></div>
  <div class="field full"><div class="actions"><button type="submit">Salvar vacina</button><a class="button secondary" href="{{ route('vaccinations.index') }}">Cancelar</a></div></div>
</div>
