@if(auth()->user()?->clinic_id === null && isset($clinics) && $clinics->isEmpty())
  <div class="field full">
    <div class="alert warning action-alert">
      <div>
        <strong>Nenhuma clinica cadastrada.</strong>
        <span>Cadastre uma clinica ativa antes de lancar entradas, vendas, estoque e financeiro.</span>
      </div>
      @can('clinics.manage')
        <a class="button secondary" href="{{ route('clinics.create') }}">Cadastrar clinica</a>
      @else
        <span class="muted">Peça ao administrador para cadastrar a primeira clinica.</span>
      @endcan
    </div>
  </div>
@endif
