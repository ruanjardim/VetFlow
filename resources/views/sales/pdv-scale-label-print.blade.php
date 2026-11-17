@extends('layouts.admin')

@section('title', 'Etiqueta de '.$product->name.' - VetFlow')

@push('head')
  <style>
    @page weight-label {
      size: {{ $labelWidthMm }}mm {{ $labelHeightMm }}mm;
      margin: 0;
    }

    .weight-label-sheet {
      height: {{ $labelHeightMm }}mm;
      width: {{ $labelWidthMm }}mm;
    }
  </style>
@endpush

@section('content')
  <header class="topbar receipt-actions">
    <div>
      <h1>Etiqueta pronta</h1>
      <p>{{ $copies }} cópia(s) · {{ $labelWidthMm }} × {{ $labelHeightMm }} mm · selecione a impressora de etiquetas na janela do sistema.</p>
    </div>
    <div class="actions">
      <button type="button" data-print-page>Imprimir novamente</button>
      <a class="button secondary" href="{{ route('sales.pdv-scale-labels.create', auth()->user()?->clinic_id ? [] : ['clinic_id' => $clinic->id]) }}">Gerar outra</a>
    </div>
  </header>

  <div class="alert info scale-label-print-help">
    Use escala de <strong>100%</strong>, margens <strong>nenhuma</strong> e desative os cabeçalhos e rodapés do navegador.
    @if($labelPrinter)
      Fila cadastrada: <strong>{{ $labelPrinter->queue_name ?: $labelPrinter->name }}</strong>.
    @endif
  </div>

  <div class="weight-label-print" aria-label="Etiquetas por peso">
    @for($copy = 1; $copy <= $copies; $copy++)
      <article class="weight-label-sheet" data-weight-label-copy="{{ $copy }}" data-ean13="{{ $ean13 }}">
        <header class="weight-label-header">
          <strong>{{ $clinic->trade_name ?: $clinic->corporate_name }}</strong>
          @if($clinic->cnpj)<span>{{ $clinic->formattedDocument() }}</span>@endif
        </header>

        <div class="weight-label-product">{{ $product->name }}</div>

        <div class="weight-label-prices">
          <div><span>PESO LÍQUIDO</span><strong>{{ number_format($weight, 3, ',', '.') }} kg</strong></div>
          <div><span>PREÇO POR KG</span><strong>R$ {{ number_format($unitPrice, 2, ',', '.') }}</strong></div>
          <div class="weight-label-total"><span>TOTAL</span><strong>R$ {{ number_format($totalPrice, 2, ',', '.') }}</strong></div>
        </div>

        <div class="weight-label-barcode">
          <svg viewBox="0 0 117 41" role="img" aria-label="Código EAN-13 {{ $ean13 }}" preserveAspectRatio="none" shape-rendering="crispEdges">
            <rect width="117" height="41" fill="#fff" />
            @foreach(str_split($barcodeModules) as $index => $module)
              @if($module === '1')
                <rect x="{{ 11 + $index }}" y="0" width="1" height="{{ in_array($index, [0, 2, 46, 48, 92, 94], true) ? 39 : 34 }}" fill="#000" />
              @endif
            @endforeach
          </svg>
          <strong>{{ substr($ean13, 0, 1) }} {{ substr($ean13, 1, 6) }} {{ substr($ean13, 7, 6) }}</strong>
        </div>

        <footer class="weight-label-footer">
          <span>Cód. {{ $product->scale_code }}</span>
          @if($lot)<span>Lote: {{ $lot }}</span>@endif
          @if($expiresOn)<span>Val.: {{ $expiresOn->format('d/m/Y') }}</span>@endif
          <span>Emitida {{ now()->format('d/m/Y H:i') }}</span>
        </footer>
      </article>
    @endfor
  </div>

  @if($autoprint)
    <script>
      window.addEventListener('load', () => window.print(), { once: true });
    </script>
  @endif
@endsection
