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
        <span class="eyebrow">Evidências privadas</span>
        <h2>Backup e execução assíncrona</h2>
        <p>A tela mostra somente identificadores, horários e totais; caminhos, hashes e conteúdo permanecem privados.</p>
      </div>
    </div>

    <div class="panel-body grid stats">
      <article class="stat">
        <span>Restauração isolada</span>
        <strong>{{ $evidence['backup']['available'] ? ($evidence['backup']['identifier'] ?? 'Localizada') : 'Pendente' }}</strong>
        <small>
          @if($evidence['backup']['available'])
            {{ $evidence['backup']['checks'] }} verificações em
            {{ \Carbon\CarbonImmutable::parse($evidence['backup']['verified_at'])->format('d/m/Y H:i') }}
          @else
            Nenhuma evidência legível localizada.
          @endif
        </small>
      </article>

      <article class="stat">
        <span>Probe de fila e storage</span>
        <strong>{{ $evidence['runtime']['available'] ? ($evidence['runtime']['identifier'] ?? 'Localizado') : 'Pendente' }}</strong>
        <small>
          @if($evidence['runtime']['available'])
            {{ $evidence['runtime']['checks'] }} verificações em
            {{ \Carbon\CarbonImmutable::parse($evidence['runtime']['verified_at'])->format('d/m/Y H:i') }}
          @else
            Nenhuma evidência legível localizada.
          @endif
        </small>
      </article>
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

  <section class="panel">
    <div class="panel-heading">
      <div>
        <span class="eyebrow">Diagnóstico técnico</span>
        <h2>Portões da release</h2>
        <p>O mesmo diagnóstico usado no terminal agora pode ser revisado por administradores na interface.</p>
      </div>
      <span class="badge {{ $readiness['passed'] ? 'success' : 'danger' }}">
        {{ $readiness['passed'] ? 'Aprovado' : $readiness['failures'].' pendência(s)' }}
      </span>
    </div>

    <div class="panel-body table-wrap">
      <table>
        <thead>
          <tr>
            <th>Verificação</th>
            <th>Status</th>
            <th>Detalhe seguro</th>
          </tr>
        </thead>
        <tbody>
          @foreach($readiness['checks'] as $check)
            <tr>
              <td><strong>{{ $check['check'] }}</strong></td>
              <td>
                <span class="badge {{ $check['passed'] ? 'success' : 'danger' }}">
                  {{ $check['status'] }}
                </span>
              </td>
              <td>{{ $check['detail'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endsection
