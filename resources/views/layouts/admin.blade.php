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
        <span class="brand-heading">
          <strong>VetFlow</strong>
          @if($brandIconKey)
            <x-brand-animal-icon :icon="$brandIconKey" />
          @endif
        </span>
        <span class="brand-subtitle">ERP veterinario</span>
      </a>

      <nav class="nav">
        @can('dashboard.view')
          <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
        @endcan

        @canany(['tutors.manage', 'patients.manage', 'medical-records.manage', 'vaccinations.manage', 'hospitalizations.manage', 'prescriptions.manage'])
          <details class="nav-group" @if(request()->routeIs('tutores.*', 'patients.*', 'medical-records.*', 'vaccinations.*', 'hospitalizations.*', 'prescriptions.*')) open @endif>
            <summary><span>Atendimento clínico</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('tutors.manage')<a class="{{ request()->routeIs('tutores.*') ? 'is-active' : '' }}" href="{{ route('tutores.index') }}">Responsáveis</a>@endcan
              @can('patients.manage')<a class="{{ request()->routeIs('patients.*') ? 'is-active' : '' }}" href="{{ route('patients.index') }}">Pacientes</a>@endcan
              @can('medical-records.manage')<a class="{{ request()->routeIs('medical-records.*') ? 'is-active' : '' }}" href="{{ route('medical-records.index') }}">Prontuários</a>@endcan
              @can('prescriptions.manage')<a class="{{ request()->routeIs('prescriptions.*') ? 'is-active' : '' }}" href="{{ route('prescriptions.index') }}">Prescrições</a>@endcan
              @can('vaccinations.manage')<a class="{{ request()->routeIs('vaccinations.*') ? 'is-active' : '' }}" href="{{ route('vaccinations.index') }}">Vacinação</a>@endcan
              @can('hospitalizations.manage')<a class="{{ request()->routeIs('hospitalizations.*') ? 'is-active' : '' }}" href="{{ route('hospitalizations.index') }}">Internações</a>@endcan
            </div>
          </details>
        @endcanany

        @canany(['schedules.manage', 'appointments.manage'])
          <details class="nav-group" @if(request()->routeIs('schedules.*', 'appointments.*')) open @endif>
            <summary><span>Agenda</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('schedules.manage')<a class="{{ request()->routeIs('schedules.*') ? 'is-active' : '' }}" href="{{ route('schedules.index') }}">Agenda visual</a>@endcan
              @can('appointments.manage')
                <a class="{{ request()->routeIs('appointments.index', 'appointments.create', 'appointments.edit') ? 'is-active' : '' }}" href="{{ route('appointments.index') }}">Consultas</a>
                <a class="{{ request()->routeIs('appointments.reminders') ? 'is-active' : '' }}" href="{{ route('appointments.reminders') }}">Lembretes</a>
              @endcan
            </div>
          </details>
        @endcanany

        @canany(['petshop-services.manage', 'service-orders.manage', 'sales.manage'])
          <details class="nav-group" @if(request()->routeIs('petshop-services.*', 'service-orders.*', 'sales.*')) open @endif>
            <summary><span>Vendas e serviços</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('sales.manage')
                <a class="{{ request()->routeIs('sales.index', 'sales.create', 'sales.edit') ? 'is-active' : '' }}" href="{{ route('sales.index') }}">Ponto de venda</a>
                <a class="{{ request()->routeIs('sales.cashier', 'sales.cashier.close') ? 'is-active' : '' }}" href="{{ route('sales.cashier') }}">Movimentos de caixa</a>
                <a class="{{ request()->routeIs('sales.profitability') ? 'is-active' : '' }}" href="{{ route('sales.profitability') }}">Rentabilidade</a>
              @endcan
              @can('service-orders.manage')<a class="{{ request()->routeIs('service-orders.*') ? 'is-active' : '' }}" href="{{ route('service-orders.index') }}">Comandas</a>@endcan
              @can('petshop-services.manage')<a class="{{ request()->routeIs('petshop-services.*') ? 'is-active' : '' }}" href="{{ route('petshop-services.index') }}">Serviços PetShop</a>@endcan
            </div>
          </details>
        @endcanany

        @canany(['products.manage', 'global-products.manage', 'inventory.manage', 'purchase-entries.manage', 'suppliers.manage'])
          <details class="nav-group" @if(request()->routeIs('products.*', 'global-products.*', 'inventory-movements.*', 'purchase-entries.*', 'suppliers.*')) open @endif>
            <summary><span>Estoque e compras</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('products.manage')<a class="{{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}">Produtos</a>@endcan
              @can('inventory.manage')
                <a class="{{ request()->routeIs('inventory-movements.index', 'inventory-movements.create', 'inventory-movements.edit') ? 'is-active' : '' }}" href="{{ route('inventory-movements.index') }}">Movimentações</a>
                <a class="{{ request()->routeIs('inventory-movements.alerts') ? 'is-active' : '' }}" href="{{ route('inventory-movements.alerts') }}">Alertas</a>
              @endcan
              @can('purchase-entries.manage')
                <a class="{{ request()->routeIs('purchase-entries.index', 'purchase-entries.create', 'purchase-entries.edit') ? 'is-active' : '' }}" href="{{ route('purchase-entries.index') }}">Entradas</a>
                <a class="{{ request()->routeIs('purchase-entries.replenishment') ? 'is-active' : '' }}" href="{{ route('purchase-entries.replenishment') }}">Reposição sugerida</a>
              @endcan
              @can('suppliers.manage')<a class="{{ request()->routeIs('suppliers.*') ? 'is-active' : '' }}" href="{{ route('suppliers.index') }}">Fornecedores</a>@endcan
              @can('global-products.manage')<a class="{{ request()->routeIs('global-products.*') ? 'is-active' : '' }}" href="{{ route('global-products.index') }}">Catálogo global</a>@endcan
            </div>
          </details>
        @endcanany

        @canany(['financial.manage', 'commissions.manage'])
          <details class="nav-group" @if(request()->routeIs('financial-transactions.*', 'commissions.*')) open @endif>
            <summary><span>Financeiro</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('financial.manage')
                <a class="{{ request()->routeIs('financial-transactions.index', 'financial-transactions.create', 'financial-transactions.edit') ? 'is-active' : '' }}" href="{{ route('financial-transactions.index') }}">Contas e lançamentos</a>
                <a class="{{ request()->routeIs('financial-transactions.cash-flow') ? 'is-active' : '' }}" href="{{ route('financial-transactions.cash-flow') }}">Fluxo de caixa</a>
              @endcan
              @can('commissions.manage')<a class="{{ request()->routeIs('commissions.*') ? 'is-active' : '' }}" href="{{ route('commissions.index') }}">Comissões</a>@endcan
            </div>
          </details>
        @endcanany

        @canany(['patients.manage', 'medical-records.manage', 'vaccinations.manage'])
          <details class="nav-group" @if(request()->routeIs('patient-catalog.*', 'pathology-catalog.*', 'exam-catalog.*', 'vaccine-catalog.*')) open @endif>
            <summary><span>Cadastros</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @can('patients.manage')
                <a class="{{ request()->routeIs('patient-catalog.species') ? 'is-active' : '' }}" href="{{ route('patient-catalog.species') }}">Espécies</a>
                <a class="{{ request()->routeIs('patient-catalog.breeds') ? 'is-active' : '' }}" href="{{ route('patient-catalog.breeds') }}">Raças e variedades</a>
                <a class="{{ request()->routeIs('patient-catalog.coats') ? 'is-active' : '' }}" href="{{ route('patient-catalog.coats') }}">Pelagens e padrões</a>
              @endcan
              @can('medical-records.manage')
                <a class="{{ request()->routeIs('pathology-catalog.*') ? 'is-active' : '' }}" href="{{ route('pathology-catalog.index') }}">Patologias</a>
                <a class="{{ request()->routeIs('exam-catalog.*') ? 'is-active' : '' }}" href="{{ route('exam-catalog.index') }}">Exames</a>
              @endcan
              @can('vaccinations.manage')<a class="{{ request()->routeIs('vaccine-catalog.*') ? 'is-active' : '' }}" href="{{ route('vaccine-catalog.index') }}">Vacinas</a>@endcan
              @can('patients.manage')
                <a class="{{ request()->routeIs('patient-catalog.specialties') ? 'is-active' : '' }}" href="{{ route('patient-catalog.specialties') }}">Minhas espécies de atuação</a>
              @endcan
            </div>
          </details>
        @endcanany

        @canany(['clinics.manage', 'clinic-branding.manage', 'users.manage', 'implementation.manage'])
          <details class="nav-group" @if(request()->routeIs('clinics.*', 'clinic-branding.*', 'access-users.*', 'implementation.*')) open @endif>
            <summary><span>Administração</span><span class="nav-chevron">⌄</span></summary>
            <div class="nav-submenu">
              @if(auth()->user()?->clinic_id === null)
                @can('clinics.manage')
                  <a class="{{ request()->routeIs('clinics.*') ? 'is-active' : '' }}" href="{{ route('clinics.index') }}">Clínicas</a>
                @endcan
              @endif
              @if(auth()->user()?->clinic_id !== null)
                @can('clinic-branding.manage')<a class="{{ request()->routeIs('clinic-branding.*') ? 'is-active' : '' }}" href="{{ route('clinic-branding.edit') }}">Identidade visual</a>@endcan
              @endif
              @can('users.manage')<a class="{{ request()->routeIs('access-users.*') ? 'is-active' : '' }}" href="{{ route('access-users.index') }}">Usuários e acessos</a>@endcan
              @can('implementation.manage')<a class="{{ request()->routeIs('implementation.*') ? 'is-active' : '' }}" href="{{ route('implementation.index') }}">Implantação</a>@endcan
            </div>
          </details>
        @endcanany
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
