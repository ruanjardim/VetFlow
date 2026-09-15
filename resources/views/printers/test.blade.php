@extends('layouts.admin')

@section('title', 'Teste de impressão - VetFlow')

@section('content')
  <header class="topbar receipt-actions">
    <div>
      <h1>Teste de impressão</h1>
      <p>Confirme o equipamento e o tamanho de papel na janela do sistema.</p>
    </div>
    <div class="actions">
      <button type="button" data-print-page>Imprimir teste</button>
      <a class="button secondary" href="{{ route('printers.edit', $printer->id) }}">Editar configuração</a>
      <a class="button secondary" href="{{ route('printers.index') }}">Voltar</a>
    </div>
  </header>

  <div class="panel printer-test-card printer-paper-{{ $printer->paper_size }}">
    <div class="panel-body printer-test-sheet">
      <div class="printer-test-logo">VetFlow</div>
      <h2>Teste de impressão</h2>
      <p class="muted">Se este conteúdo estiver legível e alinhado, o fluxo de impressão pelo navegador está funcionando.</p>

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
      <p class="printer-test-time">Gerado em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
  </div>
@endsection
