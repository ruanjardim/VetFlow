@extends('layouts.admin')

@section('title', 'Tributação de serviços - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Tributação de serviços</h1><p>Cadastre a classificação mínima usada para preparar NFS-e em homologação.</p></div>
    <a class="button secondary" href="{{ route('fiscal.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Central fiscal</a>
  </header>

  <div class="alert warning">Os códigos nacionais e municipais, a incidência e a alíquota precisam ser confirmados com a contabilidade e com as regras do município.</div>

  @if($requiresClinic)
    <form method="GET" action="{{ route('fiscal.services.index') }}" class="panel panel-body">
      <div class="field"><label for="fiscal-service-clinic">Estabelecimento</label><select id="fiscal-service-clinic" name="clinic_id" data-auto-submit-select>@foreach($clinics as $clinic)<option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>@endforeach</select></div>
    </form>
  @endif

  <section class="grid stats">
    <div class="stat"><span>Serviços ativos</span><strong>{{ $stats['total'] }}</strong></div>
    <div class="stat"><span>Prontos para homologação</span><strong>{{ $stats['ready'] }}</strong></div>
    <div class="stat"><span>Pendentes</span><strong>{{ $stats['pending'] }}</strong></div>
  </section>

  <form method="GET" action="{{ route('fiscal.services.index') }}" class="panel panel-body">
    @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
    <div class="form-grid">
      <div class="field"><label for="service-tax-search">Buscar</label><input id="service-tax-search" name="search" value="{{ request('search') }}" placeholder="Nome ou categoria"></div>
      <div class="field"><label for="service-tax-status">Situação</label><select id="service-tax-status" name="status"><option value="all" @selected($status === 'all')>Todos</option><option value="pending" @selected($status === 'pending')>Pendentes</option><option value="ready" @selected($status === 'ready')>Prontos</option></select></div>
      <div class="field full"><div class="actions"><button type="submit">Filtrar</button></div></div>
    </div>
  </form>

  <section class="panel">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Serviço</th><th>Tributação nacional</th><th>Lista de serviço</th><th>ISS</th><th>Situação</th><th>Ação</th></tr></thead>
        <tbody>
          @forelse($services as $service)
            <tr>
              <td><strong>{{ $service->name }}</strong><div class="muted">{{ $service->category ?: 'Sem categoria' }}</div></td>
              <td>{{ $service->fiscalProfile?->national_taxation_code ?? '-' }}</td>
              <td>{{ $service->fiscalProfile?->service_list_item ?? '-' }}</td>
              <td>{{ $service->fiscalProfile?->iss_rate !== null ? number_format((float) $service->fiscalProfile->iss_rate, 2, ',', '.').'%' : '-' }}</td>
              <td><span class="badge {{ $service->fiscalProfile?->ready ? 'success' : 'warning' }}">{{ $service->fiscalProfile?->ready ? 'Pronto' : 'Pendente' }}</span></td>
              <td><a class="button secondary" href="{{ route('fiscal.services.edit', array_filter(['service' => $service->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Classificar</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Nenhum serviço encontrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $services->links() }}
@endsection
