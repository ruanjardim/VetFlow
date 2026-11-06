<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d151d">
  <meta name="robots" content="index, follow">
  <title>VetFlow | Sistema para Clínica Veterinária, Pet Shop e Banho e Tosa</title>
  <meta name="description" content="Sistema de gestão para clínicas veterinárias, pet shops, banho e tosa e negócios do segmento pet. Organize atendimentos, clientes e sua operação com o VetFlow.">
  <link rel="canonical" href="https://vetflowsys.com.br/">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:site_name" content="VetFlow">
  <meta property="og:title" content="VetFlow | Gestão veterinária inteligente">
  <meta property="og:description" content="Organize atendimentos, clientes, serviços, produtos e financeiro em uma plataforma de gestão para negócios do segmento pet.">
  <meta property="og:url" content="https://vetflowsys.com.br/">
  <meta property="og:image" content="https://vetflowsys.com.br/images/auth-malinois-square.webp">
  <meta property="og:image:alt" content="Cão em destaque na apresentação do VetFlow">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="VetFlow | Gestão veterinária inteligente">
  <meta name="twitter:description" content="Gestão de atendimentos, clientes, serviços, produtos e financeiro para negócios do segmento pet.">
  <meta name="twitter:image" content="https://vetflowsys.com.br/images/auth-malinois-square.webp">
  <link rel="preload" as="image" href="{{ asset('images/auth-malinois-square.webp') }}" type="image/webp">
  @vite('resources/css/landing.css')
  <script type="application/ld+json" nonce="{{ $cspNonce }}">
  {
    "@@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "@id": "https://vetflowsys.com.br/#organization",
        "name": "VetFlow",
        "url": "https://vetflowsys.com.br/",
        "email": "contato@vetflowsys.com.br",
        "contactPoint": {
          "@type": "ContactPoint",
          "contactType": "sales",
          "email": "comercial@vetflowsys.com.br",
          "availableLanguage": "Portuguese"
        }
      },
      {
        "@type": "SoftwareApplication",
        "@id": "https://vetflowsys.com.br/#software",
        "name": "VetFlow",
        "url": "https://vetflowsys.com.br/",
        "description": "Sistema de gestão para clínicas veterinárias, pet shops e negócios do segmento pet.",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "offers": {
          "@type": "AggregateOffer",
          "priceCurrency": "BRL",
          "lowPrice": "97.00",
          "highPrice": "347.00",
          "offerCount": "3"
        },
        "publisher": {"@id": "https://vetflowsys.com.br/#organization"}
      }
    ]
  }
  </script>
</head>
<body>
  <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="site-brand" href="{{ route('home') }}" aria-label="VetFlow, página inicial"><span class="brand-mark" aria-hidden="true">VF</span><span><strong>VetFlow</strong><small>Gestão veterinária inteligente</small></span></a>
      <nav class="site-nav" aria-label="Navegação principal">
        <a href="#para-quem">Para quem é</a>
        <a href="#recursos">Recursos</a>
        <a href="#planos">Planos</a>
        <a href="mailto:comercial@vetflowsys.com.br?subject=Demonstra%C3%A7%C3%A3o%20do%20VetFlow">Fale conosco</a>
        <a class="nav-login" href="{{ route('login') }}">Entrar no sistema <span aria-hidden="true">↗</span></a>
      </nav>
    </div>
  </header>

  <main id="conteudo">
    <section class="hero" aria-labelledby="hero-title">
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <p class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span> Uma operação mais conectada</p>
          <h1 id="hero-title">Gestão veterinária <em>inteligente.</em></h1>
          <p class="hero-lead">Uma plataforma para clínicas veterinárias, pet shops, banho e tosa e negócios do segmento pet. Organize o cuidado e a operação em um só lugar.</p>
          <div class="hero-actions"><a class="button-primary" href="mailto:comercial@vetflowsys.com.br?subject=Demonstra%C3%A7%C3%A3o%20do%20VetFlow">Solicitar demonstração <span aria-hidden="true">↗</span></a><a class="button-quiet" href="{{ route('login') }}">Entrar no sistema <span aria-hidden="true">→</span></a></div>
          <p class="hero-footnote">Planos a partir de <strong>R$ 97 por mês</strong>, com implantação assistida.</p>
        </div>
        <div class="hero-media" role="img" aria-label="Cães, gatos e cavalo representando os animais atendidos pelos negócios que usam o VetFlow">
          <div class="hero-media-slideshow" aria-hidden="true">
            <img class="hero-media-slide" src="{{ asset('images/auth-malinois-square.webp') }}" width="1254" height="1254" alt="" fetchpriority="high" decoding="async">
            <img class="hero-media-slide" src="{{ asset('images/auth-pintabian-horse-square.png') }}" width="1254" height="1254" alt="" decoding="async">
            <img class="hero-media-slide" src="{{ asset('images/auth-beagle-square.png') }}" width="1254" height="1254" alt="" decoding="async">
            <img class="hero-media-slide" src="{{ asset('images/auth-gray-cat-square.png') }}" width="1254" height="1254" alt="" decoding="async">
            <img class="hero-media-slide" src="{{ asset('images/auth-white-kitten-square.png') }}" width="1254" height="1254" alt="" decoding="async">
          </div>
          <div class="media-caption"><span class="caption-dot" aria-hidden="true"></span> Cuidado com cada detalhe da operação</div>
        </div>
      </div>
    </section>

    <section class="audience section" id="para-quem" aria-labelledby="audience-title">
      <div class="wrap">
        <div class="section-heading"><p class="eyebrow">Feito para o segmento pet</p><h2 id="audience-title">Uma gestão que acompanha o seu negócio.</h2><p>O VetFlow reúne fluxos clínicos, serviços e operação comercial em uma mesma plataforma.</p></div>
        <div class="audience-grid">
          <article class="audience-card"><span class="card-number">01 /</span><span class="card-icon" aria-hidden="true">✚</span><h3>Clínicas veterinárias</h3><p>Agenda, pacientes, atendimentos e registros clínicos organizados.</p></article>
          <article class="audience-card"><span class="card-number">02 /</span><span class="card-icon" aria-hidden="true">♡</span><h3>Pet shops</h3><p>Cadastros, serviços, produtos e vendas no mesmo ambiente.</p></article>
          <article class="audience-card"><span class="card-number">03 /</span><span class="card-icon" aria-hidden="true">✂</span><h3>Banho e tosa</h3><p>Serviços e comandas para acompanhar a rotina de atendimento.</p></article>
          <article class="audience-card"><span class="card-number">04 /</span><span class="card-icon" aria-hidden="true">▤</span><h3>Casas de ração</h3><p>Catálogo de produtos, estoque, compras e vendas em uma operação integrada.</p></article>
        </div>
      </div>
    </section>

    <section class="features section" id="recursos" aria-labelledby="features-title">
      <div class="wrap features-layout">
        <div class="features-intro"><p class="eyebrow">Recursos do VetFlow</p><h2 id="features-title">A rotina inteira, com mais clareza.</h2><p>Funcionalidades disponíveis no sistema para conectar o atendimento à gestão do negócio.</p><a class="text-link" href="{{ route('login') }}">Acessar o VetFlow <span aria-hidden="true">↗</span></a></div>
        <div class="feature-list">
          <article><span aria-hidden="true">01</span><div><h3>Agenda e atendimentos</h3><p>Agendamentos, consultas e acompanhamento da rotina clínica.</p></div></article>
          <article><span aria-hidden="true">02</span><div><h3>Clientes e pacientes</h3><p>Cadastros de responsáveis e animais vinculados à clínica.</p></div></article>
          <article><span aria-hidden="true">03</span><div><h3>Prontuários e vacinação</h3><p>Registros clínicos e histórico de vacinação por paciente.</p></div></article>
          <article><span aria-hidden="true">04</span><div><h3>Serviços, produtos e estoque</h3><p>Comandas, catálogo, entradas de compras e movimentação de estoque.</p></div></article>
          <article><span aria-hidden="true">05</span><div><h3>Vendas e financeiro</h3><p>Vendas, recebimentos, lançamentos financeiros e visão do fluxo de caixa.</p></div></article>
        </div>
      </div>
    </section>

    <section class="benefits section" aria-labelledby="benefits-title"><div class="wrap benefits-layout"><div><p class="eyebrow">Menos dispersão. Mais visão.</p><h2 id="benefits-title">Informação organizada para você focar no que importa.</h2></div><ul><li>Informações centralizadas</li><li>Atendimentos mais organizados</li><li>Rotina de gestão simplificada</li><li>Acesso online</li><li>Interface clara e moderna</li></ul></div></section>

    <section class="pricing section" id="planos" aria-labelledby="pricing-title">
      <div class="wrap">
        <div class="pricing-heading">
          <div><p class="eyebrow">Planos VetFlow</p><h2 id="pricing-title">Comece no tamanho certo. Evolua sem trocar de sistema.</h2></div>
          <p>Valores de lançamento por estabelecimento, para implantação piloto assistida. Todos os planos contam com acesso online e suporte de implantação.</p>
        </div>
        <div class="pricing-grid">
          <article class="pricing-card">
            <div class="plan-heading"><p class="plan-name">Essencial</p><p class="plan-description">Para organizar a operação comercial do dia a dia.</p></div>
            <p class="plan-price"><span>R$</span> 97<small>,00 / mês</small></p>
            <p class="plan-annual">ou R$ 970,00 por ano</p>
            <p class="plan-capacity"><strong>Até 3 usuários</strong> · 1 estabelecimento</p>
            <ul class="plan-features">
              <li>Clientes, responsáveis e pets</li>
              <li>PDV, caixa e comprovante não fiscal</li>
              <li>Produtos, estoque e etiquetas de balança</li>
              <li>Continuidade do PDV preparada para falta de internet</li>
            </ul>
            <a class="plan-action" href="mailto:comercial@vetflowsys.com.br?subject=Quero%20o%20plano%20Essencial%20do%20VetFlow">Quero o Essencial <span aria-hidden="true">↗</span></a>
          </article>

          <article class="pricing-card pricing-card-featured">
            <p class="plan-badge">Mais completo para pet shop</p>
            <div class="plan-heading"><p class="plan-name">Profissional</p><p class="plan-description">Para pet shops, banho e tosa e operações integradas.</p></div>
            <p class="plan-price"><span>R$</span> 197<small>,00 / mês</small></p>
            <p class="plan-annual">ou R$ 1.970,00 por ano</p>
            <p class="plan-capacity"><strong>Até 6 usuários</strong> · 1 estabelecimento</p>
            <ul class="plan-features">
              <li>Tudo do plano Essencial</li>
              <li>Agenda, serviços, comandas e pacotes</li>
              <li>Compras, fornecedores e estoque integrado</li>
              <li>Financeiro e visão gerencial da operação</li>
            </ul>
            <a class="plan-action" href="mailto:comercial@vetflowsys.com.br?subject=Quero%20o%20plano%20Profissional%20do%20VetFlow">Quero o Profissional <span aria-hidden="true">↗</span></a>
          </article>

          <article class="pricing-card">
            <div class="plan-heading"><p class="plan-name">Completo</p><p class="plan-description">Para unir a operação comercial e a rotina veterinária.</p></div>
            <p class="plan-price"><span>R$</span> 347<small>,00 / mês</small></p>
            <p class="plan-annual">ou R$ 3.470,00 por ano</p>
            <p class="plan-capacity"><strong>Até 15 usuários</strong> · implantação personalizada</p>
            <ul class="plan-features">
              <li>Tudo do plano Profissional</li>
              <li>Prontuário, vacinação, internação e receituário</li>
              <li>Comissões e controles operacionais avançados</li>
              <li>Acompanhamento de implantação personalizado</li>
            </ul>
            <a class="plan-action" href="mailto:comercial@vetflowsys.com.br?subject=Quero%20o%20plano%20Completo%20do%20VetFlow">Quero o Completo <span aria-hidden="true">↗</span></a>
          </article>
        </div>
        <div class="pricing-notes" aria-label="Condições dos planos">
          <p><strong>Contratação sob proposta:</strong> usuários adicionais, múltiplas unidades, migração avançada de dados, mensagens pagas e integração com hardware específico.</p>
          <p><strong>Emissão fiscal:</strong> ativada separadamente somente após configuração e homologação do estabelecimento. O comprovante padrão do PDV é não fiscal.</p>
        </div>
      </div>
    </section>

    <section class="closing section" aria-labelledby="closing-title"><div class="wrap closing-inner"><p class="eyebrow">VetFlow</p><h2 id="closing-title">Leve sua gestão para o próximo nível.</h2><p>Conecte as áreas do seu negócio em um único sistema.</p><a class="button-primary" href="mailto:comercial@vetflowsys.com.br?subject=Demonstra%C3%A7%C3%A3o%20do%20VetFlow">Falar com o VetFlow <span aria-hidden="true">↗</span></a></div></section>
  </main>

  <footer class="site-footer"><div class="wrap footer-inner"><span class="footer-brand"><span class="brand-mark" aria-hidden="true">VF</span><strong>VetFlow</strong></span><a href="mailto:comercial@vetflowsys.com.br">comercial@vetflowsys.com.br</a><a href="{{ route('login') }}">Acesso ao sistema <span aria-hidden="true">↗</span></a></div></footer>
</body>
</html>
