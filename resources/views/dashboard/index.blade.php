@extends('layouts.admin')

@section('title', 'Painel de controle - VetFlow')

@section('content')
  @php($hour = (int) now()->format('H'))
  @php($greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite'))
  @php($firstName = str((string) auth()->user()?->name)->before(' '))
  @php($alertStats = $alertSummary['stats'] ?? [])
  @php($intelligence = $productIntelligence ?? [])
  @php($intelligenceStats = $intelligence['stats'] ?? [])
  @php($intelligenceHealth = $intelligence['health'] ?? ['label' => 'Sem dados', 'level' => 'muted-badge', 'description' => ''])
  @php($healthBadge = match ($intelligenceHealth['level'] ?? 'info') {
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'muted-badge',
  })
  @php($scheduleItems = ($todayAppointments ?? collect())->isNotEmpty() ? $todayAppointments : ($nextAppointments ?? collect()))
  @php($showingToday = ($todayAppointments ?? collect())->isNotEmpty())

  <div class="dashboard-v2" data-dashboard-shell="control-center-v2">
    <header class="dashboard-hero">
      <div class="dashboard-hero-copy">
        <span class="dashboard-eyebrow"><span></span> Centro de comando</span>
        <h1>{{ $greeting }}, {{ $firstName }}.</h1>
        <p>O que precisa da sua atenção hoje, sem perder tempo procurando em relatórios.</p>
        <span class="dashboard-date">Hoje, {{ now()->format('d/m/Y') }}</span>
      </div>
      <div class="dashboard-hero-actions">
        @can('appointments.manage')
          <a class="button secondary" href="{{ route('appointments.create') }}"><x-ui-icon name="calendar" :size="18" /> Agendar consulta</a>
        @endcan
        @can('sales.manage')
          <a class="button" href="{{ route('sales.create') }}"><x-ui-icon name="sales" :size="18" /> Nova venda</a>
        @endcan
      </div>
    </header>

    <section class="dashboard-kpi-grid" aria-label="Indicadores de hoje">
      <a class="dashboard-kpi dashboard-kpi-blue" href="{{ route('schedules.index') }}">
        <span class="dashboard-kpi-icon"><x-ui-icon name="calendar" /></span>
        <span class="dashboard-kpi-copy"><small>Consultas hoje</small><strong>{{ $stats['appointments_today'] ?? 0 }}</strong><span>{{ $stats['appointments_week'] ?? 0 }} na semana</span></span>
        <x-ui-icon class="dashboard-kpi-arrow" name="arrow" :size="18" />
      </a>
      <a class="dashboard-kpi dashboard-kpi-violet" href="{{ route('service-orders.board') }}">
        <span class="dashboard-kpi-icon"><x-ui-icon name="grooming" /></span>
        <span class="dashboard-kpi-copy"><small>Comandas abertas</small><strong>{{ $stats['service_orders_open'] ?? 0 }}</strong><span>{{ $stats['service_orders_waiting_pickup'] ?? 0 }} aguardando retirada</span></span>
        <x-ui-icon class="dashboard-kpi-arrow" name="arrow" :size="18" />
      </a>
      <a class="dashboard-kpi dashboard-kpi-emerald" href="{{ route('sales.index') }}">
        <span class="dashboard-kpi-icon"><x-ui-icon name="money" /></span>
        <span class="dashboard-kpi-copy"><small>Vendas hoje</small><strong>R$ {{ number_format($stats['sales_today'] ?? 0, 2, ',', '.') }}</strong><span>R$ {{ number_format($stats['sales_month'] ?? 0, 2, ',', '.') }} no mês</span></span>
        <x-ui-icon class="dashboard-kpi-arrow" name="arrow" :size="18" />
      </a>
      <a class="dashboard-kpi dashboard-kpi-amber" href="{{ route('financial-transactions.cash-flow') }}">
        <span class="dashboard-kpi-icon"><x-ui-icon name="finance" /></span>
        <span class="dashboard-kpi-copy"><small>Contas vencidas</small><strong>R$ {{ number_format(($stats['expenses_overdue'] ?? 0) + ($stats['financial_overdue'] ?? 0), 2, ',', '.') }}</strong><span>Receber e pagar em atraso</span></span>
        <x-ui-icon class="dashboard-kpi-arrow" name="arrow" :size="18" />
      </a>
    </section>

    <section class="dashboard-command-grid">
      <article class="dashboard-card dashboard-schedule-card">
        <header class="dashboard-card-heading">
          <div><span class="dashboard-card-kicker">Agenda</span><h2>{{ $showingToday ? 'Atendimentos de hoje' : 'Próximos atendimentos' }}</h2></div>
          <a href="{{ route('schedules.index') }}">Ver agenda <x-ui-icon name="arrow" :size="16" /></a>
        </header>
        <div class="dashboard-card-body dashboard-list">
          @forelse($scheduleItems as $appointment)
            <a class="dashboard-list-item" href="{{ route('appointments.edit', $appointment) }}">
              <span class="dashboard-time">{{ optional($appointment->scheduled_at)->format('H:i') }}</span>
              <span class="dashboard-list-main"><strong>{{ $appointment->patient?->name ?: $appointment->title }}</strong><small>{{ $appointment->tutor?->name ?: 'Responsável não informado' }}</small></span>
              <span class="badge muted-badge">{{ $appointment->status }}</span>
            </a>
          @empty
            <div class="dashboard-empty-state">
              <span><x-ui-icon name="calendar" :size="24" /></span>
              <strong>Agenda livre por enquanto</strong>
              <p>Nenhum atendimento encontrado para o período.</p>
              @can('appointments.manage')<a class="button secondary" href="{{ route('appointments.create') }}">Agendar consulta</a>@endcan
            </div>
          @endforelse
        </div>
      </article>

      <article class="dashboard-card dashboard-priority-card">
        <header class="dashboard-card-heading">
          <div><span class="dashboard-card-kicker">Foco do dia</span><h2>Prioridades operacionais</h2></div>
          <span class="dashboard-count-badge">{{ count($operationalPriorities ?? []) }}</span>
        </header>
        <div class="dashboard-card-body dashboard-priority-list">
          @forelse($operationalPriorities ?? [] as $priority)
            @php($priorityTone = $priority['level'] === 'danger' ? 'danger' : ($priority['level'] === 'warning' ? 'warning' : 'info'))
            <a class="dashboard-priority dashboard-priority-{{ $priorityTone }}" href="{{ $priority['url'] }}" data-dashboard-priority="{{ $priority['key'] }}">
              <span class="dashboard-priority-marker"><x-ui-icon name="warning" :size="18" /></span>
              <span class="dashboard-list-main"><small>{{ $priority['area'] }}</small><strong>{{ $priority['title'] }}</strong><span>{{ $priority['description'] }}</span></span>
              <span class="dashboard-priority-value">
                @if($priority['value_type'] === 'currency') R$ {{ number_format($priority['value'], 2, ',', '.') }} @else {{ number_format($priority['value'], 0, ',', '.') }} @endif
              </span>
            </a>
          @empty
            <div class="dashboard-empty-state dashboard-empty-success"><span><x-ui-icon name="sparkles" :size="24" /></span><strong>Operação em dia</strong><p>Nenhuma prioridade crítica nos indicadores acompanhados.</p></div>
          @endforelse
        </div>
      </article>

      <aside class="dashboard-side-stack">
        <article class="dashboard-card dashboard-quick-card">
          <header class="dashboard-card-heading"><div><span class="dashboard-card-kicker">Atalhos</span><h2>Começar agora</h2></div></header>
          <div class="dashboard-card-body dashboard-quick-grid">
            @can('tutors.manage')<a href="{{ route('tutores.create') }}"><span><x-ui-icon name="users" /></span><strong>Novo responsável</strong><small>Cadastro rápido</small></a>@endcan
            @can('patients.manage')<a href="{{ route('patients.create') }}"><span><x-ui-icon name="paw" /></span><strong>Novo paciente</strong><small>Ficha clínica</small></a>@endcan
            @can('appointments.manage')<a href="{{ route('appointments.create') }}"><span><x-ui-icon name="calendar" /></span><strong>Nova consulta</strong><small>Agendar horário</small></a>@endcan
            @can('service-orders.manage')<a href="{{ route('service-orders.create') }}"><span><x-ui-icon name="grooming" /></span><strong>Nova comanda</strong><small>Banho e tosa</small></a>@endcan
            @can('sales.manage')<a href="{{ route('sales.create') }}"><span><x-ui-icon name="sales" /></span><strong>Abrir PDV</strong><small>Venda no balcão</small></a>@endcan
            @can('products.manage')<a href="{{ route('products.create') }}"><span><x-ui-icon name="inventory" /></span><strong>Novo produto</strong><small>Catálogo e estoque</small></a>@endcan
          </div>
        </article>

        <article class="dashboard-card dashboard-finance-card">
          <header class="dashboard-card-heading">
            <div><span class="dashboard-card-kicker">Financeiro</span><h2>Pulso do mês</h2></div>
            <a href="{{ route('financial-transactions.cash-flow') }}">Detalhar <x-ui-icon name="arrow" :size="16" /></a>
          </header>
          <div class="dashboard-card-body">
            <div class="dashboard-finance-balance"><small>Resultado realizado</small><strong class="{{ (($stats['financial_month'] ?? 0) - ($stats['expenses_month'] ?? 0)) < 0 ? 'is-negative' : '' }}">R$ {{ number_format(($stats['financial_month'] ?? 0) - ($stats['expenses_month'] ?? 0), 2, ',', '.') }}</strong></div>
            <dl class="dashboard-finance-grid">
              <div><dt>Receita</dt><dd>R$ {{ number_format($stats['financial_month'] ?? 0, 2, ',', '.') }}</dd></div>
              <div><dt>Despesas</dt><dd>R$ {{ number_format($stats['expenses_month'] ?? 0, 2, ',', '.') }}</dd></div>
              <div><dt>A receber</dt><dd>R$ {{ number_format($stats['financial_pending'] ?? 0, 2, ',', '.') }}</dd></div>
              <div><dt>A pagar</dt><dd>R$ {{ number_format($stats['expenses_pending'] ?? 0, 2, ',', '.') }}</dd></div>
            </dl>
          </div>
        </article>
      </aside>
    </section>

    <section class="dashboard-secondary-grid">
      <article class="dashboard-card">
        <header class="dashboard-card-heading"><div><span class="dashboard-card-kicker">Relacionamento</span><h2>Pacientes recentes</h2></div><a href="{{ route('patients.index') }}">Ver pacientes <x-ui-icon name="arrow" :size="16" /></a></header>
        <div class="dashboard-card-body dashboard-entity-list">
          @forelse($latestPatients ?? [] as $patient)
            <a href="{{ route('patients.show', $patient) }}"><span class="dashboard-entity-avatar"><x-ui-icon name="paw" :size="18" /></span><span><strong>{{ $patient->name }}</strong><small>{{ $patient->animalSpecies?->name ?: 'Espécie não informada' }} · {{ $patient->tutor?->name ?: 'Sem responsável' }}</small></span><time>{{ optional($patient->created_at)->format('d/m') }}</time></a>
          @empty
            <div class="dashboard-inline-empty">Nenhum paciente cadastrado ainda.</div>
          @endforelse
        </div>
      </article>

      <article class="dashboard-card">
        <header class="dashboard-card-heading"><div><span class="dashboard-card-kicker">Clientes</span><h2>Responsáveis recentes</h2></div><a href="{{ route('tutores.index') }}">Ver responsáveis <x-ui-icon name="arrow" :size="16" /></a></header>
        <div class="dashboard-card-body dashboard-entity-list">
          @forelse($latestTutors ?? [] as $tutor)
            <a href="{{ route('tutores.edit', $tutor) }}"><span class="dashboard-entity-avatar dashboard-entity-avatar-blue"><x-ui-icon name="users" :size="18" /></span><span><strong>{{ $tutor->name }}</strong><small>{{ $tutor->patients_count }} {{ $tutor->patients_count === 1 ? 'paciente vinculado' : 'pacientes vinculados' }}</small></span><time>{{ optional($tutor->created_at)->format('d/m') }}</time></a>
          @empty
            <div class="dashboard-inline-empty">Nenhum responsável cadastrado ainda.</div>
          @endforelse
        </div>
      </article>
    </section>

    <section class="dashboard-secondary-grid">
      <article class="dashboard-card">
        <header class="dashboard-card-heading"><div><span class="dashboard-card-kicker">Monitoramento</span><h2>Central de alertas</h2></div><span class="badge {{ ($alertStats['critical'] ?? 0) > 0 ? 'danger' : 'success' }}">{{ $alertStats['total'] ?? 0 }} alertas</span></header>
        <div class="dashboard-card-body dashboard-alert-list">
          @forelse($alerts ?? [] as $alert)
            @php($alertTone = $alert['level'] === 'danger' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'info'))
            <a class="dashboard-alert dashboard-alert-{{ $alertTone }}" href="{{ $alert['url'] ?? route('inventory-movements.alerts') }}"><span><x-ui-icon name="warning" :size="18" /></span><span class="dashboard-list-main"><strong>{{ $alert['title'] }}</strong><small>{{ $alert['description'] }}</small></span><b>{{ $alert['count'] }}</b></a>
          @empty
            <div class="dashboard-inline-empty">Nenhum alerta de estoque, cadastro ou financeiro.</div>
          @endforelse
        </div>
      </article>

      <article class="dashboard-card dashboard-intelligence-card">
        <header class="dashboard-card-heading"><div><span class="dashboard-card-kicker">VetFlow Intelligence</span><h2>Qualidade do catálogo</h2></div><span class="badge {{ $healthBadge }}">{{ $intelligenceHealth['label'] }}</span></header>
        <div class="dashboard-card-body">
          <p class="dashboard-intelligence-description">{{ $intelligenceHealth['description'] }}</p>
          <div class="dashboard-intelligence-metrics">
            <div><small>Cobertura</small><strong>{{ number_format((float) ($intelligenceStats['coverage_percent'] ?? 0), 1, ',', '.') }}%</strong></div>
            <div><small>Sugestões</small><strong>{{ $intelligenceStats['suggestions_pending'] ?? 0 }}</strong></div>
            <div><small>Sem imagem</small><strong>{{ $intelligenceStats['missing_image'] ?? 0 }}</strong></div>
            <div><small>EAN inválido</small><strong>{{ $intelligenceStats['local_invalid_gtin'] ?? 0 }}</strong></div>
          </div>
          <div class="dashboard-intelligence-actions">
            @foreach(array_slice($intelligence['actions'] ?? [], 0, 3) as $action)
              <a href="{{ $action['url'] }}"><span>{{ $action['title'] }}</span><strong>{{ $action['count'] }}</strong></a>
            @endforeach
          </div>
          <a class="button secondary dashboard-intelligence-button" href="{{ $intelligence['routes']['products_diagnostics'] ?? route('products.diagnostics') }}">Abrir diagnóstico de produtos</a>
        </div>
      </article>
    </section>
  </div>
@endsection
