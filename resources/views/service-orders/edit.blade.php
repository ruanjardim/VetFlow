@extends('layouts.admin')

@section('title', 'Editar comanda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar comanda</h1>
      <p>{{ $item->code }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('service-orders.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('service-orders.form', ['order' => $item])
      </form>
    </div>
  </div>
@endsection
