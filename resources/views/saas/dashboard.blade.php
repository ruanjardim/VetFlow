@extends('layouts.admin')

@section('title', 'Gestão SaaS - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Gestão SaaS</h1><p>Planos, assinaturas e implantação comercial do VetFlow.</p></div>
    <a class="button" href="{{ route('saas.onboarding.create') }}">Implantar novo cliente</a>
  </header>

  <section class="grid stats">
    <div class="stat"><span>Planos comerciais ativos</span><strong>{{ $activePlans }}</strong></div>
    <div class="stat"><span>Estabelecimentos ativos</span><strong>{{ $activeClinics }}</strong></div>
    <div class="stat"><span>Assinaturas em operação</span><strong>{{ $activeSubscriptions }}</strong></div>
    <div class="stat"><span>Usuários ativos</span><strong>{{ $activeUsers }}</strong></div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Administração comercial</h2><p>A composição dos planos e as exceções ficam registradas no banco.</p></div></div>
    <div class="panel-body">
      <div class="form-actions">
        <a class="button" href="{{ route('saas.plans.index') }}">Gerenciar planos</a>
        <a class="button secondary" href="{{ route('saas.establishments.index') }}">Ver estabelecimentos</a>
      </div>
      <div class="badge-list" style="margin-top: 18px;">
        @foreach($subscriptionsByStatus as $status => $total)<span class="badge muted-badge">{{ ucfirst($status) }}: {{ $total }}</span>@endforeach
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Estabelecimentos recentes</h2><p>Últimos cadastros disponíveis para configuração.</p></div></div>
    <div class="panel-body table-wrap">
      <table><thead><tr><th>Estabelecimento</th><th>Plano</th><th>Status</th><th>Ação</th></tr></thead><tbody>
        @forelse($recentClinics as $clinic)
          <tr><td><strong>{{ $clinic->trade_name ?: $clinic->corporate_name }}</strong><div class="muted">{{ $clinic->email }}</div></td><td>{{ $clinic->subscription?->plan?->name ?? 'Sem assinatura' }}</td><td><span class="badge {{ $clinic->subscription?->status === 'active' ? 'success' : 'warning' }}">{{ $clinic->subscription?->status ?? 'pendente' }}</span></td><td><a class="button secondary" href="{{ route('saas.establishments.show', $clinic) }}">Abrir</a></td></tr>
        @empty<tr><td colspan="4" class="muted">Nenhum estabelecimento cadastrado.</td></tr>@endforelse
      </tbody></table>
    </div>
  </section>
@endsection
