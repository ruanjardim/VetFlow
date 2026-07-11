@extends('layouts.admin')

@section('title', 'Editar servico PetShop - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar servico PetShop</h1>
      <p>{{ $item->name }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('petshop-services.update', $item->id) }}">
        @csrf
        @method('PUT')
        @include('petshop-services.form', ['service' => $item])
      </form>
    </div>
  </div>
@endsection
