@extends('layouts.admin')

@section('title', 'Sugestoes Intelligence - VetFlow')

@section('content')
  @php
    $statusBadges = [
      'VERIFIED' => 'success',
      'CONFLICT' => 'danger',
      'PENDING' => 'warning',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>Sugestoes Intelligence</h1>
      <p>Fila de revisao do Catalogo Global VetFlow.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('global-products.index') }}">Catalogo Global</a>
      <a class="button" href="{{ route('products.create') }}">Cadastrar produto</a>
    </div>
  </header>

  <div class="grid stats inventory-lot-stats">
    <a class="stat stat-link" href="{{ route('global-products.suggestions') }}">
      <span>Total</span>
      <strong>{{ $stats['total'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.suggestions', ['status' => 'PENDING']) }}">
      <span>Pendentes</span>
      <strong>{{ $stats['pending'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.suggestions', ['status' => 'VERIFIED']) }}">
      <span>Verificadas</span>
      <strong>{{ $stats['verified'] }}</strong>
    </a>
    <a class="stat stat-link" href="{{ route('global-products.suggestions', ['status' => 'CONFLICT']) }}">
      <span>Conflitos</span>
      <strong>{{ $stats['conflict'] }}</strong>
    </a>
  </div>

  <div class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('global-products.suggestions') }}" class="form-grid">
        <div class="field">
          <label for="q">Buscar</label>
          <input id="q" name="q" value="{{ request('q') }}" placeholder="GTIN, nome sugerido ou origem">
        </div>
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">Todos</option>
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="type">Tipo</label>
          <select id="type" name="type">
            <option value="">Todos</option>
            @foreach($types as $type)
              <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
            @endforeach
          </select>
        </div>
        <div class="field full">
          <div class="actions">
            <button type="submit">Filtrar</button>
            <a class="button secondary" href="{{ route('global-products.suggestions') }}">Limpar</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="panel nested-panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>GTIN / Tipo</th>
            <th>Sugestao</th>
            <th>Origem</th>
            <th>Confianca</th>
            <th>Status</th>
            <th>Atualizacao</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($suggestions as $suggestion)
            <tr>
              <td>
                <strong>{{ $suggestion->gtin ?: '-' }}</strong>
                <div class="muted">{{ $suggestion->suggestion_type }}</div>
              </td>
              <td>
                {{ $suggestion->suggested_name ?: ($suggestion->payload['message'] ?? '-') }}
                @if($suggestion->payload)
                  <details class="payload-details">
                    <summary>Ver dados</summary>
                    <pre>{{ json_encode($suggestion->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                  </details>
                @endif
              </td>
              <td>{{ $suggestion->source_name ?: '-' }}</td>
              <td>{{ number_format((float) $suggestion->confidence, 2, ',', '.') }}%</td>
              <td>
                <span class="badge {{ $statusBadges[$suggestion->status] ?? 'warning' }}">
                  {{ $statuses[$suggestion->status] ?? $suggestion->status }}
                </span>
              </td>
              <td>{{ $suggestion->updated_at->format('d/m/Y H:i') }}</td>
              <td>
                <div class="actions">
                  @if($suggestion->gtin)
                    <a class="button secondary" href="{{ route('products.create', ['gtin' => $suggestion->gtin]) }}">Cadastrar</a>
                    <a class="button secondary" href="{{ route('global-products.index', ['q' => $suggestion->gtin]) }}">Buscar global</a>
                  @endif
                  <form class="inline" method="POST" action="{{ route('global-products.suggestions.review', $suggestion->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="VERIFIED">
                    <button class="secondary" type="submit">Revisada</button>
                  </form>
                  <form class="inline" method="POST" action="{{ route('global-products.suggestions.review', $suggestion->id) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="CONFLICT">
                    <button class="danger" type="submit">Conflito</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhuma sugestao encontrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $suggestions->links() }}
@endsection
