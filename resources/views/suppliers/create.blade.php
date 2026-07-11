@extends('layouts.admin')

@section('title', 'Novo fornecedor - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo fornecedor</h1>
      <p>Cadastro para compras e entrada de mercadorias.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        @include('suppliers.form', ['supplier' => null])
      </form>
    </div>
  </div>
@endsection
