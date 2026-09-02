@extends('layouts.admin')

@section('title', 'Editar responsável - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar responsável</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('tutores.update', $item->id) }}" data-tutor-form>
        @csrf
        @method('PUT')
        @include('tutors.form', ['tutor' => $item])
      </form>
    </div>
  </div>
@endsection
