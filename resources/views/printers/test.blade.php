@extends('layouts.admin')

@section('title', 'Teste de impressão - VetFlow')

@section('content')
  <header class="topbar receipt-actions">
    <div>
      <h1>Teste de impressão</h1>
      <p>O navegador abrirá a janela do sistema; selecione a fila cadastrada para a impressora térmica.</p>
    </div>
    <div class="actions">
      <button type="button" data-print-page>Imprimir teste</button>
      <a class="button secondary" href="{{ route('printers.edit', array_filter(['printer' => $printer->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Editar configuração</a>
      <a class="button secondary" href="{{ route('printers.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar</a>
    </div>
  </header>

  <div class="alert info printer-browser-help">
    Na primeira impressão, abra <strong>Mais definições</strong>, confirme o papel de 80 mm da Epson e desative
    <strong>Cabeçalhos e rodapés</strong>. Essa opção do navegador é o que remove a URL e o número da página.
  </div>

  <div class="panel printer-test-card thermal-print-document printer-paper-{{ $printer->paper_size }}">
    <div class="panel-body printer-test-sheet">
      <div class="printer-test-logo">VetFlow</div>
      <h2>Teste de impressão</h2>
      <p class="printer-test-copy">Se este conteúdo estiver legível, centralizado e sem cortes laterais, a bobina está configurada corretamente.</p>

      <dl class="printer-test-details">
        <div><dt>Impressora</dt><dd>{{ $printer->name }}</dd></div>
        <div><dt>Tipo</dt><dd>{{ $types[$printer->type] ?? $printer->type }}</dd></div>
        <div><dt>Finalidade</dt><dd>{{ $purposes[$printer->purpose] ?? $printer->purpose }}</dd></div>
        <div><dt>Conexão</dt><dd>{{ $connections[$printer->connection_type] ?? $printer->connection_type }}</dd></div>
        <div><dt>Papel</dt><dd>{{ $paperSizes[$printer->paper_size] ?? $printer->paper_size }}</dd></div>
        <div><dt>Fila</dt><dd>{{ $printer->queue_name ?: 'Selecionar na janela de impressão' }}</dd></div>
      </dl>

      <div class="printer-test-bars" aria-hidden="true">
        @for($i = 0; $i < 18; $i++)<span></span>@endfor
      </div>
      <p class="printer-test-characters">Á É Í Ó Ú · Ç · 1234567890 · R$ 10,00</p>
      <p class="printer-test-time">Gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
  </div>
@endsection
