@extends('layouts.admin')
@section('title', 'Contrato e termos - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Contrato e termos</h1><p>Consulte as versões publicadas e registre o aceite do representante do estabelecimento.</p></div></header>

  @if(!$package_ready)
    <div class="alert warning"><strong>Pacote ainda não liberado para aceite.</strong> As cinco minutas precisam passar por revisão jurídica e publicação formal. Nenhuma minuta será tratada como contrato vigente.</div>
  @elseif($accepted_package_uuid)
    <div class="alert success"><strong>Pacote vigente já aceito.</strong> Comprovante: {{ $accepted_package_uuid }}. Um novo aceite será solicitado se documento, plano, preço ou período promocional mudar.</div>
  @endif

  <section class="panel">
    <div class="panel-heading"><div><h2>Condições comerciais registradas</h2><p>O aceite congela plano, preço, desconto, versão dos documentos e evidências técnicas.</p></div></div>
    <div class="panel-body">
      <div class="grid stats">
        <div class="stat"><span>Plano</span><strong>{{ $subscription?->plan?->name ?? 'Não configurado' }}</strong></div>
        <div class="stat"><span>Preço-base mensal</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($quote['base_price_cents']) }}</strong></div>
        <div class="stat"><span>Desconto atual</span><strong>{{ $quote['discount_percentage'] ? $quote['discount_percentage'].'%' : 'Sem desconto' }}</strong></div>
        <div class="stat"><span>Valor atual</span><strong>{{ \App\Modules\Saas\Services\SubscriptionPriceService::format($quote['final_price_cents']) }}</strong></div>
      </div>
      @if($quote['coupon_code'])<p class="muted">Cupom {{ $quote['coupon_code'] }}. Após o fim da promoção, prevalece o preço-base vigente conforme o instrumento contratual publicado.</p>@endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Documentos do pacote</h2><p>Abra cada documento para ler seu conteúdo completo.</p></div></div>
    <div class="panel-body table-wrap">
      <table><thead><tr><th>Documento</th><th>Versão</th><th>Vigência</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
        @forelse($documents as $document)
          <tr><td><strong>{{ $document->title }}</strong></td><td>{{ $document->version }}</td><td>{{ ($document->effective_at ?? $document->published_at)?->format('d/m/Y') }}</td><td><span class="badge {{ in_array($document->id, $accepted_document_ids, true) ? 'success' : 'warning' }}">{{ in_array($document->id, $accepted_document_ids, true) ? 'Aceito' : 'Pendente' }}</span></td><td><a class="button secondary" target="_blank" rel="noopener" href="{{ route('legal.show', $document->type) }}">Ler</a></td></tr>
        @empty<tr><td colspan="5" class="muted">Nenhum documento revisado e publicado.</td></tr>@endforelse
      </tbody></table>
    </div>
  </section>

  <form method="POST" action="{{ route('legal.acceptance.store') }}">@csrf
    <section class="panel"><div class="panel-heading"><div><h2>Registro do aceite</h2><p>Somente o administrador autorizado deve confirmar.</p></div></div><div class="panel-body">
      <label class="field"><span><input type="checkbox" name="acceptance_confirmed" value="1" required> Declaro que li as versões publicadas, possuo poderes para representar o estabelecimento e aceito o pacote jurídico e as condições comerciais exibidas.</span></label>
      <label class="field"><span><input type="checkbox" name="authority_confirmed" value="1" required> Confirmo que tenho autorização para representar e vincular contratualmente o estabelecimento informado.</span></label>
      <p class="muted">O VetFlow registrará data e hora, usuário, estabelecimento, IP, navegador, versão e hash de cada documento. O comprovante não poderá ser editado ou apagado pela aplicação.</p>
      <button class="button" type="submit" @disabled(!$package_ready)>Aceitar e gerar comprovante</button>
    </div></section>
  </form>
@endsection
