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
</head>
<body>
  <main class="auth-shell">
    <section class="auth-card">
      <div class="auth-brand">
        <strong>VetFlow</strong>
        <span>ERP veterinario seguro</span>
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
  </main>
</body>
</html>
