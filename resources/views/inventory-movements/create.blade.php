@extends('layouts.admin')

@section('title', 'Nova movimentacao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova movimentacao</h1>
      <p>Atualizacao do saldo de produto.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('inventory-movements.store') }}">
        @csrf
        @include('inventory-movements.form', ['movement' => null])
      </form>
    </div>
  </div>
@endsection
