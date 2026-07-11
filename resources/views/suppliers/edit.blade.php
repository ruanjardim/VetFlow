@extends('layouts.admin')

@section('title', 'Editar fornecedor - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar fornecedor</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('suppliers.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('suppliers.form', ['supplier' => $item])
      </form>
    </div>
  </div>
@endsection
