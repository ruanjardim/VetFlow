@extends('layouts.admin')

@section('title', 'Gerar etiqueta por peso - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Gerar etiqueta por peso</h1>
      <p>Informe o produto e o peso líquido para imprimir um adesivo que o próprio PDV reconhece.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('sales.pdv-scale-settings.edit', $requiresClinic ? ['clinic_id' => $clinic->id] : []) }}">Configurar leitura</a>
      <a class="button secondary" href="{{ route('sales.create') }}" target="_blank" rel="noopener">Abrir PDV ↗</a>
    </div>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('sales.pdv-scale-labels.create') }}" class="panel panel-body">
      <div class="field">
        <label for="label-clinic-filter">Estabelecimento</label>
        <select id="label-clinic-filter" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $option)
            <option value="{{ $option->id }}" @selected($clinic->id === $option->id)>{{ $option->trade_name ?: $option->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  @unless($setting->enabled)
    <div class="alert warning">
      A leitura de etiquetas de peso está desativada. Salve a configuração antes de gerar uma etiqueta que possa ser lida no PDV.
    </div>
  @endunless

  <div class="grid cols-2 scale-label-workspace">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Dados da etiqueta</h2>
          <p>{{ $clinic->trade_name ?: $clinic->corporate_name }}</p>
        </div>
        <span class="badge {{ $setting->enabled ? 'success' : 'warning' }}">{{ $setting->enabled ? 'Pronta' : 'Configurar' }}</span>
      </div>
      <div class="panel-body">
        @if($products->isEmpty())
          <div class="alert warning">
            Nenhum produto está pronto para etiqueta. Cadastre-o em <strong>kg</strong>, informe o preço por quilo e um código de balança com exatamente {{ $setting->product_code_digits }} dígitos.
          </div>
        @endif

        <form method="GET" action="{{ route('sales.pdv-scale-labels.print') }}" target="_blank" rel="noopener" class="form-grid">
          <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
          <input type="hidden" name="autoprint" value="1">

          <div class="field full">
            <label for="scale-label-product">Ração ou produto</label>
            <select id="scale-label-product" name="product_id" required @disabled($products->isEmpty())>
              <option value="">Selecione o produto vendido por quilo</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}" @selected((int) old('product_id') === $product->id)>
                  {{ $product->name }} · cód. {{ $product->scale_code }} · R$ {{ number_format((float) $product->sale_price, 2, ',', '.') }}/kg · estoque {{ number_format((float) $product->stock_quantity, 3, ',', '.') }} kg
                </option>
              @endforeach
            </select>
            @if($unreadyProductsCount > 0)
              <small>{{ $unreadyProductsCount }} produto(s) por peso ficaram fora da lista por código incompatível ou preço zerado.</small>
            @endif
          </div>

          <div class="field">
            <label for="scale-label-weight">Peso líquido (kg)</label>
            <input id="scale-label-weight" name="weight" inputmode="decimal" value="{{ old('weight', '1,000') }}" placeholder="1,000" required>
            <small>Use até três casas decimais. Ex.: 0,500 ou 1,000.</small>
          </div>
          <div class="field">
            <label for="scale-label-copies">Quantidade de etiquetas</label>
            <input id="scale-label-copies" name="copies" type="number" min="1" max="50" value="{{ old('copies', 1) }}" required>
            <small>Cada cópia será enviada como uma página à impressora.</small>
          </div>

          <div class="field">
            <label for="scale-label-width">Largura da etiqueta (mm)</label>
            <input id="scale-label-width" name="label_width_mm" type="number" min="50" max="100" value="{{ old('label_width_mm', 60) }}" required>
          </div>
          <div class="field">
            <label for="scale-label-height">Altura da etiqueta (mm)</label>
            <input id="scale-label-height" name="label_height_mm" type="number" min="30" max="100" value="{{ old('label_height_mm', 40) }}" required>
          </div>

          <div class="field">
            <label for="scale-label-lot">Lote de origem (opcional)</label>
            <input id="scale-label-lot" name="lot" maxlength="80" value="{{ old('lot') }}" placeholder="Copie da embalagem original">
          </div>
          <div class="field">
            <label for="scale-label-expiration">Validade (opcional)</label>
            <input id="scale-label-expiration" name="expires_on" type="date" min="{{ today()->toDateString() }}" value="{{ old('expires_on') }}">
          </div>

          <div class="field full">
            <div class="actions">
              <button type="submit" @disabled(! $setting->enabled || $products->isEmpty())>Gerar e imprimir etiqueta</button>
              <a class="button secondary" href="{{ route('products.index') }}">Revisar produtos</a>
            </div>
          </div>
        </form>
      </div>
    </section>

    <aside class="panel">
      <div class="panel-heading"><div><h2>Impressão adesiva</h2><p>Fluxo controlado pelo navegador.</p></div></div>
      <div class="panel-body scale-label-guidance">
        @if($labelPrinter)
          <div class="stat">
            <span>Impressora de etiquetas cadastrada</span>
            <strong>{{ $labelPrinter->name }}</strong>
            <small>{{ $labelPrinter->queue_name ?: 'Selecione a fila correspondente na janela de impressão.' }}</small>
          </div>
        @else
          <div class="alert-soft">
            Nenhuma impressora de etiquetas está cadastrada. A geração funciona mesmo assim; selecione a impressora instalada quando o navegador abrir a janela do sistema.
          </div>
        @endif

        <ol>
          <li>Confirme o mesmo tamanho de etiqueta no VetFlow e no driver da impressora.</li>
          <li>Desative cabeçalhos e rodapés na janela de impressão.</li>
          <li>Mantenha a escala em 100% e as margens como nenhuma.</li>
          <li>Leia uma etiqueta no PDV e confira peso, preço e estoque antes do uso real.</li>
        </ol>

        <p class="muted">O código de barras leva produto e peso. O PDV aplica o preço por quilo vigente no cadastro. A etiqueta é operacional e não substitui o documento fiscal.</p>

        @can('printers.manage')
          <a class="button secondary" href="{{ route('printers.index', $requiresClinic ? ['clinic_id' => $clinic->id] : []) }}">Configurar impressoras</a>
        @endcan
      </div>
    </aside>
  </div>
@endsection
