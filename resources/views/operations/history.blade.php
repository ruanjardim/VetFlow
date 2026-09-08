@extends('layouts.admin')

@section('title', 'Histórico operacional - VetFlow')

@section('content')
  <section class="page-heading">
    <div>
      <span class="eyebrow">Auditoria por clínica</span>
      <h1>Histórico operacional</h1>
      <p>Consulte numa única linha do tempo os registros técnicos e as decisões armazenadas pelo VetFlow.</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('operations.index') }}">Voltar à central</a>
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <form method="GET" action="{{ route('operations.history') }}" class="form-grid compact-filter-grid">
        <div class="field">
          <label for="operations-history-type">Categoria</label>
          <select id="operations-history-type" name="type">
            <option value="">Todas as categorias</option>
            <option value="runtime" @selected($history['filters']['type'] === 'runtime')>Probe operacional</option>
            <option value="backup" @selected($history['filters']['type'] === 'backup')>Backup restaurável</option>
            <option value="smoke" @selected($history['filters']['type'] === 'smoke')>Smoke test</option>
            <option value="decision" @selected($history['filters']['type'] === 'decision')>Decisões da release</option>
          </select>
        </div>
        <div class="field">
          <label for="operations-history-release">Período da release</label>
          <select id="operations-history-release" name="release">
            <option value="current" @selected($history['filters']['release'] === 'current')>Release atual</option>
            <option value="all" @selected($history['filters']['release'] === 'all')>Todas as releases</option>
          </select>
        </div>
        <div class="field implementation-portfolio-filter-actions">
          <button type="submit">Aplicar filtros</button>
          <a class="button secondary" href="{{ route('operations.history') }}">Limpar</a>
        </div>
      </form>
    </div>
  </section>

  <section class="grid stats">
    <article class="stat"><span>Probes</span><strong>{{ $history['totals']['runtime'] }}</strong><small>eventos no escopo</small></article>
    <article class="stat"><span>Backups</span><strong>{{ $history['totals']['backup'] }}</strong><small>evidências importadas</small></article>
    <article class="stat"><span>Smoke test</span><strong>{{ $history['totals']['smoke'] }}</strong><small>decisões de itens</small></article>
    <article class="stat"><span>Releases</span><strong>{{ $history['totals']['decision'] }}</strong><small>decisões operacionais</small></article>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Linha do tempo</h2>
        <p>Até 100 eventos mais recentes. Notas livres, hashes, caminhos e conteúdo de evidências não são exibidos.</p>
      </div>
      <span class="badge">{{ $history['filters']['release'] === 'all' ? 'Todas as releases' : 'Release '.($history['current_release'] ? substr($history['current_release'], 0, 7) : 'indisponível') }}</span>
    </div>

    @if($history['items'] === [])
      <div class="panel-body"><p class="muted">Nenhum registro operacional foi encontrado para os filtros selecionados.</p></div>
    @else
      <div class="panel-body table-wrap">
        <table>
          <thead>
            <tr><th>Data e hora</th><th>Categoria</th><th>Evento</th><th>Detalhe seguro</th><th>Responsável</th><th>Release</th></tr>
          </thead>
          <tbody>
            @foreach($history['items'] as $item)
              <tr>
                <td>{{ $item['occurred_at']->format('d/m/Y H:i') }}</td>
                <td>{{ $item['type_label'] }}</td>
                <td><strong>{{ $item['action'] }}</strong></td>
                <td>{{ $item['summary'] }}</td>
                <td>{{ $item['actor'] ?? 'Operador removido' }}</td>
                <td><code>{{ $item['release_short'] }}</code></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>
@endsection
