@extends('layouts.admin')

@section('title', 'Novo cadastro funcional - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo cadastro funcional</h1>
      <p>Cadastre um colaborador com ou sem acesso ao sistema.</p>
    </div>
  </header>

  @if($clinics->isNotEmpty())
    <form method="GET" action="{{ route('employees.create') }}" class="panel panel-body" style="margin-bottom: 18px;">
      <div class="field">
        <label for="create-employee-clinic">Estabelecimento</label>
        <select id="create-employee-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <form method="POST" action="{{ route('employees.store') }}" class="panel panel-body">
    @csrf
    @include('employees.form')
  </form>
@endsection
