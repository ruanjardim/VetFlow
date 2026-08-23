@extends('layouts.admin')

@section('title', 'Assistente de Implantação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Assistente de Implantação</h1>
      <p>Prepare, valide e acompanhe a migração de dados da clínica para o VetFlow.</p>
    </div>

    <div class="row-actions">
      @if(!empty($wizardState))
        <form method="POST" action="{{ route('implementation.reset') }}">
          @csrf
          @method('DELETE')

          <button class="button secondary" type="submit">
            Reiniciar
          </button>
        </form>
      @endif

      @if(auth()->user()?->clinic_id === null)
        @can('clinics.manage')
          <a class="button secondary" href="{{ route('clinics.index') }}">
            Clínicas
          </a>
        @endcan
      @endif
    </div>
  </header>

  @if(session('warning'))
    <div class="alert warning">{{ session('warning') }}</div>
  @endif

  @if($clinicsCount === 0)
    <div class="alert warning action-alert">
      <div>
        <strong>Cadastre uma clínica antes de iniciar.</strong>
        <span>A implantação precisa de uma clínica ativa para receber os dados importados.</span>
      </div>

      <a class="button secondary" href="{{ route('clinics.create') }}">
        Cadastrar clínica
      </a>
    </div>
  @endif

  @if(!empty($pilotReadiness))
    <section class="panel implementation-pilot-readiness">
      <div class="panel-body">
        <div class="implementation-heading">
          <div>
            <span class="eyebrow">Decisão consolidada</span>
            <h2>Prontidão para o piloto</h2>
            <p class="muted">
              A decisão humana fica vinculada a um retrato das importações, qualidade, checklist e plano atuais.
            </p>
          </div>
        </div>

        <div class="implementation-readiness-list">
          @foreach($pilotReadiness as $readiness)
            <article class="implementation-readiness-card readiness-{{ $readiness['status']['key'] }}">
              <div class="implementation-readiness-header">
                <div>
                  <h3>{{ $readiness['clinic_name'] }}</h3>
                  <p class="muted">{{ $readiness['status']['description'] }}</p>
                </div>

                <span class="implementation-readiness-status">
                  {{ $readiness['status']['label'] }}
                </span>
              </div>

              <div class="implementation-readiness-gates">
                @foreach($readiness['gates'] as $gate)
                  <div class="implementation-readiness-gate {{ $gate['passed'] ? 'passed' : 'pending' }}">
                    <span aria-hidden="true">{{ $gate['passed'] ? '✓' : '○' }}</span>
                    <div>
                      <strong>{{ $gate['label'] }}</strong>
                      <small>{{ $gate['summary'] }}</small>
                    </div>
                  </div>
                @endforeach
              </div>

              @if($readiness['decision'])
                <div class="implementation-decision-audit">
                  <strong>
                    Última decisão: {{ $readiness['decision'] === 'approved' ? 'Aprovado' : 'Em espera' }}
                  </strong>
                  <span>
                    por {{ $readiness['decision_user_name'] }}
                    em {{ $readiness['decided_at']?->format('d/m/Y H:i') }}
                  </span>

                  @if(!$readiness['decision_current'])
                    <small>Esta decisão foi superada por mudanças nas evidências.</small>
                  @elseif($readiness['decision_notes'])
                    <small>{{ $readiness['decision_notes'] }}</small>
                  @endif
                </div>
              @endif

              <div class="row-actions">
                <a
                  class="button secondary"
                  href="{{ route('implementation.pilots.history', $readiness['clinic_id']) }}"
                >
                  Ver histórico do piloto
                </a>
              </div>

              <form
                class="implementation-readiness-decision"
                method="POST"
                action="{{ route('implementation.pilot-decisions.store') }}"
              >
                @csrf
                <input type="hidden" name="clinic_id" value="{{ $readiness['clinic_id'] }}">

                <div class="field">
                  <label for="pilot-decision-{{ $readiness['clinic_id'] }}">Decisão</label>
                  <select id="pilot-decision-{{ $readiness['clinic_id'] }}" name="decision" required>
                    @if($readiness['gates_passed'])
                      <option value="approved">Aprovar piloto com as evidências atuais</option>
                    @endif
                    <option value="held">Manter piloto em espera</option>
                  </select>
                </div>

                <div class="field">
                  <label for="pilot-decision-notes-{{ $readiness['clinic_id'] }}">
                    Justificativa ou observação
                  </label>
                  <textarea
                    id="pilot-decision-notes-{{ $readiness['clinic_id'] }}"
                    name="notes"
                    rows="3"
                    maxlength="2000"
                    placeholder="Obrigatória ao manter o piloto em espera"
                  ></textarea>
                </div>

                <button class="button primary" type="submit">Registrar decisão</button>
              </form>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if(!empty($onboardingReadiness))
    <section class="panel">
      <div class="panel-body">
        <div class="implementation-heading">
          <div>
            <span class="eyebrow">Onboarding guiado</span>
            <h2>Cobertura da implantação</h2>
            <p class="muted">
              O progresso considera somente blocos que já tiveram uma importação concluída com sucesso.
            </p>
          </div>
        </div>

        <div class="implementation-readiness-list">
          @foreach($onboardingReadiness as $readiness)
            <article class="implementation-readiness-card">
              <div class="implementation-readiness-header">
                <div>
                  <h3>{{ $readiness['clinic_name'] }}</h3>
                  <p class="muted">
                    {{ $readiness['completed_blocks'] }} de {{ $readiness['total_blocks'] }} blocos concluídos
                  </p>
                </div>

                <strong>{{ $readiness['percentage'] }}%</strong>
              </div>

              <div
                class="implementation-progress"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $readiness['percentage'] }}"
                aria-label="Cobertura da implantação de {{ $readiness['clinic_name'] }}"
              >
                <span style="width: {{ $readiness['percentage'] }}%"></span>
              </div>

              <div class="implementation-readiness-blocks">
                @foreach($readiness['blocks'] as $block)
                  <div class="implementation-readiness-block {{ $block['completed'] ? 'completed' : 'pending' }}">
                    <span aria-hidden="true">{{ $block['completed'] ? '✓' : '○' }}</span>

                    <div>
                      <strong>{{ $block['label'] }}</strong>

                      @if($block['completed'])
                        <small>
                          {{ $block['imported_count'] }} registros via {{ mb_strtoupper($block['source']) }}
                          em {{ $block['completed_at']?->format('d/m/Y H:i') }}
                        </small>
                      @else
                        <small>Pendente de importação concluída</small>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if(!empty($onboardingQuality))
    <section class="panel">
      <div class="panel-body">
        <div class="implementation-heading">
          <div>
            <span class="eyebrow">Qualidade dos dados</span>
            <h2>Pendências do onboarding</h2>
            <p class="muted">
              A revisão começa depois da primeira importação de cada bloco e destaca cadastros que merecem conferência.
            </p>
          </div>
        </div>

        <div class="implementation-readiness-list">
          @foreach($onboardingQuality as $quality)
            <article class="implementation-readiness-card">
              <div class="implementation-readiness-header">
                <div>
                  <h3>{{ $quality['clinic_name'] }}</h3>
                  <p class="muted">
                    {{ $quality['total_issues'] }} pendências em {{ $quality['evaluated_blocks'] }} blocos avaliados
                  </p>
                </div>

                <strong>{{ $quality['percentage'] }}%</strong>
              </div>

              <div
                class="implementation-progress"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $quality['percentage'] }}"
                aria-label="Qualidade dos dados de {{ $quality['clinic_name'] }}"
              >
                <span style="width: {{ $quality['percentage'] }}%"></span>
              </div>

              <p class="implementation-quality-summary">
                {{ $quality['ready_blocks'] }} de {{ $quality['evaluated_blocks'] }} blocos avaliados sem pendências detectadas
              </p>

              <div class="implementation-readiness-blocks">
                @foreach($quality['blocks'] as $block)
                  <div class="implementation-readiness-block quality-{{ $block['status'] }}">
                    <span aria-hidden="true">
                      {{ $block['status'] === 'ready' ? '✓' : ($block['status'] === 'attention' ? '!' : '○') }}
                    </span>

                    <div>
                      <strong>{{ $block['label'] }}</strong>

                      @if($block['status'] === 'awaiting')
                        <small>Aguardando importação concluída</small>
                      @elseif($block['status'] === 'ready')
                        <small>Sem pendências detectadas</small>
                      @else
                        <small>
                          {{ $block['issue_count'] }} registros para revisar:<br>
                          {{ $block['description'] }}
                        </small>

                        <a
                          class="implementation-quality-link"
                          href="{{ route('implementation.quality.issues', [
                            'clinic' => $quality['clinic_id'],
                            'type' => $block['type'],
                          ]) }}"
                        >
                          Ver registros
                        </a>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if(!empty($pilotChecklists))
    <section class="panel">
      <div class="panel-body">
        <div class="implementation-heading">
          <div>
            <span class="eyebrow">Preparação do piloto</span>
            <h2>Checklist auditável</h2>
            <p class="muted">
              Cada conclusão ou reabertura gera uma nova decisão com responsável e horário, preservando o histórico.
            </p>
          </div>
        </div>

        <div class="implementation-readiness-list">
          @foreach($pilotChecklists as $checklist)
            <article class="implementation-readiness-card">
              <div class="implementation-readiness-header">
                <div>
                  <h3>{{ $checklist['clinic_name'] }}</h3>
                  <p class="muted">
                    {{ $checklist['completed_checks'] }} de {{ $checklist['total_checks'] }} itens concluídos
                  </p>
                </div>

                <strong>{{ $checklist['percentage'] }}%</strong>
              </div>

              <div
                class="implementation-progress"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $checklist['percentage'] }}"
                aria-label="Checklist do piloto de {{ $checklist['clinic_name'] }}"
              >
                <span style="width: {{ $checklist['percentage'] }}%"></span>
              </div>

              <div class="implementation-pilot-checks">
                @foreach($checklist['checks'] as $check)
                  <form
                    class="implementation-pilot-check {{ $check['completed'] ? 'completed' : 'pending' }}"
                    method="POST"
                    action="{{ route('implementation.pilot-checks.store') }}"
                  >
                    @csrf
                    <input type="hidden" name="clinic_id" value="{{ $checklist['clinic_id'] }}">
                    <input type="hidden" name="check_key" value="{{ $check['key'] }}">
                    <input type="hidden" name="completed" value="{{ $check['completed'] ? 0 : 1 }}">

                    <div class="implementation-pilot-check-heading">
                      <span aria-hidden="true">{{ $check['completed'] ? '✓' : '○' }}</span>
                      <div>
                        <strong>{{ $check['label'] }}</strong>
                        <small>{{ $check['description'] }}</small>
                      </div>
                    </div>

                    <label for="pilot-note-{{ $checklist['clinic_id'] }}-{{ $check['key'] }}">
                      Observação da decisão
                    </label>
                    <textarea
                      id="pilot-note-{{ $checklist['clinic_id'] }}-{{ $check['key'] }}"
                      name="notes"
                      rows="2"
                      maxlength="1000"
                      placeholder="Contexto opcional para a equipe"
                    >{{ $check['notes'] }}</textarea>

                    @if($check['has_decision'])
                      <small class="implementation-pilot-audit">
                        Última decisão por {{ $check['user_name'] }}
                        em {{ $check['decided_at']?->format('d/m/Y H:i') }}
                      </small>
                    @else
                      <small class="implementation-pilot-audit">Ainda sem decisão registrada</small>
                    @endif

                    <button class="button secondary small" type="submit">
                      {{ $check['completed'] ? 'Reabrir item' : 'Marcar concluído' }}
                    </button>
                  </form>
                @endforeach
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if(!empty($pilotReleases))
    <section class="panel">
      <div class="panel-body">
        <div class="implementation-heading">
          <div>
            <span class="eyebrow">Liberação piloto</span>
            <h2>Responsáveis, escopo e notas</h2>
            <p class="muted">
              Salvar o plano cria uma nova revisão; o conteúdo anterior permanece preservado para auditoria.
            </p>
          </div>
        </div>

        <div class="implementation-readiness-list">
          @foreach($pilotReleases as $release)
            @php
              $useOldRelease = (int) old('clinic_id') === (int) $release['clinic_id'];
            @endphp
            <article class="implementation-readiness-card">
              <div class="implementation-readiness-header">
                <div>
                  <h3>{{ $release['clinic_name'] }}</h3>

                  @if($release['has_release'])
                    <p class="muted">
                      Revisão {{ $release['revision'] }} registrada por {{ $release['user_name'] }}
                      em {{ $release['recorded_at']?->format('d/m/Y H:i') }}
                    </p>
                  @else
                    <p class="muted">Plano ainda não registrado</p>
                  @endif
                </div>
              </div>

              <form
                class="implementation-pilot-release"
                method="POST"
                action="{{ route('implementation.pilot-releases.store') }}"
              >
                @csrf
                <input type="hidden" name="clinic_id" value="{{ $release['clinic_id'] }}">

                <div class="form-grid">
                  <div class="field">
                    <label for="release-owner-{{ $release['clinic_id'] }}">Responsável operacional</label>
                    <input
                      id="release-owner-{{ $release['clinic_id'] }}"
                      name="release_owner"
                      type="text"
                      maxlength="150"
                      required
                      value="{{ $useOldRelease ? old('release_owner') : $release['release_owner'] }}"
                    >
                  </div>

                  <div class="field">
                    <label for="support-owner-{{ $release['clinic_id'] }}">Responsável pelo suporte</label>
                    <input
                      id="support-owner-{{ $release['clinic_id'] }}"
                      name="support_owner"
                      type="text"
                      maxlength="150"
                      required
                      value="{{ $useOldRelease ? old('support_owner') : $release['support_owner'] }}"
                    >
                  </div>

                  <div class="field">
                    <label for="planned-start-{{ $release['clinic_id'] }}">Início previsto</label>
                    <input
                      id="planned-start-{{ $release['clinic_id'] }}"
                      name="planned_start_date"
                      type="date"
                      value="{{ $useOldRelease ? old('planned_start_date') : $release['planned_start_date']?->format('Y-m-d') }}"
                    >
                  </div>
                </div>

                <div class="field">
                  <label for="pilot-scope-{{ $release['clinic_id'] }}">Escopo funcional do piloto</label>
                  <textarea
                    id="pilot-scope-{{ $release['clinic_id'] }}"
                    name="scope"
                    rows="4"
                    maxlength="5000"
                    required
                    placeholder="Módulos, equipe, unidade e rotinas incluídas"
                  >{{ $useOldRelease ? old('scope') : $release['scope'] }}</textarea>
                </div>

                <div class="field">
                  <label for="release-notes-{{ $release['clinic_id'] }}">Notas da liberação</label>
                  <textarea
                    id="release-notes-{{ $release['clinic_id'] }}"
                    name="release_notes"
                    rows="4"
                    maxlength="10000"
                    required
                    placeholder="Mudanças, limitações conhecidas e orientações para o piloto"
                  >{{ $useOldRelease ? old('release_notes') : $release['release_notes'] }}</textarea>
                </div>

                <button class="button primary" type="submit">
                  {{ $release['has_release'] ? 'Salvar nova revisão' : 'Registrar plano do piloto' }}
                </button>
              </form>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Auditoria</span>
          <h2>Importações recentes</h2>
          <p class="muted">
            Resumos permanentes das últimas importações concluídas nas clínicas disponíveis para seu acesso.
          </p>
        </div>
      </div>

      @if($recentImports->isEmpty())
        <div class="empty-state">
          <h3>Nenhuma importação concluída</h3>
          <p>O histórico será preenchido após a confirmação do primeiro bloco.</p>
        </div>
      @else
        <div class="table-wrap implementation-table">
          <table>
            <thead>
              <tr>
                <th>Concluída em</th>
                <th>Clínica</th>
                <th>Bloco</th>
                <th>Origem</th>
                <th>Arquivo</th>
                <th>Importados</th>
                <th>Responsável</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentImports as $recentImport)
                <tr>
                  <td>{{ $recentImport->completed_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $recentImport->clinic_name }}</td>
                  <td>{{ $recentImport->entity_label }}</td>
                  <td>{{ mb_strtoupper($recentImport->data_source) }}</td>
                  <td>{{ $recentImport->file_name }}</td>
                  <td>{{ $recentImport->imported_count }}</td>
                  <td>{{ $recentImport->user_name }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Etapa {{ $currentStep }} de {{ count($wizardSteps) }}</span>
          <h2>{{ $currentStepData['title'] }}</h2>
          <p class="muted">{{ $currentStepData['description'] }}</p>
        </div>

        <strong>{{ $progressPercentage }}%</strong>
      </div>

      <div
        class="implementation-progress"
        role="progressbar"
        aria-valuemin="1"
        aria-valuemax="{{ count($wizardSteps) }}"
        aria-valuenow="{{ $currentStep }}"
        aria-label="Progresso da implantação"
      >
        <span style="width: {{ $progressPercentage }}%"></span>
      </div>

      <nav class="implementation-steps" aria-label="Etapas da implantação">
        @foreach($wizardSteps as $stepNumber => $step)
          @php
            $stepState = $stepNumber < $currentStep
              ? 'completed'
              : ($stepNumber === $currentStep ? 'current' : 'pending');
            $stepEnabled = $stepNumber <= $maxAllowedStep;
          @endphp

          @if($stepEnabled)
            <a
              href="{{ route('implementation.index', ['step' => $stepNumber]) }}"
              class="implementation-step {{ $stepState }}"
              @if($stepNumber === $currentStep) aria-current="step" @endif
            >
              <span class="implementation-step-number">
                @if($stepNumber < $currentStep)
                  ✓
                @else
                  {{ $stepNumber }}
                @endif
              </span>

              <span>{{ $step['short_title'] }}</span>
            </a>
          @else
            <span class="implementation-step disabled" aria-disabled="true">
              <span class="implementation-step-number">{{ $stepNumber }}</span>
              <span>{{ $step['short_title'] }}</span>
            </span>
          @endif
        @endforeach
      </nav>
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      @switch($currentStep)
        @case(1)
          <h2>Selecione a clínica destino</h2>
          <p class="muted">
            Todos os registros confirmados nesta implantação serão vinculados à clínica escolhida.
          </p>

          @if($clinicsCount > 0)
            <form method="POST" action="{{ route('implementation.clinic') }}">
              @csrf

              <div class="form-group implementation-form-width">
                <label for="implementation-clinic">Clínica</label>

                <select id="implementation-clinic" name="clinic_id" required>
                  <option value="">Selecione uma clínica</option>

                  @foreach($clinics as $clinic)
                    <option
                      value="{{ $clinic->id }}"
                      @selected((string) old('clinic_id', $wizardState['clinic_id'] ?? '') === (string) $clinic->id)
                    >
                      {{ $clinic->trade_name }} — {{ $clinic->corporate_name }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-actions">
                <button class="button" type="submit">Salvar e continuar</button>
              </div>
            </form>
          @else
            <div class="empty-state">
              <h3>Nenhuma clínica disponível</h3>
              <p>Cadastre ou ative a clínica destino para liberar o assistente.</p>
            </div>
          @endif
          @break

        @case(2)
          <h2>Escolha a origem dos dados</h2>
          <p class="muted">
            Responsáveis, Pacientes, Fornecedores, Produtos, Estoque e Financeiro podem ser importados por CSV ou Excel.
          </p>

          <form method="POST" action="{{ route('implementation.source') }}">
            @csrf

            <div class="implementation-options">
              @foreach($dataSources as $sourceValue => $sourceLabel)
                @php($sourceAvailable = in_array($sourceValue, ['csv', 'excel'], true))

                <label class="implementation-option">
                  <input
                    type="radio"
                    name="data_source"
                    value="{{ $sourceValue }}"
                    @checked(old('data_source', $wizardState['data_source'] ?? '') === $sourceValue)
                    @disabled(!$sourceAvailable)
                  >

                  <span>
                    <strong>{{ $sourceLabel }}</strong>

                    @if($sourceAvailable)
                      <small>
                        {{ $sourceValue === 'excel' ? 'Arquivos .xlsx' : 'Arquivos .csv' }}
                        para os seis blocos
                      </small>
                    @else
                      <small>Conector planejado</small>
                    @endif
                  </span>
                </label>
              @endforeach
            </div>

            <div class="form-actions">
              <button class="button" type="submit">Salvar e continuar</button>
            </div>
          </form>
          @break

        @case(3)
          <h2>Escolha e envie o bloco {{ $sourceLabel }}</h2>
          <p class="muted">
            Use um template do VetFlow. Cada arquivo pode ter até 2 MB e 500 registros.
            @if($dataSource === 'excel')
              Os dados devem estar na primeira aba da planilha.
            @endif
          </p>

          <div class="alert warning">
            Ordem recomendada: Fornecedores, Produtos e depois Estoque. Se preencher
            <code>estoque_atual</code> no arquivo de Produtos, não repita esse mesmo saldo
            no arquivo de Estoque. Importe o Financeiro por último.
            @if($dataSource === 'excel')
              Formate documentos, telefones e identificadores como Texto quando precisarem manter zeros à esquerda.
            @endif
          </div>

          <div class="implementation-blocks">
            @foreach($migrationBlocks as $block)
              <label class="implementation-block {{ $block['available'] ? 'available' : 'disabled' }}">
                <input
                  type="checkbox"
                  @checked($block['available'])
                  disabled
                >

                <span>
                  <strong>{{ $block['label'] }}</strong>
                  <small>{{ $block['available'] ? 'Disponível nesta entrega' : 'Planejado' }}</small>
                </span>
              </label>
            @endforeach
          </div>

          @foreach($availableImports as $importKey => $import)
            <div class="panel compact-panel">
              <div class="panel-body">
                <h3>{{ $import['label'] }}</h3>
                <p class="muted">
                  Baixe o template e mantenha os nomes das colunas para que o mapeamento seja automático.
                </p>

                <div class="row-actions">
                  <a
                    class="button secondary"
                    href="{{
                      $dataSource === 'excel'
                        ? route('implementation.templates.excel', $import['template'])
                        : route('implementation.templates', $import['template'])
                    }}"
                  >
                    {{ $import['label'] }} {{ $sourceLabel }}
                  </a>
                </div>
              </div>
            </div>

            <form
              class="implementation-upload"
              method="POST"
              action="{{ route($import['upload_route']) }}"
              enctype="multipart/form-data"
            >
              @csrf

              <div class="form-group implementation-form-width">
                <label for="{{ $import['input_id'] }}">
                  Arquivo de {{ $import['label'] }} ({{ $sourceLabel }})
                </label>
                <input
                  id="{{ $import['input_id'] }}"
                  type="file"
                  name="{{ $import['input_name'] }}"
                  accept="{{
                    $dataSource === 'excel'
                      ? '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                      : '.csv,text/csv'
                  }}"
                  required
                >
                <span class="field-hint">
                  Colunas esperadas: {{ $import['expected_columns'] }}.
                </span>
              </div>

              <div class="form-actions">
                <button class="button" type="submit">Analisar {{ $import['label'] }}</button>
              </div>
            </form>
          @endforeach
          @break

        @case(4)
          <h2>Mapeamento automático</h2>
          <p class="muted">
            Arquivo <strong>{{ $wizardState['file_name'] ?? $defaultFile }}</strong>.
            @if($dataSource === 'excel')
              Primeira aba lida: <strong>{{ $analysis['worksheet'] ?? 'não identificada' }}</strong>.
            @else
              Separado por {{ $analysis['delimiter'] ?? 'delimitador não identificado' }}.
            @endif
          </p>

          <div class="table-wrap implementation-table">
            <table>
              <thead>
                <tr>
                  <th>Coluna do arquivo</th>
                  <th>Campo no VetFlow</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($mappingDefinitions as $mapping)
                  @php($columnFound = in_array($mapping['source_label'], $analysis['headers'] ?? [], true))

                  <tr>
                    <td><code>{{ $mapping['source_label'] }}</code></td>
                    <td>{{ $mapping['target_label'] }}</td>
                    <td>
                      <span class="badge {{ $columnFound ? 'success' : 'danger' }}">
                        {{ $columnFound ? 'Detectada' : 'Ausente' }}
                      </span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @break

        @case(5)
          <h2>Resultado da validação</h2>

          <div class="implementation-summary">
            <div>
              <span>Registros lidos</span>
              <strong>{{ $analysis['total_rows'] ?? 0 }}</strong>
            </div>
            <div>
              <span>Válidos</span>
              <strong>{{ $analysis['valid_rows'] ?? 0 }}</strong>
            </div>
            <div>
              <span>Com pendências</span>
              <strong>{{ $analysis['invalid_rows'] ?? 0 }}</strong>
            </div>
          </div>

          @if(!empty($analysis['file_errors']))
            <div class="alert error">
              <strong>Problemas no arquivo:</strong>

              <ul class="implementation-error-list">
                @foreach($analysis['file_errors'] as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @if(($analysis['can_import'] ?? false))
            <div class="alert success">
              Todos os registros estão prontos para a pré-visualização.
            </div>
          @else
            <div class="alert warning action-alert">
              <div>
                <strong>Envie um novo arquivo após as correções.</strong>
                <span>Nenhum registro será gravado enquanto houver pendências.</span>
              </div>

              <a class="button secondary" href="{{ route('implementation.index', ['step' => 3]) }}">
                Substituir {{ $sourceLabel }}
              </a>
            </div>
          @endif

          @if(($analysis['invalid_rows'] ?? 0) > 0)
            <div class="table-wrap implementation-table">
              <table>
                <thead>
                  <tr>
                    <th>Linha</th>
                    <th>{{ $activeImport['singular'] }}</th>
                    <th>Pendências</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($analysis['rows'] as $row)
                    @if(!empty($row['errors']))
                      <tr>
                        <td>{{ $row['line'] }}</td>
                        <td>
                          {{
                            data_get($row, 'values.name')
                              ?: data_get($row, 'values.description')
                              ?: data_get($row, 'values.product_name')
                              ?: data_get($row, 'values.identifier')
                              ?: 'Sem identificação'
                          }}
                        </td>
                        <td>{{ implode(' ', $row['errors']) }}</td>
                      </tr>
                    @endif
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
          @break

        @case(6)
          <h2>Pré-visualização de {{ $activeImport['label'] }}</h2>
          <p class="muted">
            Confira os dados antes da gravação. Até 20 registros são exibidos nesta tela.
          </p>

          <div class="table-wrap implementation-table">
            <table>
              <thead>
                <tr>
                  <th>Linha</th>
                  @foreach($activeImport['preview_columns'] as $column)
                    <th>{{ $column['label'] }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach(array_slice($analysis['rows'] ?? [], 0, 20) as $row)
                  <tr>
                    <td>{{ $row['line'] }}</td>
                    @foreach($activeImport['preview_columns'] as $column)
                      <td>{{ data_get($row['values'], $column['key']) ?: '—' }}</td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @break

        @case(7)
          <h2>Confirmar importação</h2>
          <p class="muted">
            Esta ação criará os registros de {{ $activeImport['label'] }} validados na clínica selecionada.
          </p>

          <dl class="implementation-confirmation">
            <div>
              <dt>Clínica</dt>
              <dd>{{ $selectedClinic?->trade_name }}</dd>
            </div>
            <div>
              <dt>Arquivo</dt>
              <dd>{{ $wizardState['file_name'] ?? $defaultFile }}</dd>
            </div>
            <div>
              <dt>{{ $activeImport['label'] }}</dt>
              <dd>{{ $analysis['valid_rows'] ?? 0 }}</dd>
            </div>
          </dl>

          <div class="alert warning">
            Confirme somente depois de revisar a prévia. A importação é executada em uma transação.
          </div>

          <form method="POST" action="{{ route($activeImport['import_route']) }}">
            @csrf

            <button class="button" type="submit">Importar {{ $activeImport['label'] }}</button>
          </form>
          @break

        @case(8)
          <div class="implementation-finish">
            <span aria-hidden="true">✓</span>
            <h2>Importação concluída</h2>
            <p>
              A importação do bloco
              {{ $completedSummary['entity_label'] ?? $activeImport['label'] }}
              foi concluída para a clínica selecionada.
            </p>
          </div>

          @if($completedSummary)
            <dl class="implementation-confirmation">
              <div>
                <dt>Clínica</dt>
                <dd>{{ $completedSummary['clinic_name'] }}</dd>
              </div>
              <div>
                <dt>Arquivo</dt>
                <dd>{{ $completedSummary['file_name'] }}</dd>
              </div>
              <div>
                <dt>Importados</dt>
                <dd>{{ $completedSummary['imported_count'] }}</dd>
              </div>
              <div>
                <dt>Concluído em</dt>
                <dd>{{ $completedSummary['completed_at'] }}</dd>
              </div>
            </dl>
          @endif

          <form method="POST" action="{{ route('implementation.reset') }}">
            @csrf
            @method('DELETE')

            <button class="button" type="submit">Iniciar nova importação</button>
          </form>
          @break
      @endswitch

      <div class="implementation-actions">
        <div>
          @if($previousStep && $currentStep !== 8)
            <a
              class="button secondary"
              href="{{ route('implementation.index', ['step' => $previousStep]) }}"
            >
              Voltar
            </a>
          @endif
        </div>

        <div>
          @if($currentStep === 4)
            <a
              class="button"
              href="{{ route('implementation.index', ['step' => 5]) }}"
            >
              Validar dados
            </a>
          @elseif($currentStep === 5 && ($analysis['can_import'] ?? false))
            <a
              class="button"
              href="{{ route('implementation.index', ['step' => 6]) }}"
            >
              Ver prévia
            </a>
          @elseif($currentStep === 6)
            <a
              class="button"
              href="{{ route('implementation.index', ['step' => 7]) }}"
            >
              Continuar para importação
            </a>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
