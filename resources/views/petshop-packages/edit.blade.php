@extends('layouts.admin')

@section('title', 'Editar pacote - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar pacote</h1>
      <p>Defina serviços, quantidades, preço e validade.</p>
    </div>
  </header>


  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('petshop-packages.update', $package->id) }}">
        @csrf
        @method('PUT')
        @include('petshop-packages.form')
      </form>
    </div>
  </div>
@endsection
