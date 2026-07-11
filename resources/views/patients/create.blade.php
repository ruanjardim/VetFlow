@extends('layouts.admin')

@section('title', 'Novo paciente - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo paciente</h1>
      <p>Cadastro do pet.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('patients.store') }}">
        @csrf
        @include('patients.form', ['patient' => null])
      </form>
    </div>
  </div>
@endsection
