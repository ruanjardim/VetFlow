@extends('layouts.admin')

@section('title', 'Central de operações - VetFlow')

@section('content')
  <section class="page-heading">
    <div>
      <h1>Central de operações</h1>
      <p>Confira a identidade publicada e o contexto técnico seguro antes de executar o roteiro de liberação.</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('operations.report') }}">Emitir relatório</a>
      <a class="button secondary" href="{{ route('operations.report.json') }}">Baixar JSON</a>
    </div>
  </section>

  <section class="panel implementation-pilot-readiness">
    <div class="panel-body">
      <div class="implementation-readiness-header">
        <div>
          <span class="eyebrow">Decisão consolidada</span>
          <h2>Prontidão operacional da release</h2>
          <p class="muted">{{ $state['status']['description'] }}</p>
        </div>
        <span class="implementation-readiness-status">{{ $state['status']['label'] }}</span>
      </div>

      <div class="implementation-readiness-gates">
        @foreach($state['gates'] as $gate)
          <div class="implementation-readiness-gate {{ $gate['passed'] ? 'passed' : 'pending' }}">
            <span aria-hidden="true">{{ $gate['passed'] ? '✓' : '○' }}</span>
            <div>
              <strong>{{ $gate['label'] }}</strong>
              <small>{{ $gate['summary'] }}</small>
            </div>
          </div>
        @endforeach
      </div>

      @if($state['decision'])
        <div class="alert-soft">
          <div>
            <strong>
              Última decisão: {{ $state['decision']['decision'] === 'approved' ? 'Aprovada' : 'Em espera' }}
              {{ $state['decision']['current'] ? '' : '(superada)' }}
            </strong>
            <span>
              por {{ $state['decision']['actor'] ?? 'Operador removido' }} em
              {{ $state['decision']['decided_at']->format('d/m/Y H:i') }}
            </span>
            @if($state['decision']['note'])<span>{{ $state['decision']['note'] }}</span>@endif
          </div>
        </div>
      @endif

      <form method="POST" action="{{ route('operations.decision.store') }}" class="form-grid compact-filter-grid">
        @csrf
        <div class="field">
          <label for="operations-decision">Decisão</label>
          <select id="operations-decision" name="decision" required @disabled(!$releaseAvailable)>
            <option value="held" @selected(old('decision') === 'held')>Manter release em espera</option>
            <option value="approved" @selected(old('decision') === 'approved') @disabled(!$state['gates_passed'])>
              Aprovar release
            </option>
          </select>
        </div>
        <div class="field">
          <label for="operations-decision-note">Justificativa ou observação</label>
          <input
            id="operations-decision-note"
            name="note"
            maxlength="1000"
            value="{{ old('note') }}"
            placeholder="Obrigatória ao manter a release em espera"
            @disabled(!$releaseAvailable)
          >
        </div>
        <div class="field implementation-portfolio-filter-actions">
          <button type="submit" @disabled(!$releaseAvailable)>Registrar decisão</button>
        </div>
      </form>
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

    <div class="panel-body">
      <div class="implementation-readiness-header">
        <div>
          <span class="eyebrow">Execução assistida</span>
          <h3>Probe operacional</h3>
          <p class="muted">O teste cria apenas artefatos sintéticos, passa pela fila real e mantém o histórico por clínica e release.</p>
        </div>
        <form method="POST" action="{{ route('operations.runtime-probes.prepare') }}">
          @csrf
          <button type="submit" @disabled(!$runtimeProbeRuns['available'] || !$runtimeProbeRuns['can_prepare'])>
            Preparar novo probe
          </button>
        </form>
      </div>

      @if($errors->has('runtime_probe'))
        <div class="alert warning">{{ $errors->first('runtime_probe') }}</div>
      @endif

      @if($runtimeProbeRuns['items'] === [])
        <p class="muted">Nenhuma execução iniciada nesta clínica e release.</p>
      @else
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Probe</th>
                <th>Status</th>
                <th>Responsável</th>
                <th>Atualização</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              @foreach($runtimeProbeRuns['items'] as $run)
                <tr>
                  <td><strong>{{ $run['probe_id'] }}</strong><br><small>{{ $run['detail'] }}</small></td>
                  <td><span class="badge {{ $run['status_tone'] }}">{{ $run['status_label'] }}</span></td>
                  <td>{{ $run['actor'] ?? 'Operador removido' }}</td>
                  <td>{{ $run['occurred_at']->format('d/m/Y H:i') }}</td>
                  <td>
                    @if($run['can_verify'])
                      <form method="POST" action="{{ route('operations.runtime-probes.verify', $run['probe_id']) }}">
                        @csrf
                        <button type="submit" class="secondary">Verificar resultado</button>
                      </form>
                    @else
                      <span class="muted">Concluído</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
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

  <section class="panel">
    <div class="panel-heading">
      <div>
        <span class="eyebrow">Validação humana</span>
        <h2>Smoke test da release</h2>
        <p>Cada conclusão ou reabertura cria um novo registro ligado ao ambiente, commit e clínica atuais.</p>
      </div>
      <span class="badge {{ $smokeChecklist['completed'] === $smokeChecklist['total'] ? 'success' : 'warning' }}">
        {{ $smokeChecklist['completed'] }} de {{ $smokeChecklist['total'] }}
      </span>
    </div>

    <div class="panel-body">
      @unless($smokeChecklist['available'])
        <div class="alert warning">Identifique o commit publicado antes de registrar o smoke test.</div>
      @endunless

      <div class="implementation-checklist-items">
        @foreach($smokeChecklist['items'] as $item)
          <article class="implementation-checklist-item {{ $item['completed'] ? 'completed' : '' }}">
            <div class="implementation-checklist-copy">
              <span aria-hidden="true">{{ $item['completed'] ? '✓' : '○' }}</span>
              <div>
                <strong>{{ $item['label'] }}</strong>
                <p class="muted">{{ $item['description'] }}</p>
                @if($item['actor'])
                  <small>
                    Última decisão por {{ $item['actor'] }} em {{ $item['decided_at']->format('d/m/Y H:i') }}
                  </small>
                @else
                  <small>Ainda sem decisão registrada.</small>
                @endif
              </div>
            </div>

            <form method="POST" action="{{ route('operations.smoke-checks.store', $item['key']) }}" class="form-grid compact-filter-grid">
              @csrf
              <input type="hidden" name="action" value="{{ $item['completed'] ? 'reopen' : 'complete' }}">
              <div class="field">
                <label for="smoke-note-{{ $item['key'] }}">Observação</label>
                <input
                  id="smoke-note-{{ $item['key'] }}"
                  name="note"
                  maxlength="500"
                  value="{{ $item['note'] }}"
                  placeholder="Contexto opcional da validação"
                  @disabled(!$smokeChecklist['available'])
                >
              </div>
              <div class="field implementation-portfolio-filter-actions">
                <button type="submit" class="{{ $item['completed'] ? 'secondary' : '' }}" @disabled(!$smokeChecklist['available'])>
                  {{ $item['completed'] ? 'Reabrir item' : 'Marcar concluído' }}
                </button>
              </div>
            </form>
          </article>
        @endforeach
      </div>
    </div>
  </section>
@endsection
