@extends('layouts.admin')

@section('title', 'Editar clinica - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar clinica</h1>
      <p>{{ $item->trade_name ?? $item->corporate_name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('clinics.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('clinics.form', ['clinic' => $item])
      </form>
    </div>
  </div>
@endsection
