@extends('layouts.admin')

@section('title', 'Nova forma de pagamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova forma de pagamento</h1>
      <p>Cadastre a maquininha, a taxa e o prazo em que o valor cai na conta.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.payment-methods.store') }}">
        @csrf
        @include('sales.payment-methods.form')
      </form>
    </div>
  </div>
@endsection
