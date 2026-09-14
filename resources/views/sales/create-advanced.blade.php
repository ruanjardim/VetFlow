@extends('layouts.admin')

@section('title', 'Nova venda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova venda</h1>
      <p>Registre venda direta, servico avulso ou fechamento de comanda.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        @include('sales.form', ['sale' => null])
      </form>
    </div>
  </div>
@endsection
