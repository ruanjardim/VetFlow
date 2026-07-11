@extends('layouts.admin')

@section('title', 'Editar movimentacao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar movimentacao</h1>
      <p>{{ $item->product?->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('inventory-movements.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('inventory-movements.form', ['movement' => $item])
      </form>
    </div>
  </div>
@endsection
