@extends('layouts.admin')

@section('title', 'Editar lancamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar lancamento</h1>
      <p>{{ $item->description }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('financial-transactions.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('financial-transactions.form', ['transaction' => $item])
      </form>
    </div>
  </div>
@endsection
