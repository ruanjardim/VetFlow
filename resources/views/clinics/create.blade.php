@extends('layouts.admin')

@section('title', 'Nova clinica - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova clinica</h1>
      <p>Cadastro da unidade.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('clinics.store') }}">
        @csrf
        @include('clinics.form', ['clinic' => null])
      </form>
    </div>
  </div>
@endsection
