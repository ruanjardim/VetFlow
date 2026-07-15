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
        @can('dashboard.view')
          <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @endcan
        @can('clinics.manage')
          <a class="{{ request()->routeIs('clinics.*') ? 'is-active' : '' }}" href="{{ route('clinics.index') }}">Clinicas</a>
        @endcan
        @can('tutors.manage')
          <a class="{{ request()->routeIs('tutores.*') ? 'is-active' : '' }}" href="{{ route('tutores.index') }}">Tutores</a>
        @endcan
        @can('patients.manage')
          <a class="{{ request()->routeIs('patients.*') ? 'is-active' : '' }}" href="{{ route('patients.index') }}">Pacientes</a>
        @endcan
        @can('schedules.manage')
          <a class="{{ request()->routeIs('schedules.*') ? 'is-active' : '' }}" href="{{ route('schedules.index') }}">Agenda</a>
        @endcan
        @can('appointments.manage')
          <a class="{{ request()->routeIs('appointments.*') ? 'is-active' : '' }}" href="{{ route('appointments.index') }}">Consultas</a>
        @endcan
        @can('petshop-services.manage')
          <a class="{{ request()->routeIs('petshop-services.*') ? 'is-active' : '' }}" href="{{ route('petshop-services.index') }}">Servicos PetShop</a>
        @endcan
        @can('service-orders.manage')
          <a class="{{ request()->routeIs('service-orders.*') ? 'is-active' : '' }}" href="{{ route('service-orders.index') }}">Comandas</a>
        @endcan
        @can('sales.manage')
          <a class="{{ request()->routeIs('sales.*') ? 'is-active' : '' }}" href="{{ route('sales.index') }}">PDV / Vendas</a>
        @endcan
        @can('products.manage')
          <a class="{{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}">Produtos</a>
        @endcan
        @can('global-products.manage')
          <a class="{{ request()->routeIs('global-products.*') ? 'is-active' : '' }}" href="{{ route('global-products.index') }}">Catalogo Global</a>
        @endcan
        @can('inventory.manage')
          <a class="{{ request()->routeIs('inventory-movements.*') && ! request()->routeIs('inventory-movements.alerts') ? 'is-active' : '' }}" href="{{ route('inventory-movements.index') }}">Estoque</a>
          <a class="{{ request()->routeIs('inventory-movements.alerts') ? 'is-active' : '' }}" href="{{ route('inventory-movements.alerts') }}">Alertas</a>
        @endcan
        @can('purchase-entries.manage')
          <a class="{{ request()->routeIs('purchase-entries.*') ? 'is-active' : '' }}" href="{{ route('purchase-entries.index') }}">Entradas</a>
        @endcan
        @can('suppliers.manage')
          <a class="{{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}">Fornecedores</a>
        @endcan
        @can('financial.manage')
          <a class="{{ request()->routeIs('financial-transactions.*') ? 'is-active' : '' }}" href="{{ route('financial-transactions.index') }}">Financeiro</a>
        @endcan
      </nav>
    </aside>

    <main class="main">
      <header class="user-bar">
        <div>
          <strong>{{ auth()->user()?->name }}</strong>
          <span>{{ auth()->user()?->email }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="button secondary" type="submit">Sair</button>
        </form>
      </header>

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
