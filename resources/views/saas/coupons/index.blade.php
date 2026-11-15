@extends('layouts.admin')
@section('title', 'Cupons promocionais - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Cupons promocionais</h1><p>Descontos controlados de 10%, 20%, 30% ou 40%, com validade e histórico.</p></div><a class="button" href="{{ route('saas.coupons.create') }}">Novo cupom</a></header>
  <div class="alert warning"><strong>Cobrança administrativa:</strong> este módulo registra as condições e os preços da assinatura. Não executa cartão, PIX ou débito automático.</div>
  <section class="panel"><div class="panel-body table-wrap"><table><thead><tr><th>Código</th><th>Desconto</th><th>Planos</th><th>Validade</th><th>Duração</th><th>Usos</th><th>Status</th><th>Ação</th></tr></thead><tbody>
    @forelse($coupons as $coupon)<tr><td><strong>{{ $coupon->code }}</strong></td><td>{{ $coupon->percentage }}%</td><td>{{ $coupon->plans->isEmpty() ? 'Todos' : $coupon->plans->pluck('name')->join(', ') }}</td><td>{{ $coupon->valid_from?->format('d/m/Y H:i') }} a {{ $coupon->valid_until?->format('d/m/Y H:i') }}</td><td>{{ $coupon->duration_months }} {{ $coupon->duration_months === 1 ? 'mês' : 'meses' }}</td><td>{{ $coupon->promotions_count }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td><td><span class="badge {{ $coupon->isAvailableAt() ? 'success' : 'warning' }}">{{ $coupon->isAvailableAt() ? 'Disponível' : 'Indisponível' }}</span></td><td><a class="button secondary" href="{{ route('saas.coupons.edit', $coupon) }}">Editar</a></td></tr>
    @empty<tr><td colspan="8" class="muted">Nenhum cupom cadastrado.</td></tr>@endforelse
  </tbody></table></div></section>
  {{ $coupons->links() }}
@endsection
