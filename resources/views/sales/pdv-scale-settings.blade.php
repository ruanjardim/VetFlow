@extends('layouts.admin')

@section('title', 'Balança e etiquetas - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Balança e etiquetas de peso</h1>
      <p>Configure a leitura segura de etiquetas EAN-13 e gere adesivos de peso pelo próprio VetFlow.</p>
    </div>
    <div class="actions">
      <a class="button" href="{{ route('sales.pdv-scale-labels.create', $requiresClinic ? ['clinic_id' => $clinic->id] : []) }}">Gerar etiqueta</a>
      <a class="button secondary" href="{{ route('sales.create') }}" target="_blank" rel="noopener">Abrir PDV ↗</a>
    </div>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('sales.pdv-scale-settings.edit') }}" class="panel panel-body">
      <div class="field">
        <label for="scale-clinic-filter">Estabelecimento</label>
        <select id="scale-clinic-filter" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $option)
            <option value="{{ $option->id }}" @selected($clinic->id === $option->id)>{{ $option->trade_name ?: $option->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="alert-soft">
    <span>
      <strong>Compatibilidade comercial:</strong> o VetFlow gera uma etiqueta EAN-13 de peso para impressão pelo navegador e também lê etiquetas compatíveis produzidas pela própria balança. O leitor USB deve operar como teclado e enviar Enter. Conexão serial direta exige driver e protocolo específicos do fabricante.
    </span>
  </div>

  <div class="grid cols-2">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Formato da etiqueta</h2>
          <p>{{ $clinic->trade_name ?: $clinic->corporate_name }}</p>
        </div>
        <span class="badge {{ $setting->enabled ? 'success' : 'warning' }}">{{ $setting->enabled ? 'Ativa' : 'Desativada' }}</span>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('sales.pdv-scale-settings.update') }}" class="form-grid">
          @csrf
          @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">

          <div class="field full">
            <label for="scale-enabled">Leitura no PDV</label>
            <select id="scale-enabled" name="enabled">
              <option value="1" @selected(old('enabled', $setting->enabled) == 1)>Ativada</option>
              <option value="0" @selected(old('enabled', $setting->enabled) == 0)>Desativada</option>
            </select>
          </div>
          <div class="field">
            <label for="label-prefix">Prefixo da balança</label>
            <input id="label-prefix" name="label_prefix" inputmode="numeric" maxlength="4" value="{{ old('label_prefix', $setting->label_prefix) }}" required>
            <small>Exemplo comum: 20. Copie exatamente o início da etiqueta.</small>
          </div>
          <div class="field">
            <label for="product-code-digits">Dígitos do produto</label>
            <input id="product-code-digits" name="product_code_digits" type="number" min="1" max="8" value="{{ old('product_code_digits', $setting->product_code_digits) }}" required>
            <small>Quantidade de dígitos logo após o prefixo.</small>
          </div>
          <div class="field">
            <label for="weight-decimals">Casas decimais do peso</label>
            <select id="weight-decimals" name="weight_decimals">
              @foreach([0, 1, 2, 3] as $decimals)
                <option value="{{ $decimals }}" @selected((int) old('weight_decimals', $setting->weight_decimals) === $decimals)>{{ $decimals }}</option>
              @endforeach
            </select>
            <small>Para peso em kg, normalmente são 3.</small>
          </div>
          <div class="field">
            <label>Dígitos calculados do peso</label>
            <input value="{{ $valueDigits }}" disabled>
            <small>O último dígito da etiqueta é o verificador EAN-13.</small>
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Salvar configuração</button>
              <a class="button secondary" href="{{ route('products.index') }}">Abrir produtos</a>
            </div>
          </div>
        </form>
      </div>
    </section>

    <section class="panel">
      <div class="panel-heading"><div><h2>Como cadastrar</h2><p>Fluxo sem risco de confundir EAN de fábrica com etiqueta de peso.</p></div></div>
      <div class="panel-body">
        <ol>
          <li>Cadastre o produto com unidade <strong>kg</strong> e preço de venda por quilo.</li>
          <li>No produto, informe o <strong>código da balança</strong> com zeros à esquerda.</li>
          <li>Gere uma etiqueta no VetFlow ou imprima uma amostra na balança e confira prefixo, código e peso.</li>
          <li>Leia no PDV: o peso entra como quantidade e o estoque baixa com três casas decimais.</li>
        </ol>
        <div class="stat">
          <span>Etiqueta de exemplo para código 1 e 1,250 kg</span>
          <strong>{{ $exampleLabel }}</strong>
        </div>
        <p class="muted">Este número demonstra a divisão dos campos. A tela <strong>Gerar etiqueta</strong> produz o adesivo completo; uma balança etiquetadora compatível também pode produzir o mesmo EAN-13.</p>
      </div>
    </section>
  </div>
@endsection
