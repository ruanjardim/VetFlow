<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index, follow">
  <title>{{ $typeLabel }} | VetFlow</title>
  <meta name="description" content="{{ $typeLabel }} do VetFlow, com versão, vigência e situação de publicação identificadas.">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite('resources/css/landing.css')
</head>
<body class="legal-page">
  <a class="skip-link" href="#documento">Ir para o documento</a>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="site-brand" href="{{ route('home') }}" aria-label="VetFlow, página inicial"><span class="brand-mark" aria-hidden="true">VF</span><span><strong>VetFlow</strong><small>Gestão veterinária inteligente</small></span></a>
      <nav class="site-nav" aria-label="Navegação"><a href="{{ route('home') }}">Voltar ao site</a><a class="nav-login" href="{{ route('login') }}">Entrar no sistema</a></nav>
    </div>
  </header>

  <main id="documento" class="legal-main">
    <article class="wrap legal-document">
      <p class="eyebrow">Documentação jurídica e operacional</p>
      <h1>{{ $document?->title ?? $typeLabel }}</h1>
      @if(!$document)
        <div class="legal-alert legal-alert-warning"><strong>Documento em preparação.</strong> Esta página ainda não possui uma versão disponibilizada.</div>
      @else
        @if($document->status !== 'published')
          <div class="legal-alert legal-alert-warning"><strong>MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA.</strong> Este texto é informativo, não está publicado para aceite e pode ser alterado.</div>
        @else
          <div class="legal-alert legal-alert-success"><strong>Versão publicada.</strong> Vigência em {{ ($document->effective_at ?? $document->published_at)?->format('d/m/Y H:i') }}.</div>
        @endif
        <dl class="legal-metadata">
          <div><dt>Tipo</dt><dd>{{ $typeLabel }}</dd></div>
          <div><dt>Versão</dt><dd>{{ $document->version }}</dd></div>
          <div><dt>Situação</dt><dd>{{ $document->status === 'published' ? 'Publicada' : 'Minuta sem vigência' }}</dd></div>
          <div><dt>Integridade</dt><dd><code>{{ $document->content_hash }}</code></dd></div>
        </dl>
        <div class="legal-body">{{ $document->body }}</div>
      @endif
    </article>
  </main>

  <footer class="site-footer"><div class="wrap footer-inner"><span class="footer-brand"><span class="brand-mark" aria-hidden="true">VF</span><strong>VetFlow</strong></span><a href="mailto:comercial@vetflowsys.com.br">comercial@vetflowsys.com.br</a><a href="{{ route('home') }}">Página inicial</a></div></footer>
</body>
</html>
