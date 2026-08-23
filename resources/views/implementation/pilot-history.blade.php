@extends('layouts.admin')

@section('title', 'Histórico do Piloto - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Histórico do piloto</h1>
      <p>Evidências e decisões preservadas da clínica {{ $clinic->trade_name }}.</p>
    </div>

    <div class="row-actions">
      <a class="button secondary" href="{{ route('implementation.pilots.report', $clinic) }}">
        Relatório atual
      </a>
      <a class="button secondary" href="{{ route('implementation.index') }}">
        Voltar à implantação
      </a>
    </div>
  </header>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Cobertura</span>
          <h2>Importações concluídas</h2>
          <p class="muted">Cada execução permanece identificada por arquivo, origem, responsável e horário.</p>
        </div>
      </div>

      @if($history['imports']->isEmpty())
        <div class="empty-state"><p>Nenhuma importação registrada.</p></div>
      @else
        <div class="table-wrap implementation-table">
          <table>
            <thead><tr><th>Concluída em</th><th>Bloco</th><th>Arquivo</th><th>Resultado</th><th>Responsável</th></tr></thead>
            <tbody>
              @foreach($history['imports'] as $import)
                <tr>
                  <td>{{ $import->completed_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $import->entity_label }}</td>
                  <td>{{ $import->file_name }} · {{ mb_strtoupper($import->data_source) }}</td>
                  <td>{{ $import->imported_count }} importados de {{ $import->total_rows }}</td>
                  <td>{{ $import->user_name }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{ $history['imports']->links() }}
      @endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Preparação</span>
          <h2>Decisões do checklist</h2>
          <p class="muted">Conclusões e reaberturas são exibidas sem substituir os eventos anteriores.</p>
        </div>
      </div>

      @if($history['checks']->isEmpty())
        <div class="empty-state"><p>Nenhuma decisão de checklist registrada.</p></div>
      @else
        <div class="table-wrap implementation-table">
          <table>
            <thead><tr><th>Decidido em</th><th>Item</th><th>Decisão</th><th>Observação</th><th>Responsável</th></tr></thead>
            <tbody>
              @foreach($history['checks'] as $check)
                <tr>
                  <td>{{ $check->decided_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $check->check_label }}</td>
                  <td>{{ $check->completed ? 'Concluído' : 'Reaberto' }}</td>
                  <td>{{ $check->notes ?: '—' }}</td>
                  <td>{{ $check->user_name }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{ $history['checks']->links() }}
      @endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Liberação</span>
          <h2>Revisões do plano</h2>
          <p class="muted">Escopo, responsáveis e notas permanecem disponíveis por revisão.</p>
        </div>
      </div>

      @if($history['releases']->isEmpty())
        <div class="empty-state"><p>Nenhuma revisão do plano registrada.</p></div>
      @else
        <div class="implementation-readiness-list">
          @foreach($history['releases'] as $release)
            <article class="implementation-readiness-card">
              <div class="implementation-readiness-header">
                <div>
                  <h3>Revisão {{ $release->revision }}</h3>
                  <p class="muted">{{ $release->recorded_at?->format('d/m/Y H:i') }} por {{ $release->user_name }}</p>
                </div>
                <strong>{{ $release->planned_start_date?->format('d/m/Y') ?: 'Sem data' }}</strong>
              </div>

              <p><strong>Operação:</strong> {{ $release->release_owner }}</p>
              <p><strong>Suporte:</strong> {{ $release->support_owner }}</p>
              <p><strong>Escopo:</strong> {{ $release->scope }}</p>
              <p><strong>Notas:</strong> {{ $release->release_notes }}</p>
            </article>
          @endforeach
        </div>

        {{ $history['releases']->links() }}
      @endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Governança</span>
          <h2>Decisões de prontidão</h2>
          <p class="muted">O hash relaciona cada decisão ao retrato de evidências usado naquele momento.</p>
        </div>
      </div>

      @if($history['decisions']->isEmpty())
        <div class="empty-state"><p>Nenhuma decisão de prontidão registrada.</p></div>
      @else
        <div class="table-wrap implementation-table">
          <table>
            <thead><tr><th>Decidido em</th><th>Decisão</th><th>Evidências</th><th>Observação</th><th>Responsável</th></tr></thead>
            <tbody>
              @foreach($history['decisions'] as $decision)
                <tr>
                  <td>{{ $decision->decided_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $decision->decision === 'approved' ? 'Aprovado' : 'Em espera' }}</td>
                  <td>
                    <code>{{ substr($decision->evidence_hash, 0, 12) }}…</code><br>
                    <small class="muted">
                      {{ data_get($decision->evidence_snapshot, 'coverage.completed', 0) }} blocos ·
                      {{ data_get($decision->evidence_snapshot, 'quality.issues', 0) }} pendências ·
                      {{ data_get($decision->evidence_snapshot, 'checklist.completed', 0) }} itens
                    </small>
                  </td>
                  <td>{{ $decision->notes ?: '—' }}</td>
                  <td>{{ $decision->user_name }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{ $history['decisions']->links() }}
      @endif
    </div>
  </section>
@endsection
