@extends('layouts.admin')

@section('title', 'Editar consulta - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar consulta</h1>
      <p>{{ $item->title }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('appointments.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('appointments.form', ['appointment' => $item])
      </form>
    </div>
  </div>
@endsection
