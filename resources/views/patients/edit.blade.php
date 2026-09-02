@extends('layouts.admin')

@section('title', 'Editar paciente - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar paciente</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('patients.update', $item->id) }}" data-patient-form>
        @csrf
        @method('PUT')
        @include('patients.form', ['patient' => $item])
      </form>
    </div>
  </div>
@endsection
