@extends('layouts.admin')
@section('title', 'Estabelecimentos SaaS - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Estabelecimentos</h1><p>Assinaturas, unidades e estado operacional dos tenants.</p></div><a class="button" href="{{ route('saas.onboarding.create') }}">Implantar novo cliente</a></header>
  <div class="panel"><div class="table-wrap"><table><thead><tr><th>Estabelecimento</th><th>Tipo</th><th>Plano</th><th>Assinatura</th><th>Unidades</th><th>Status</th><th>Ação</th></tr></thead><tbody>
    @forelse($clinics as $clinic)<tr>
      <td><strong>{{ $clinic->trade_name ?: $clinic->corporate_name }}</strong><div class="muted">{{ $clinic->documentLabel() }}: {{ $clinic->formattedDocument() }}</div></td>
      <td>{{ $clinic->business_type ?: 'Não informado' }}</td><td>{{ $clinic->subscription?->plan?->name ?? 'Sem plano' }}</td><td>{{ $clinic->subscription?->status ?? 'pendente' }}</td><td>{{ 1 + $clinic->children_count }}</td>
      <td><span class="badge {{ $clinic->active ? 'success' : 'danger' }}">{{ $clinic->active ? 'Ativo' : 'Inativo' }}</span></td><td><a class="button secondary" href="{{ route('saas.establishments.show', $clinic) }}">Gerenciar</a></td>
    </tr>@empty<tr><td colspan="7" class="muted">Nenhum estabelecimento encontrado.</td></tr>@endforelse
  </tbody></table></div></div>{{ $clinics->links() }}
@endsection
