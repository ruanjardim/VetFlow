@php($selectedConnection = old('connection_type', $printer?->connection_type ?? 'browser'))

<div class="form-grid" data-printer-form>
  @if($requiresClinic)
    <div class="field full">
      <label for="clinic_id">Estabelecimento</label>
      @if($printer)
        <input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">
        <input value="{{ $clinics->firstWhere('id', $selectedClinicId)?->trade_name ?: $clinics->firstWhere('id', $selectedClinicId)?->corporate_name }}" disabled>
      @else
        <select id="clinic_id" name="clinic_id" required>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected((int) old('clinic_id', $selectedClinicId) === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      @endif
    </div>
  @endif
  <div class="field">
    <label for="name">Nome de identificação</label>
    <input id="name" name="name" value="{{ old('name', $printer?->name) }}" placeholder="Ex.: Térmica do caixa" maxlength="120" required autofocus>
  </div>
  <div class="field">
    <label for="type">Tipo de impressora</label>
    <select id="type" name="type" required>
      @foreach($types as $value => $label)
        <option value="{{ $value }}" @selected(old('type', $printer?->type ?? 'non_fiscal') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="purpose">Finalidade principal</label>
    <select id="purpose" name="purpose" required>
      @foreach($purposes as $value => $label)
        <option value="{{ $value }}" @selected(old('purpose', $printer?->purpose ?? 'receipt') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="paper_size">Papel</label>
    <select id="paper_size" name="paper_size" required>
      @foreach($paperSizes as $value => $label)
        <option value="{{ $value }}" @selected(old('paper_size', $printer?->paper_size ?? '80mm') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="connection_type">Conexão</label>
    <select id="connection_type" name="connection_type" data-printer-connection required>
      @foreach($connections as $value => $label)
        <option value="{{ $value }}" @selected($selectedConnection === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="field">
    <label for="queue_name">Nome no computador ou fila</label>
    <input id="queue_name" name="queue_name" value="{{ old('queue_name', $printer?->queue_name) }}" placeholder="Ex.: EPSON TM-T20X Caixa 1" maxlength="160">
    <small class="muted">Use o mesmo nome mostrado nas impressoras do Windows, macOS ou serviço de impressão.</small>
  </div>
  <div class="field" data-printer-network-field @if($selectedConnection !== 'network') hidden @endif>
    <label for="network_host">Endereço IP ou host</label>
    <input id="network_host" name="network_host" value="{{ old('network_host', $printer?->network_host) }}" placeholder="Ex.: 192.168.1.50">
  </div>
  <div class="field" data-printer-network-field @if($selectedConnection !== 'network') hidden @endif>
    <label for="network_port">Porta de rede</label>
    <input id="network_port" name="network_port" type="number" min="1" max="65535" value="{{ old('network_port', $printer?->network_port) }}" placeholder="Ex.: 9100">
  </div>
  <div class="field">
    <label for="manufacturer">Fabricante</label>
    <input id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $printer?->manufacturer) }}" placeholder="Ex.: Epson" maxlength="100">
  </div>
  <div class="field">
    <label for="model">Modelo</label>
    <input id="model" name="model" value="{{ old('model', $printer?->model) }}" placeholder="Ex.: TM-T20X" maxlength="120">
  </div>
  <div class="field">
    <label for="is_default">Uso padrão</label>
    <select id="is_default" name="is_default" required>
      <option value="0" @selected(! old('is_default', $printer?->is_default ?? false))>Não é a impressora padrão</option>
      <option value="1" @selected(old('is_default', $printer?->is_default ?? false))>Usar como impressora padrão</option>
    </select>
    <small class="muted">Ao marcar, a impressora padrão anterior deixa de ser a padrão.</small>
  </div>
  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active" required>
      <option value="1" @selected(old('active', $printer?->active ?? true))>Ativa</option>
      <option value="0" @selected(! old('active', $printer?->active ?? true))>Inativa</option>
    </select>
  </div>
  <div class="field full">
    <label for="notes">Observações de instalação</label>
    <textarea id="notes" name="notes" maxlength="1000" placeholder="Ex.: instalada no caixa principal; driver disponível no computador da recepção.">{{ old('notes', $printer?->notes) }}</textarea>
  </div>
  <div class="field full">
    <div class="alert-soft printer-fiscal-note">
      <span>Para impressoras fiscais, registre aqui o equipamento usado. A emissão fiscal exige integração homologada, certificado e regras tributárias próprias; este cadastro não substitui esses componentes.</span>
    </div>
  </div>
  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar impressora</button>
      <a class="button secondary" href="{{ route('printers.index') }}">Cancelar</a>
    </div>
  </div>
</div>
