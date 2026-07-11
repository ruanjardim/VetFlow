@extends('layouts.admin')

@section('title', 'Dashboard - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Dashboard</h1>
      <p>Operacao do dia, agenda e indicadores principais.</p>
    </div>
  </header>

  <section class="grid stats">
    <div class="stat">
      <span>Pacientes</span>
      <strong>{{ $stats['patients'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Tutores</span>
      <strong>{{ $stats['tutors'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Consultas hoje</span>
      <strong>{{ $stats['appointments_today'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Receita paga</span>
      <strong>R$ {{ number_format($stats['financial'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Produtos</span>
      <strong>{{ $stats['products'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Estoque baixo</span>
      <strong>{{ $stats['low_stock'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Servicos PetShop</span>
      <strong>{{ $stats['petshop_services_active'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Comandas abertas</span>
      <strong>{{ $stats['service_orders_open'] ?? 0 }}</strong>
    </div>
    <div class="stat">
      <span>Vendas hoje</span>
      <strong>R$ {{ number_format($stats['sales_today'] ?? 0, 2, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Rascunhos PDV</span>
      <strong>{{ $stats['sales_drafts'] ?? 0 }}</strong>
    </div>
  </section>

  @php($alertStats = $alertSummary['stats'] ?? [])
  @php($alertStatLinks = [
    'total' => route('inventory-movements.alerts'),
    'critical' => route('inventory-movements.alerts', ['level' => 'critical']),
    'attention' => route('inventory-movements.alerts', ['level' => 'attention']),
    'cadastro' => route('inventory-movements.alerts', ['level' => 'cadastro']),
  ])

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Alertas de estoque e produtos</h2>
        <p>Resumo do que precisa de acao no cadastro, lotes e vendas.</p>
      </div>
      <a class="button secondary" href="{{ route('inventory-movements.alerts') }}">Ver painel</a>
    </div>
    <div class="panel-body">
      <div class="grid stats inventory-lot-stats">
        <a class="stat stat-link" href="{{ $alertStatLinks['total'] }}">
          <span>Total</span>
          <strong>{{ $alertStats['total'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $alertStatLinks['critical'] }}">
          <span>Criticos</span>
          <strong>{{ $alertStats['critical'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $alertStatLinks['attention'] }}">
          <span>Atencao</span>
          <strong>{{ $alertStats['attention'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $alertStatLinks['cadastro'] }}">
          <span>Cadastro</span>
          <strong>{{ $alertStats['cadastro'] ?? 0 }}</strong>
        </a>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Alerta</th>
              <th>Quantidade</th>
              <th>Acao</th>
            </tr>
          </thead>
          <tbody>
            @forelse($alerts ?? [] as $alert)
              <tr>
                <td>
                  <a href="{{ $alert['url'] ?? route('inventory-movements.alerts') }}">
                    <strong>{{ $alert['title'] }}</strong>
                    <div class="muted">{{ $alert['description'] }}</div>
                  </a>
                </td>
                <td>
                  @php($badgeClass = $alert['level'] === 'danger' ? 'danger' : ($alert['level'] === 'warning' ? 'warning' : 'muted-badge'))
                  <a class="badge {{ $badgeClass }}" href="{{ $alert['url'] ?? route('inventory-movements.alerts') }}">{{ $alert['count'] }}</a>
                </td>
                <td><a class="button secondary" href="{{ $alert['url'] ?? route('inventory-movements.alerts') }}">Abrir</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="muted">Nenhum alerta de estoque ou produto no momento.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel">
      <div class="panel-body">
        <h2>Proximas consultas</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Titulo</th>
                <th>Data</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($nextAppointments ?? [] as $appointment)
                <tr>
                  <td>{{ $appointment->title }}</td>
                  <td>{{ optional($appointment->scheduled_at)->format('d/m/Y H:i') }}</td>
                  <td>{{ $appointment->status }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="3" class="muted">Nenhuma consulta futura cadastrada.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="panel">
        <div class="panel-body">
          <h2>Financeiro</h2>
          <p>A receber: R$ {{ number_format($stats['financial_pending'] ?? 0, 2, ',', '.') }}</p>
          <p>Recebimento vencido: R$ {{ number_format($stats['financial_overdue'] ?? 0, 2, ',', '.') }}</p>
          <p>Receita mes: R$ {{ number_format($stats['financial_month'] ?? 0, 2, ',', '.') }}</p>
          <p>A pagar: R$ {{ number_format($stats['expenses_pending'] ?? 0, 2, ',', '.') }}</p>
          <p>Pagamento vencido: R$ {{ number_format($stats['expenses_overdue'] ?? 0, 2, ',', '.') }}</p>
          <p>Despesas mes: R$ {{ number_format($stats['expenses_month'] ?? 0, 2, ',', '.') }}</p>
          <p>Estoque: R$ {{ number_format($stats['stock_value'] ?? 0, 2, ',', '.') }}</p>
          <p>Comandas hoje: R$ {{ number_format($stats['service_orders_day_total'] ?? 0, 2, ',', '.') }}</p>
          <p>Vendas mes: R$ {{ number_format($stats['sales_month'] ?? 0, 2, ',', '.') }}</p>
          <p>PDV pendente: R$ {{ number_format($stats['sales_pending_payment'] ?? 0, 2, ',', '.') }}</p>
        </div>
      </div>

      <div class="panel">
        <div class="panel-body">
          <h2>Atalhos</h2>
          <div class="actions">
            <a class="button" href="{{ route('appointments.create') }}">Nova consulta</a>
            <a class="button secondary" href="{{ route('tutores.create') }}">Novo tutor</a>
            <a class="button secondary" href="{{ route('products.create') }}">Novo produto</a>
            <a class="button secondary" href="{{ route('petshop-services.create') }}">Novo servico</a>
            <a class="button secondary" href="{{ route('service-orders.create') }}">Nova comanda</a>
            <a class="button secondary" href="{{ route('sales.create') }}">Nova venda</a>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
