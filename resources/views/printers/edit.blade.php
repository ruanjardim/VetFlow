@extends('layouts.admin')

@section('title', 'Editar impressora - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar impressora</h1>
      <p>{{ $printer->name }}</p>
    </div>
    <a class="button secondary" href="{{ route('printers.test', array_filter(['printer' => $printer->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Testar impressão</a>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('printers.update', $printer->id) }}">
        @csrf
        @method('PUT')
        @include('printers.form')
      </form>
    </div>
  </div>
@endsection
