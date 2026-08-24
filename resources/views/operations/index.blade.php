@extends('layouts.admin')

@section('title', 'Central de operações - VetFlow')

@section('content')
  <section class="page-heading">
    <div>
      <h1>Central de operações</h1>
      <p>Confira a identidade publicada e o contexto técnico seguro antes de executar o roteiro de liberação.</p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <span class="eyebrow">Release publicada</span>
        <h2>Identidade do ambiente</h2>
        <p>Somente informações operacionais não sensíveis são apresentadas nesta tela.</p>
      </div>
    </div>

    <div class="panel-body">
      <dl class="definition-grid">
        <div>
          <dt>Status da identidade</dt>
          <dd>{{ $releaseAvailable ? 'Identificada' : 'Não identificada' }}</dd>
        </div>
        <div>
          <dt>Commit publicado</dt>
          <dd>{{ $release['short_sha'] ?? 'Indisponível' }}</dd>
        </div>
        <div>
          <dt>Ambiente</dt>
          <dd>{{ $environment }}</dd>
        </div>
        <div>
          <dt>Processamento da fila</dt>
          <dd>{{ $queueMode }} / {{ $queueConnection }}</dd>
        </div>
        <div>
          <dt>Armazenamento padrão</dt>
          <dd>{{ $storageDisk }}</dd>
        </div>
      </dl>
    </div>
  </section>
@endsection
