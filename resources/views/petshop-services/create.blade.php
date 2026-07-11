@extends('layouts.admin')

@section('title', 'Novo servico PetShop - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo servico PetShop</h1>
      <p>Cadastro de servico comercial.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('petshop-services.store') }}">
        @csrf
        @include('petshop-services.form', ['service' => null])
      </form>
    </div>
  </div>
@endsection
