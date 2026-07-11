@extends('layouts.admin')

@section('title', 'Nova consulta - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova consulta</h1>
      <p>Agendamento de atendimento.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('appointments.store') }}">
        @csrf
        @include('appointments.form', ['appointment' => null])
      </form>
    </div>
  </div>
@endsection
