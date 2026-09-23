@extends('layouts.admin')

@section('title', 'Novo pacote - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo pacote</h1>
      <p>Defina serviços, quantidades, preço e validade.</p>
    </div>
  </header>


  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('petshop-packages.store') }}">
        @csrf

        @include('petshop-packages.form')
      </form>
    </div>
  </div>
@endsection
