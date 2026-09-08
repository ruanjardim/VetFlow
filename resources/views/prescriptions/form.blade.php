@php
  $selectedMedicalRecordId = (int) old('medical_record_id', $prescription->medical_record_id ?? $preselectedMedicalRecordId ?? 0);
  $itemRows = old('items');

  if (! is_array($itemRows)) {
    $itemRows = $prescription
      ? $prescription->items->map(fn ($item) => $item->only([
          'medication_name', 'concentration', 'dosage', 'route', 'frequency',
          'duration', 'quantity', 'instructions',
        ]))->all()
      : [['medication_name' => '', 'concentration' => '', 'dosage' => '', 'route' => '', 'frequency' => '', 'duration' => '', 'quantity' => '', 'instructions' => '']];
  }
@endphp

<div class="panel-body">
  <div class="form-grid">
    @if($prescription)
      <div class="field full">
        <label>Prontuário e paciente</label>
        <input value="#{{ $prescription->medical_record_id }} — {{ $prescription->patient?->name }} — {{ optional($prescription->medicalRecord?->examined_at)->format('d/m/Y H:i') }}" disabled>
        <input type="hidden" name="medical_record_id" value="{{ $prescription->medical_record_id }}">
      </div>
    @else
      <div class="field full">
        <label for="medical_record_id">Prontuário e paciente</label>
        <select id="medical_record_id" name="medical_record_id" required>
          <option value="">Selecione</option>
          @foreach($medicalRecords as $medicalRecord)
            <option value="{{ $medicalRecord->id }}" @selected($selectedMedicalRecordId === $medicalRecord->id)>
              #{{ $medicalRecord->id }} — {{ $medicalRecord->patient?->name }} — {{ optional($medicalRecord->examined_at)->format('d/m/Y H:i') }}
            </option>
          @endforeach
        </select>
      </div>
    @endif

    <div class="field">
      <label for="prescribed_at">Data e hora</label>
      <input id="prescribed_at" name="prescribed_at" type="datetime-local" value="{{ old('prescribed_at', $prescription?->prescribed_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" required>
    </div>
    <div class="field full">
      <label for="general_instructions">Orientações gerais</label>
      <textarea id="general_instructions" name="general_instructions">{{ old('general_instructions', $prescription->general_instructions ?? '') }}</textarea>
    </div>
  </div>

  <div class="prescription-items-heading">
    <div>
      <h2>Itens prescritos</h2>
      <p class="muted">Registre o texto exatamente como deverá aparecer no documento final.</p>
    </div>
    <button class="button secondary" type="button" data-prescription-add-item>Adicionar item</button>
  </div>

  <div class="prescription-items" data-prescription-items data-next-index="{{ count($itemRows) }}">
    @foreach($itemRows as $index => $item)
      <fieldset class="prescription-item" data-prescription-item>
        <legend>Item <span data-prescription-item-number>{{ $loop->iteration }}</span></legend>
        <div class="form-grid">
          <div class="field"><label>Medicamento</label><input name="items[{{ $index }}][medication_name]" value="{{ $item['medication_name'] ?? '' }}" maxlength="255" required></div>
          <div class="field"><label>Concentração/apresentação</label><input name="items[{{ $index }}][concentration]" value="{{ $item['concentration'] ?? '' }}" maxlength="255"></div>
          <div class="field"><label>Dose</label><input name="items[{{ $index }}][dosage]" value="{{ $item['dosage'] ?? '' }}" maxlength="255" required></div>
          <div class="field"><label>Via</label><input name="items[{{ $index }}][route]" value="{{ $item['route'] ?? '' }}" maxlength="255" placeholder="Ex.: oral"></div>
          <div class="field"><label>Frequência</label><input name="items[{{ $index }}][frequency]" value="{{ $item['frequency'] ?? '' }}" maxlength="255" required placeholder="Ex.: a cada 12 horas"></div>
          <div class="field"><label>Duração</label><input name="items[{{ $index }}][duration]" value="{{ $item['duration'] ?? '' }}" maxlength="255" placeholder="Ex.: 7 dias"></div>
          <div class="field"><label>Quantidade</label><input name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '' }}" maxlength="255"></div>
          <div class="field full"><label>Instruções específicas</label><textarea name="items[{{ $index }}][instructions]" maxlength="2000">{{ $item['instructions'] ?? '' }}</textarea></div>
        </div>
        <button class="button danger" type="button" data-prescription-remove-item>Remover item</button>
      </fieldset>
    @endforeach
  </div>

  <template data-prescription-item-template>
    <fieldset class="prescription-item" data-prescription-item>
      <legend>Item <span data-prescription-item-number></span></legend>
      <div class="form-grid">
        <div class="field"><label>Medicamento</label><input name="items[__INDEX__][medication_name]" maxlength="255" required></div>
        <div class="field"><label>Concentração/apresentação</label><input name="items[__INDEX__][concentration]" maxlength="255"></div>
        <div class="field"><label>Dose</label><input name="items[__INDEX__][dosage]" maxlength="255" required></div>
        <div class="field"><label>Via</label><input name="items[__INDEX__][route]" maxlength="255" placeholder="Ex.: oral"></div>
        <div class="field"><label>Frequência</label><input name="items[__INDEX__][frequency]" maxlength="255" required placeholder="Ex.: a cada 12 horas"></div>
        <div class="field"><label>Duração</label><input name="items[__INDEX__][duration]" maxlength="255" placeholder="Ex.: 7 dias"></div>
        <div class="field"><label>Quantidade</label><input name="items[__INDEX__][quantity]" maxlength="255"></div>
        <div class="field full"><label>Instruções específicas</label><textarea name="items[__INDEX__][instructions]" maxlength="2000"></textarea></div>
      </div>
      <button class="button danger" type="button" data-prescription-remove-item>Remover item</button>
    </fieldset>
  </template>

  <div class="field prescription-notes">
    <label for="notes">Observações internas</label>
    <textarea id="notes" name="notes">{{ old('notes', $prescription->notes ?? '') }}</textarea>
    <small>Este campo fica no histórico do sistema e não compõe as instruções impressas.</small>
  </div>

  <div class="actions prescription-form-actions">
    <button type="submit">Salvar rascunho</button>
    <a class="button secondary" href="{{ $prescription ? route('prescriptions.show', $prescription->id) : route('prescriptions.index') }}">Cancelar</a>
  </div>
</div>
