<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'VetFlow')</title>
  @if(file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <link rel="stylesheet" href="{{ route('assets.css', ['v' => filemtime(resource_path('css/app.css'))]) }}">
  @endif
  @stack('head')
</head>
<body>
  <main class="auth-shell @yield('auth-shell-class')">
    <section class="auth-card @yield('auth-card-class')">
      <div class="auth-brand">
        <span class="auth-brand-mark" aria-hidden="true">VF</span>
        <span class="auth-brand-copy">
          <strong>VetFlow</strong>
          <span>Gestão veterinária inteligente</span>
        </span>
      </div>

      @if(session('status'))
        <div class="alert success">{{ session('status') }}</div>
      @endif

      @if($errors->any())
        <div class="alert error">
          @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
          @endforeach
        </div>
      @endif

      @yield('content')
    </section>

    @hasSection('auth-visual')
      <aside class="auth-visual" aria-label="Imagem institucional VetFlow">
        @yield('auth-visual')
      </aside>
    @endif
  </main>
</body>
</html>
