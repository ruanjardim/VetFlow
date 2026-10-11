@extends('layouts.admin')

@section('title', 'Documentos fiscais - VetFlow')

@section('content')
  @php
    $money = fn ($cents) => 'R$ '.number_format(((int) $cents) / 100, 2, ',', '.');
    $exportQuery = array_filter([
      'clinic_id' => $requiresClinic ? $selectedClinicId : null,
      'competence' => $filters['competence'],
      'from' => $filters['from'],
      'to' => $filters['to'],
      'type' => $filters['type'],
      'status' => $filters['status'],
      'sale' => $filters['sale'],
    ], fn ($value) => $value !== null && $value !== '');
  @endphp

  <header class="topbar">
    <div>
      <h1>Central de documentos fiscais</h1>
      <p>{{ $clinic->trade_name ?: $clinic->corporate_name }} · competência {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $filters['competence'])->translatedFormat('F/Y') }}</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('fiscal.documents.export', $exportQuery) }}">Exportar conferência CSV</a>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.index', array_filter(['clinic_id' => $requiresClinic ? $selectedClinicId : null, 'from' => $filters['from'], 'to' => $filters['to']])) }}">Abrir conciliação</a>
      <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar ao Fiscal</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Competência operacional:</strong> esta consulta usa a data em que a preparação fiscal foi criada no VetFlow. Ela não define competência tributária, escrituração ou obrigação de emissão. O CSV contém somente campos de conferência permitidos e nunca inclui payloads, respostas brutas ou credenciais do provedor.
  </div>

  <form method="GET" action="{{ route('fiscal.documents.index') }}" class="panel panel-body filter-grid fiscal-document-filter-grid">
    @if($requiresClinic)
      <div class="field">
        <label for="document_center_clinic">Estabelecimento</label>
        <select id="document_center_clinic" name="clinic_id">
          @foreach($clinics as $availableClinic)
            <option value="{{ $availableClinic->id }}" @selected($selectedClinicId === $availableClinic->id)>{{ $availableClinic->trade_name ?: $availableClinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="field">
      <label for="document_center_competence">Competência</label>
      <input id="document_center_competence" name="competence" type="month" value="{{ $filters['competence'] }}" required>
      @error('competence')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="document_center_from">De</label>
      <input id="document_center_from" name="from" type="date" value="{{ $filters['from'] }}" required>
      @error('from')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="document_center_to">Até</label>
      <input id="document_center_to" name="to" type="date" value="{{ $filters['to'] }}" required>
      @error('to')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
      <label for="document_center_type">Tipo</label>
      <select id="document_center_type" name="type">
        <option value="">NFC-e e NFS-e</option>
        @foreach($types as $typeKey => $typeLabel)
          <option value="{{ $typeKey }}" @selected($filters['type'] === $typeKey)>{{ $typeLabel }}</option>
        @endforeach
      </select>
    </div>
    <div class="field">
      <label for="document_center_status">Situação</label>
      <select id="document_center_status" name="status">
        <option value="">Todas</option>
        @foreach($statusLabels as $statusKey => $statusLabel)
          <option value="{{ $statusKey }}" @selected($filters['status'] === $statusKey)>{{ $statusLabel }}</option>
        @endforeach
      </select>
    </div>
    <div class="field">
      <label for="document_center_sale">Venda</label>
      <input id="document_center_sale" name="sale" value="{{ $filters['sale'] }}" maxlength="80" placeholder="Código ou ID da venda">
    </div>
    <div class="field">
      <div class="row-actions">
        <button type="submit">Pesquisar</button>
        <a class="button secondary" href="{{ route('fiscal.documents.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Limpar</a>
      </div>
    </div>
  </form>

  <section class="grid stats">
    <div class="stat"><span>Documentos encontrados</span><strong>{{ $summary['total'] }}</strong><span class="muted">{{ $money($summary['total_cents']) }}</span></div>
    <div class="stat"><span>NFC-e</span><strong>{{ $summary['nfce'] }}</strong></div>
    <div class="stat"><span>NFS-e</span><strong>{{ $summary['nfse'] }}</strong></div>
    <div class="stat"><span>Autorizados</span><strong>{{ $summary['authorized'] }}</strong></div>
    <div class="stat"><span>Em andamento</span><strong>{{ $summary['pending'] }}</strong></div>
    <div class="stat"><span>Com falha ou rejeição</span><strong>{{ $summary['issues'] }}</strong></div>
    <div class="stat"><span>Cancelados</span><strong>{{ $summary['cancelled'] }}</strong></div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Documentos da competência</h2>
        <p>{{ $documents->total() }} resultado(s) nos filtros atuais · {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') }}.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Preparação</th>
            <th>Tipo</th>
            <th>Venda</th>
            <th>Situação</th>
            <th>Número / chave</th>
            <th>Valor</th>
            <th>Responsável</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($documents as $document)
            <tr>
              <td>
                <strong>#{{ $document->id }}</strong>
                <div class="muted">{{ $document->created_at?->format('d/m/Y H:i') }}</div>
              </td>
              <td>{{ $types[$document->type] ?? strtoupper($document->type) }}<div class="muted">{{ $document->environment === 'homologation' ? 'Homologação' : ucfirst($document->environment) }}</div></td>
              <td>
                <strong>{{ $document->sale?->code ?? 'Venda removida' }}</strong>
                @if($document->sale?->completed_at)<div class="muted">Concluída em {{ $document->sale->completed_at->format('d/m/Y H:i') }}</div>@endif
              </td>
              <td><span class="badge {{ $documentCenter->statusSeverity($document->status) }}">{{ $documentCenter->statusLabel($document->status) }}</span></td>
              <td>
                <strong>{{ $document->document_number ?: 'Ainda não numerado' }}</strong>
                @if($document->series)<div class="muted">Série {{ $document->series }}</div>@endif
                @if($document->access_key)<div class="muted" title="{{ $document->access_key }}">Chave {{ \Illuminate\Support\Str::limit($document->access_key, 22) }}</div>@endif
              </td>
              <td>{{ $money($documentCenter->totalCents($document)) }}</td>
              <td>{{ $document->creator?->name ?? 'Sistema' }}</td>
              <td>
                <div class="row-actions">
                  <a class="button secondary" href="{{ route('fiscal.documents.show', $document->id) }}">Revisar</a>
                  @if($document->sale)
                    <a class="button secondary" href="{{ route('sales.receipt', $document->sale->id) }}">Venda</a>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted">Nenhum documento fiscal foi encontrado para a competência e os filtros informados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($documents->hasPages())
      <div class="panel-body">{{ $documents->links() }}</div>
    @endif
  </section>
@endsection
