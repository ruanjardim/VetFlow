@extends('layouts.admin')

@section('title', 'Novo tutor - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo tutor</h1>
      <p>Cadastro do responsavel.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('tutores.store') }}">
        @csrf
        @include('tutors.form', ['tutor' => null])
      </form>
    </div>
  </div>
@endsection
