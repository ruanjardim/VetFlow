<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <title>@yield('title', 'PDV - VetFlow')</title>
  @if(file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <link rel="stylesheet" href="{{ route('assets.css', ['v' => filemtime(resource_path('css/app.css'))]) }}">
    <script src="{{ route('assets.js', ['v' => filemtime(resource_path('js/app.js'))]) }}" defer></script>
  @endif
</head>
<body class="@yield('body_class')" data-pdv-offline-shell-page>
  <div class="shell">
    <main class="main">
      @yield('content')
    </main>
  </div>
</body>
</html>
