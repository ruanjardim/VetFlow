@extends('layouts.admin')

@section('title', 'Editar tutor - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar tutor</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('tutores.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('tutors.form', ['tutor' => $item])
      </form>
    </div>
  </div>
@endsection
