@extends('layouts.admin')

@section('title', 'Novo responsável - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo responsável</h1>
      <p>Cadastro da pessoa responsável pelo paciente.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('tutores.store') }}" data-tutor-form>
        @csrf
        @include('tutors.form', ['tutor' => null])
      </form>
    </div>
  </div>
@endsection
