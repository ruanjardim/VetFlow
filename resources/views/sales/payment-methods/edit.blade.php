@extends('layouts.admin')

@section('title', 'Editar forma de pagamento - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar forma de pagamento</h1>
      <p>{{ $method->name }}. As mudanças valem para os próximos recebimentos; os já registrados mantêm a taxa e o prazo da época.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.payment-methods.update', $method->id) }}">
        @csrf
        @method('PUT')
        @include('sales.payment-methods.form')
      </form>
    </div>
  </div>
@endsection
