@extends('layouts.admin')

@section('title', 'Editar agendamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar agendamento</h1>
      <p>{{ $item->title }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('schedules.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('schedules.form', ['schedule' => $item])
      </form>
    </div>
  </div>
@endsection
