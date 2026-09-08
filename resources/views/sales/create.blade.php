@extends('layouts.admin')

@php($quickMode = request('mode') !== 'advanced')

@section('title', ($quickMode ? 'PDV rapido PetShop' : 'Nova venda') . ' - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>{{ $quickMode ? 'PDV rapido PetShop' : 'Nova venda' }}</h1>
      <p>
        {{ $quickMode
          ? 'Selecione o responsável e o pet, adicione banho, tosa ou produtos e receba.'
          : 'Registre venda direta, servico avulso ou fechamento de comanda.' }}
      </p>
    </div>
    <div class="actions">
      @if($quickMode)
        <a class="button secondary" href="{{ route('sales.create', ['mode' => 'advanced']) }}">Venda avancada</a>
      @else
        <a class="button secondary" href="{{ route('sales.create') }}">Voltar ao PDV rapido</a>
      @endif
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('sales.store') }}">
        @csrf
        @include('sales.form', ['sale' => null, 'quickMode' => $quickMode])
      </form>
    </div>
  </div>
@endsection
