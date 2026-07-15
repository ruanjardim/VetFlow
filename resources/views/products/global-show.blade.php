@extends('layouts.admin')

@section('title', 'Produto Global - VetFlow')

@section('content')
  @php
    $statusBadges = [
      'VERIFIED' => 'success',
      'CONFLICT' => 'danger',
      'PENDING' => 'warning',
    ];
    $imageSrc = $product->image_path
      ? route('products.lookup-image', ['filename' => basename($product->image_path)])
      : $product->image_url;
  @endphp

  <header class="topbar">
    <div>
      <h1>{{ $product->name ?: 'Produto global' }}</h1>
      <p>{{ $product->gtin }} - {{ $product->brand ?: 'Sem marca' }}</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('global-products.index') }}">Voltar</a>
      <form class="inline" method="POST" action="{{ route('global-products.enrich', $product->id) }}">
        @csrf
        <button type="submit">Enriquecer agora</button>
      </form>
    </div>
  </header>

  <div class="content-grid">
    <div class="panel">
      <div class="panel-body global-product-hero">
        <div class="global-product-media">
          @if($imageSrc)
            <img src="{{ $imageSrc }}" alt="Foto de {{ $product->name }}">
          @else
            <span>Sem imagem</span>
          @endif
        </div>
        <div class="global-product-summary">
          <div class="actions">
            <span class="badge {{ $statusBadges[$product->status] ?? 'warning' }}">{{ $statuses[$product->status] ?? $product->status }}</span>
            <span class="badge muted-badge">Qualidade {{ $product->quality_score }}%</span>
            <span class="badge muted-badge">{{ $product->quality_status }}</span>
          </div>
          <dl class="definition-grid">
            <div>
              <dt>GTIN</dt>
              <dd>{{ $product->gtin }}</dd>
            </div>
            <div>
              <dt>Marca</dt>
              <dd>{{ $product->brand ?: '-' }}</dd>
            </div>
            <div>
              <dt>Fabricante</dt>
              <dd>{{ $product->manufacturer ?: '-' }}</dd>
            </div>
            <div>
              <dt>Categoria</dt>
              <dd>{{ $product->category ?: '-' }}</dd>
            </div>
            <div>
              <dt>Peso / volume</dt>
              <dd>{{ $product->weight ?: '-' }}</dd>
            </div>
            <div>
              <dt>Unidade</dt>
              <dd>{{ $product->unit ?: '-' }}</dd>
            </div>
            <div>
              <dt>Fonte principal</dt>
              <dd>{{ $product->api_source ?: 'vetflow' }}</dd>
            </div>
            <div>
              <dt>Confianca</dt>
              <dd>{{ number_format((float) $product->source_confidence, 2, ',', '.') }}%</dd>
            </div>
          </dl>
          @if($product->description)
            <p>{{ $product->description }}</p>
          @endif
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Acoes</h2>
          <p>Controle de qualidade e uso local.</p>
        </div>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('global-products.status', $product->id) }}" class="form-grid compact-form">
          @csrf
          @method('PATCH')
          <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
              @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected($product->status === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field full">
            <label for="review_note">Nota de revisao</label>
            <textarea id="review_note" name="review_note"></textarea>
          </div>
          <div class="field full">
            <button type="submit">Atualizar status</button>
          </div>
        </form>

        <form method="POST" action="{{ route('global-products.promote', $product->id) }}" class="form-grid compact-form nested-panel">
          @csrf
          <div class="field">
            <label for="sale_price">Preco de venda</label>
            <input id="sale_price" name="sale_price" type="text" inputmode="decimal" placeholder="0,00" data-money-input>
          </div>
          <div class="field">
            <label for="cost_price">Custo</label>
            <input id="cost_price" name="cost_price" type="text" inputmode="decimal" placeholder="0,00" data-money-input>
          </div>
          <div class="field">
            <label for="stock_quantity">Estoque</label>
            <input id="stock_quantity" name="stock_quantity" type="number" min="0" step="0.001" value="0">
          </div>
          <div class="field">
            <label for="minimum_stock">Minimo</label>
            <input id="minimum_stock" name="minimum_stock" type="number" min="0" step="0.001" value="0">
          </div>
          <div class="field full">
            <button type="submit">Criar produto local</button>
          </div>
        </form>

        <form method="POST" action="{{ route('global-products.sync-local', $product->id) }}" class="nested-panel">
          @csrf
          <button class="secondary" type="submit">Sincronizar locais vinculados</button>
        </form>
      </div>
    </div>
  </div>

  @if($qualityFlags !== [])
    <div class="panel nested-panel">
      <div class="panel-heading">
        <div>
          <h2>Pendencias de qualidade</h2>
          <p>Campos que ainda reduzem a confiabilidade do cadastro global.</p>
        </div>
      </div>
      <div class="panel-body">
        <div class="badge-list">
          @foreach($qualityFlags as $flag)
            <span class="badge warning">{{ $flag }}</span>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <div class="content-grid nested-panel">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Fontes consultadas</h2>
          <p>Origem, confianca e ultimo retorno bruto.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Fonte</th>
              <th>Tipo</th>
              <th>Confianca</th>
              <th>Status</th>
              <th>Consulta</th>
            </tr>
          </thead>
          <tbody>
            @forelse($product->sources as $source)
              <tr>
                <td>
                  <strong>{{ $source->source_label ?: $source->source_name }}</strong>
                  <div class="muted">{{ $source->source_name }}</div>
                </td>
                <td>{{ $source->source_type }}</td>
                <td>{{ number_format((float) $source->confidence, 2, ',', '.') }}%</td>
                <td>{{ $source->status }}</td>
                <td>{{ optional($source->queried_at)->format('d/m/Y H:i') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="muted">Nenhuma fonte registrada ainda.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Produtos locais</h2>
          <p>Cadastros da clinica vinculados a este produto global.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Produto</th>
              <th>Venda</th>
              <th>Estoque</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            @forelse($product->products as $local)
              <tr>
                <td>
                  <strong>{{ $local->name }}</strong>
                  <div class="muted">{{ $local->sku ?: $local->gtin }}</div>
                </td>
                <td>R$ {{ number_format((float) $local->sale_price, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $local->stock_quantity, 3, ',', '.') }} {{ $local->unit }}</td>
                <td><a class="button secondary" href="{{ route('products.edit', $local->id) }}">Editar</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhum produto local vinculado.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="content-grid nested-panel">
    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Imagens</h2>
          <p>Registros coletados para este GTIN.</p>
        </div>
      </div>
      <div class="panel-body image-gallery">
        @forelse($product->images as $image)
          @php($src = $image->image_path ? route('products.lookup-image', ['filename' => basename($image->image_path)]) : $image->image_url)
          <div class="image-tile">
            @if($src)
              <img src="{{ $src }}" alt="Imagem do produto">
            @else
              <span>Sem arquivo</span>
            @endif
            <strong>{{ $image->image_type }}</strong>
            <span>{{ $image->source_name ?: 'vetflow' }} - {{ number_format((float) $image->confidence, 2, ',', '.') }}%</span>
          </div>
        @empty
          <span class="muted">Nenhuma imagem registrada.</span>
        @endforelse
      </div>
    </div>

    <div class="panel">
      <div class="panel-heading">
        <div>
          <h2>Regulatorio</h2>
          <p>Base para medicamentos, vacinas e antiparasitarios.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Registro</th>
              <th>Principio ativo</th>
              <th>Forma</th>
              <th>Receita</th>
            </tr>
          </thead>
          <tbody>
            @forelse($product->regulatoryData as $regulatory)
              <tr>
                <td>{{ $regulatory->registration_number ?: '-' }}</td>
                <td>
                  {{ $regulatory->active_ingredient ?: '-' }}
                  <div class="muted">{{ $regulatory->dosage ?: $regulatory->concentration }}</div>
                </td>
                <td>
                  {{ $regulatory->pharmaceutical_form ?: '-' }}
                  <div class="muted">{{ $regulatory->storage_temperature ?: '-' }}</div>
                </td>
                <td>{{ $regulatory->prescription_required ? 'Sim' : 'Nao informado' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Nenhum dado regulatorio registrado.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @if($suggestions->isNotEmpty())
    <div class="panel nested-panel">
      <div class="panel-heading">
        <div>
          <h2>Sugestoes relacionadas</h2>
          <p>Ocorrencias pendentes para este GTIN.</p>
        </div>
        <a class="button secondary" href="{{ route('global-products.suggestions', ['q' => $product->gtin]) }}">Ver fila</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Origem</th>
              <th>Status</th>
              <th>Atualizacao</th>
            </tr>
          </thead>
          <tbody>
            @foreach($suggestions as $suggestion)
              <tr>
                <td>{{ $suggestion->suggestion_type }}</td>
                <td>{{ $suggestion->source_name ?: '-' }}</td>
                <td>{{ $statuses[$suggestion->status] ?? $suggestion->status }}</td>
                <td>{{ $suggestion->updated_at->format('d/m/Y H:i') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
