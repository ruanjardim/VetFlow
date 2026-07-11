@extends('layouts.admin')

@section('title', 'Novo agendamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo agendamento</h1>
      <p>Registro na agenda.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('schedules.store') }}">
        @csrf
        @include('schedules.form', ['schedule' => null])
      </form>
    </div>
  </div>
@endsection
