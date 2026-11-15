@extends('layouts.admin')
@section('title', 'Planos SaaS - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Planos</h1><p>Composição comercial, limites e disponibilidade.</p></div><a class="button" href="{{ route('saas.plans.create') }}">Novo plano</a></header>
  <div class="panel"><div class="table-wrap"><table>
    <thead><tr><th>Plano</th><th>Mensal</th><th>Anual</th><th>Usuários</th><th>Unidades</th><th>Assinaturas</th><th>Status</th><th>Ação</th></tr></thead>
    <tbody>@forelse($plans as $plan)<tr>
      <td><strong>{{ $plan->name }}</strong><div class="muted">{{ $plan->slug }} @if($plan->internal) · compatibilidade interna @endif</div></td>
      <td>{{ $plan->monthly_price === null ? '—' : 'R$ '.number_format((float)$plan->monthly_price, 2, ',', '.') }}</td>
      <td>{{ $plan->annual_price === null ? '—' : 'R$ '.number_format((float)$plan->annual_price, 2, ',', '.') }}</td>
      <td>{{ $plan->max_users ?? 'Ilimitado' }}</td><td>{{ $plan->max_units ?? 'Ilimitado' }}</td><td>{{ $plan->subscriptions_count }}</td>
      <td><span class="badge {{ $plan->active ? 'success' : 'danger' }}">{{ $plan->active ? 'Ativo' : 'Inativo' }}</span></td>
      <td>@if(!$plan->internal)<a class="button secondary" href="{{ route('saas.plans.edit', $plan) }}">Editar</a>@else<span class="muted">Protegido</span>@endif</td>
    </tr>@empty<tr><td colspan="8" class="muted">Nenhum plano encontrado.</td></tr>@endforelse</tbody>
  </table></div></div>{{ $plans->links() }}
@endsection
