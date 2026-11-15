@extends('layouts.admin')
@section('title', 'Governança jurídica - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Governança jurídica</h1><p>Versões, revisão, publicação, integridade e aceites dos documentos.</p></div><a class="button" href="{{ route('saas.legal.create') }}">Nova versão</a></header>
  <div class="alert warning"><strong>Importante:</strong> os textos iniciais são minutas técnicas. Publicar somente após revisão de advogado, preenchimento dos dados da fornecedora e definição de SLA, backup, suporte, cancelamento e foro.</div>
  <section class="panel"><div class="panel-body table-wrap"><table><thead><tr><th>Documento</th><th>Versão</th><th>Status</th><th>Hash</th><th>Aceites</th><th>Revisão/publicação</th></tr></thead><tbody>
    @forelse($documents as $document)<tr><td><strong>{{ $document->title }}</strong><div class="muted">{{ \App\Modules\Saas\Models\LegalDocument::TYPES[$document->type] ?? $document->type }}</div></td><td>{{ $document->version }}</td><td><span class="badge {{ $document->status === 'published' ? 'success' : 'warning' }}">{{ $document->status === 'published' ? 'Publicado' : ($document->status === 'retired' ? 'Substituído' : 'Minuta') }}</span></td><td><code title="{{ $document->content_hash }}">{{ \Illuminate\Support\Str::limit($document->content_hash, 14) }}</code></td><td>{{ $document->acceptances_count }}</td><td>
      <a class="button secondary" target="_blank" rel="noopener" href="{{ route('legal.show', $document->type) }}">Visualizar</a>
      @if($document->status === 'draft' && $document->hasUnresolvedPublicationMarkers())<div class="alert warning" style="margin-top: 8px;">Esta minuta contém campos ou avisos pendentes e não pode ser publicada. Crie uma versão final após a revisão.</div>
      @elseif($document->status === 'draft')<form method="POST" action="{{ route('saas.legal.publish', $document) }}" style="margin-top: 8px;">@csrf<label class="field"><span>Advogado/revisor responsável</span><input name="reviewer_name" required></label><label class="field"><span><input type="checkbox" name="legal_review_confirmed" value="1" required> Confirmo revisão jurídica desta versão e autorizo a publicação.</span></label><button class="button" type="submit">Publicar versão</button></form>@else<div class="muted">{{ $document->reviewed_by_name }} · {{ $document->published_at?->format('d/m/Y H:i') }}</div>@endif
    </td></tr>@empty<tr><td colspan="6" class="muted">Execute a carga de dados para criar as cinco minutas iniciais.</td></tr>@endforelse
  </tbody></table></div></section>
@endsection
