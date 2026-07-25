@extends('layouts.admin')

@section('title', 'Assistente de Implantação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Assistente de Implantação</h1>
      <p>Prepare, valide e acompanhe a migração de dados da clínica para o VetFlow.</p>
    </div>

    <a class="button secondary" href="{{ route('clinics.index') }}">
      Clínicas
    </a>
  </header>

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
          @endphp

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
            Todos os dados validados nesta implantação serão vinculados à clínica escolhida.
          </p>

          @if($clinicsCount > 0)
            <div class="form-group">
              <label for="implementation-clinic">Clínica</label>

              <select id="implementation-clinic" name="clinic_id">
                <option value="">Selecione uma clínica</option>

                @foreach($clinics as $clinic)
                  <option value="{{ $clinic->id }}">
                    {{ $clinic->trade_name }} — {{ $clinic->corporate_name }}
                  </option>
                @endforeach
              </select>
            </div>
          @else
            <div class="empty-state">
              <h3>Nenhuma clínica cadastrada</h3>
              <p>Cadastre a clínica destino para liberar o assistente.</p>
            </div>
          @endif
          @break

        @case(2)
          <h2>Escolha a origem dos dados</h2>
          <p class="muted">
            Nesta primeira versão, CSV e Excel serão os formatos prioritários.
          </p>

          <div class="implementation-options">
            @foreach($dataSources as $sourceValue => $sourceLabel)
              <label class="implementation-option">
                <input
                  type="radio"
                  name="data_source"
                  value="{{ $sourceValue }}"
                  @disabled(!in_array($sourceValue, ['csv', 'excel'], true))
                >

                <span>
                  <strong>{{ $sourceLabel }}</strong>

                  @if(in_array($sourceValue, ['csv', 'excel'], true))
                    <small>Disponível na próxima etapa da implementação</small>
                  @else
                    <small>Conector planejado</small>
                  @endif
                </span>
              </label>
            @endforeach
          </div>
          @break

        @case(3)
          <h2>Envio de arquivos</h2>
          <p class="muted">
            O upload real será conectado na próxima sprint. A estrutura dos blocos já está preparada.
          </p>

          <div class="implementation-blocks">
            @foreach($migrationBlocks as $block)
              <label class="implementation-block">
                <input type="checkbox" disabled>
                <span>{{ $block }}</span>
              </label>
            @endforeach
          </div>

          <div class="panel compact-panel">
            <div class="panel-body">
              <h3>Templates CSV</h3>
              <p class="muted">Use os modelos do VetFlow para organizar os dados antes do envio.</p>

              <div class="row-actions">
                @foreach($templates as $template)
                  <a
                    class="button secondary"
                    href="{{ route('implementation.templates', $template) }}"
                  >
                    {{ ucfirst($template) }} CSV
                  </a>
                @endforeach
              </div>
            </div>
          </div>
          @break

        @case(4)
          <div class="implementation-placeholder">
            <span>04</span>
            <h2>Mapeamento de colunas</h2>
            <p>Os campos do arquivo serão relacionados aos campos oficiais do VetFlow.</p>
          </div>
          @break

        @case(5)
          <div class="implementation-placeholder">
            <span>05</span>
            <h2>Validação dos dados</h2>
            <p>Duplicidades, campos obrigatórios e inconsistências serão apresentados aqui.</p>
          </div>
          @break

        @case(6)
          <div class="implementation-placeholder">
            <span>06</span>
            <h2>Pré-visualização</h2>
            <p>Uma amostra dos registros ficará disponível para conferência antes da gravação.</p>
          </div>
          @break

        @case(7)
          <div class="implementation-placeholder">
            <span>07</span>
            <h2>Importação</h2>
            <p>O progresso de cada bloco será acompanhado nesta etapa.</p>
          </div>
          @break

        @case(8)
          <div class="implementation-placeholder">
            <span>08</span>
            <h2>Finalização</h2>
            <p>O relatório final da implantação será exibido aqui.</p>
          </div>
          @break
      @endswitch

      <div class="implementation-actions">
        <div>
          @if($previousStep)
            <a
              class="button secondary"
              href="{{ route('implementation.index', ['step' => $previousStep]) }}"
            >
              Voltar
            </a>
          @endif
        </div>

        <div>
          @if($nextStep)
            <a
              class="button"
              href="{{ route('implementation.index', ['step' => $nextStep]) }}"
            >
              Continuar
            </a>
          @else
            <a class="button" href="{{ route('dashboard') }}">
              Concluir
            </a>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection
