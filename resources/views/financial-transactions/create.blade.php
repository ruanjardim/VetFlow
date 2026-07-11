@extends('layouts.admin')

@section('title', 'Novo lancamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo lancamento</h1>
      <p>Registro financeiro.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('financial-transactions.store') }}">
        @csrf
        @include('financial-transactions.form', ['transaction' => null])
      </form>
    </div>
  </div>
@endsection
