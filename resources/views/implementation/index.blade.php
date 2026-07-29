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

      <a class="button secondary" href="{{ route('clinics.index') }}">
        Clínicas
      </a>
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
            Tutores e Pacientes já podem ser importados por CSV. Excel será habilitado após este fluxo estar consolidado.
          </p>

          <form method="POST" action="{{ route('implementation.source') }}">
            @csrf

            <div class="implementation-options">
              @foreach($dataSources as $sourceValue => $sourceLabel)
                @php($sourceAvailable = $sourceValue === 'csv')

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
                      <small>Disponível agora para Tutores e Pacientes</small>
                    @elseif($sourceValue === 'excel')
                      <small>Próxima origem planejada</small>
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
          <h2>Escolha e envie o bloco CSV</h2>
          <p class="muted">
            Use um template do VetFlow. Cada arquivo pode ter até 2 MB e 500 registros.
          </p>

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
                    href="{{ route('implementation.templates', $import['template']) }}"
                  >
                    {{ $import['template_label'] }}
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
                <label for="{{ $import['input_id'] }}">Arquivo de {{ $import['label'] }}</label>
                <input
                  id="{{ $import['input_id'] }}"
                  type="file"
                  name="{{ $import['input_name'] }}"
                  accept=".csv,text/csv"
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
            Arquivo <strong>{{ $wizardState['file_name'] ?? $activeImport['default_file'] }}</strong>,
            separado por {{ $analysis['delimiter'] ?? 'delimitador não identificado' }}.
          </p>

          <div class="table-wrap implementation-table">
            <table>
              <thead>
                <tr>
                  <th>Coluna do CSV</th>
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
                Substituir CSV
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
                        <td>{{ $row['values']['name'] ?: 'Sem nome' }}</td>
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
              <dd>{{ $wizardState['file_name'] ?? $activeImport['default_file'] }}</dd>
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
            <p>{{ $completedSummary['entity_label'] ?? $activeImport['label'] }} foram adicionados à clínica selecionada.</p>
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
