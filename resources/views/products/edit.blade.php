@extends('layouts.admin')

@section('title', 'Editar produto - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar produto</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('products.update', $item->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('products.form', ['product' => $item])
      </form>
    </div>
  </div>
@endsection
