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

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Prioridades de hoje</h2>
        <p>Acoes operacionais ordenadas por impacto para orientar o trabalho da equipe.</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Area</th>
              <th>O que fazer</th>
              <th>Indicador</th>
              <th>Acao</th>
            </tr>
          </thead>
          <tbody>
            @forelse($operationalPriorities ?? [] as $priority)
              @php($priorityBadge = $priority['level'] === 'danger' ? 'danger' : ($priority['level'] === 'warning' ? 'warning' : 'muted-badge'))
              <tr data-dashboard-priority="{{ $priority['key'] }}">
                <td>{{ $priority['area'] }}</td>
                <td>
                  <a href="{{ $priority['url'] }}">
                    <strong>{{ $priority['title'] }}</strong>
                    <div class="muted">{{ $priority['description'] }}</div>
                  </a>
                </td>
                <td>
                  <a class="badge {{ $priorityBadge }}" href="{{ $priority['url'] }}">
                    @if($priority['value_type'] === 'currency')
                      R$ {{ number_format($priority['value'], 2, ',', '.') }}
                    @else
                      {{ number_format($priority['value'], 0, ',', '.') }}
                    @endif
                  </a>
                </td>
                <td><a class="button secondary" href="{{ $priority['url'] }}">{{ $priority['action'] }}</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="muted">Operacao em dia. Nenhuma prioridade pendente nos indicadores acompanhados.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  @php($alertStats = $alertSummary['stats'] ?? [])

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Central de alertas</h2>
        <p>Resumo do que precisa de acao no estoque, cadastro e financeiro.</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="grid stats inventory-lot-stats">
        <div class="stat">
          <span>Total</span>
          <strong>{{ $alertStats['total'] ?? 0 }}</strong>
        </div>
        <div class="stat">
          <span>Criticos</span>
          <strong>{{ $alertStats['critical'] ?? 0 }}</strong>
        </div>
        <div class="stat">
          <span>Atencao</span>
          <strong>{{ $alertStats['attention'] ?? 0 }}</strong>
        </div>
        <a class="stat stat-link" href="{{ route('inventory-movements.alerts') }}">
          <span>Estoque e cadastro</span>
          <strong>{{ $alertStats['inventory'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ route('financial-transactions.cash-flow') }}">
          <span>Financeiro</span>
          <strong>{{ $alertStats['financial'] ?? 0 }}</strong>
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
                <td colspan="3" class="muted">Nenhum alerta de estoque, cadastro ou financeiro no momento.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  @php($intelligence = $productIntelligence ?? [])
  @php($intelligenceStats = $intelligence['stats'] ?? [])
  @php($intelligenceHealth = $intelligence['health'] ?? ['label' => 'Sem dados', 'level' => 'muted-badge', 'description' => ''])
  @php($healthBadge = match ($intelligenceHealth['level'] ?? 'info') {
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'muted-badge',
  })

  <section class="panel nested-panel">
    <div class="panel-heading">
      <div>
        <h2>VetFlow Intelligence</h2>
        <p>Indicadores do Catalogo Global, sugestoes e qualidade dos produtos aprendidos.</p>
      </div>
      <div class="actions">
        <span class="badge {{ $healthBadge }}">{{ $intelligenceHealth['label'] }}</span>
        <a class="button secondary" href="{{ $intelligence['routes']['products_diagnostics'] ?? route('products.diagnostics') }}">Diagnostico produtos</a>
        <a class="button secondary" href="{{ route('global-products.index') }}">Ver catalogo</a>
      </div>
    </div>
    <div class="panel-body">
      <div class="intelligence-health">
        <strong>{{ $intelligenceHealth['description'] }}</strong>
        <span>Cobertura local-global: {{ number_format((float) ($intelligenceStats['coverage_percent'] ?? 0), 1, ',', '.') }}%</span>
      </div>

      <div class="grid stats inventory-lot-stats">
        <a class="stat stat-link" href="{{ $intelligence['routes']['catalog'] ?? route('global-products.index') }}">
          <span>Catalogo global</span>
          <strong>{{ $intelligenceStats['total'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['conflicts'] ?? route('global-products.index') }}">
          <span>Conflitos</span>
          <strong>{{ $intelligenceStats['conflict'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['suggestions'] ?? route('global-products.suggestions') }}">
          <span>Sugestoes</span>
          <strong>{{ $intelligenceStats['suggestions_pending'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['missing_image'] ?? route('global-products.index') }}">
          <span>Sem imagem</span>
          <strong>{{ $intelligenceStats['missing_image'] ?? 0 }}</strong>
        </a>
      </div>

      <div class="grid stats inventory-lot-stats">
        <a class="stat stat-link" href="{{ $intelligence['routes']['stale'] ?? route('global-products.index') }}">
          <span>Consulta antiga</span>
          <strong>{{ $intelligenceStats['stale'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['low_quality'] ?? route('global-products.index') }}">
          <span>Qualidade baixa</span>
          <strong>{{ $intelligenceStats['low_quality'] ?? 0 }}</strong>
        </a>
        <div class="stat">
          <span>Qualidade media</span>
          <strong>{{ number_format((float) ($intelligenceStats['average_quality'] ?? 0), 1, ',', '.') }}%</strong>
        </div>
        <div class="stat">
          <span>Produtos locais vinculados</span>
          <strong>{{ $intelligenceStats['linked_local_products'] ?? 0 }}/{{ $intelligenceStats['local_products'] ?? 0 }}</strong>
        </div>
      </div>

      <div class="grid stats inventory-lot-stats">
        <a class="stat stat-link" href="{{ $intelligence['routes']['products_unlinked'] ?? route('products.index', ['intelligence' => 'unlinked']) }}">
          <span>Locais sem global</span>
          <strong>{{ $intelligenceStats['unlinked_local_with_gtin'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['products_invalid_gtin'] ?? route('products.index', ['intelligence' => 'invalid_gtin']) }}">
          <span>EAN invalido</span>
          <strong>{{ $intelligenceStats['local_invalid_gtin'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['products_without_price'] ?? route('products.index', ['intelligence' => 'without_price']) }}">
          <span>Sem preco local</span>
          <strong>{{ $intelligenceStats['local_without_price'] ?? 0 }}</strong>
        </a>
        <a class="stat stat-link" href="{{ $intelligence['routes']['products_low_stock'] ?? route('products.index', ['intelligence' => 'low_stock']) }}">
          <span>Estoque local baixo</span>
          <strong>{{ $intelligenceStats['local_low_stock'] ?? 0 }}</strong>
        </a>
      </div>

      <div class="content-grid intelligence-grid">
        <div class="intelligence-section">
          <div class="intelligence-section-heading">
            <div>
              <h2>Acoes recomendadas</h2>
              <p>Prioridades para melhorar automacao e confiabilidade.</p>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Acao</th>
                  <th>Qtd</th>
                  <th>Atalho</th>
                </tr>
              </thead>
              <tbody>
                @forelse($intelligence['actions'] ?? [] as $action)
                  @php($badge = $action['level'] === 'danger' ? 'danger' : ($action['level'] === 'warning' ? 'warning' : 'muted-badge'))
                  <tr>
                    <td>
                      <a href="{{ $action['url'] }}">
                        <strong>{{ $action['title'] }}</strong>
                        <div class="muted">{{ $action['description'] }}</div>
                      </a>
                    </td>
                    <td><a class="badge {{ $badge }}" href="{{ $action['url'] }}">{{ $action['count'] }}</a></td>
                    <td><a class="button secondary" href="{{ $action['url'] }}">Abrir</a></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="muted">Nenhuma acao critica do Intelligence no momento.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="intelligence-section">
          <div class="intelligence-section-heading">
            <div>
              <h2>Aprendizados recentes</h2>
              <p>Ultimos produtos globais atualizados.</p>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Qualidade</th>
                </tr>
              </thead>
              <tbody>
                @forelse($intelligence['recent'] ?? [] as $product)
                  <tr>
                    <td>
                      <a href="{{ $product['url'] }}">
                        <strong>{{ $product['name'] }}</strong>
                        <div class="muted">{{ $product['gtin'] }} {{ $product['brand'] ? '- '.$product['brand'] : '' }}</div>
                      </a>
                    </td>
                    <td>{{ $product['quality'] }}%</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" class="muted">Nenhum aprendizado global ainda.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="content-grid intelligence-grid">
        <div class="intelligence-section">
          <div class="intelligence-section-heading">
            <div>
              <h2>Fontes mais usadas</h2>
              <p>Origens que mais alimentaram o catalogo.</p>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Fonte</th>
                  <th>Itens</th>
                  <th>Confianca media</th>
                </tr>
              </thead>
              <tbody>
                @forelse($intelligence['sources'] ?? [] as $source)
                  <tr>
                    <td>{{ $source['name'] }}</td>
                    <td>{{ $source['total'] }}</td>
                    <td>{{ number_format((float) $source['average_confidence'], 1, ',', '.') }}%</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="muted">Nenhuma fonte registrada ainda.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="intelligence-section">
          <div class="intelligence-section-heading">
            <div>
              <h2>Categorias globais</h2>
              <p>Principais grupos aprendidos.</p>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Categoria</th>
                  <th>Itens</th>
                </tr>
              </thead>
              <tbody>
                @forelse($intelligence['categories'] ?? [] as $category)
                  <tr>
                    <td><a href="{{ $category['url'] }}">{{ $category['category'] }}</a></td>
                    <td>{{ $category['total'] }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" class="muted">Nenhuma categoria global ainda.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
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
