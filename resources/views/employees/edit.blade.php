@extends('layouts.admin')

@section('title', 'Editar cadastro funcional - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar cadastro funcional</h1>
      <p>{{ $employee->name }} · {{ $employee->clinic?->trade_name ?? $employee->clinic?->corporate_name }}</p>
    </div>
    @if($employee->user)
      <a class="button secondary" href="{{ route('access-users.edit', $employee->user->id) }}">Editar login e permissões</a>
    @else
      <a class="button" href="{{ route('access-users.create', ['clinic_id' => $employee->clinic_id, 'employee_id' => $employee->id]) }}">Criar login e permissões</a>
    @endif
  </header>

  <form method="POST" action="{{ route('employees.update', $employee->id) }}" class="panel panel-body">
    @csrf
    @method('PUT')
    @include('employees.form')
  </form>
@endsection
