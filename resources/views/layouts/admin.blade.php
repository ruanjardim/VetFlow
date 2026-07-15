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
    <script src="{{ route('assets.js', ['v' => filemtime(resource_path('js/app.js'))]) }}" defer></script>
  @endif
</head>
<body>
  <div class="shell">
    <aside class="sidebar">
      <a class="brand" href="{{ route('dashboard') }}">
        <strong>VetFlow</strong>
        <span>ERP veterinario</span>
      </a>

      <nav class="nav">
        <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="{{ request()->routeIs('clinics.*') ? 'is-active' : '' }}" href="{{ route('clinics.index') }}">Clinicas</a>
        <a class="{{ request()->routeIs('tutores.*') ? 'is-active' : '' }}" href="{{ route('tutores.index') }}">Tutores</a>
        <a class="{{ request()->routeIs('patients.*') ? 'is-active' : '' }}" href="{{ route('patients.index') }}">Pacientes</a>
        <a class="{{ request()->routeIs('schedules.*') ? 'is-active' : '' }}" href="{{ route('schedules.index') }}">Agenda</a>
        <a class="{{ request()->routeIs('appointments.*') ? 'is-active' : '' }}" href="{{ route('appointments.index') }}">Consultas</a>
        <a class="{{ request()->routeIs('petshop-services.*') ? 'is-active' : '' }}" href="{{ route('petshop-services.index') }}">Servicos PetShop</a>
        <a class="{{ request()->routeIs('service-orders.*') ? 'is-active' : '' }}" href="{{ route('service-orders.index') }}">Comandas</a>
        <a class="{{ request()->routeIs('sales.*') ? 'is-active' : '' }}" href="{{ route('sales.index') }}">PDV / Vendas</a>
        <a class="{{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}">Produtos</a>
        <a class="{{ request()->routeIs('global-products.*') ? 'is-active' : '' }}" href="{{ route('global-products.index') }}">Catalogo Global</a>
        <a class="{{ request()->routeIs('inventory-movements.*') && ! request()->routeIs('inventory-movements.alerts') ? 'is-active' : '' }}" href="{{ route('inventory-movements.index') }}">Estoque</a>
        <a class="{{ request()->routeIs('inventory-movements.alerts') ? 'is-active' : '' }}" href="{{ route('inventory-movements.alerts') }}">Alertas</a>
        <a class="{{ request()->routeIs('purchase-entries.*') ? 'is-active' : '' }}" href="{{ route('purchase-entries.index') }}">Entradas</a>
        <a class="{{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}">Fornecedores</a>
        <a class="{{ request()->routeIs('financial-transactions.*') ? 'is-active' : '' }}" href="{{ route('financial-transactions.index') }}">Financeiro</a>
      </nav>
    </aside>

    <main class="main">
      @if(session('success'))
        <div class="alert success">{{ session('success') }}</div>
      @endif

      @if(session('error'))
        <div class="alert error">{{ session('error') }}</div>
      @endif

      @if($errors->any())
        <div class="alert error">
          @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
          @endforeach
        </div>
      @endif

      @yield('content')
    </main>
  </div>
</body>
</html>
