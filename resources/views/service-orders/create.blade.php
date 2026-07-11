@extends('layouts.admin')

@section('title', 'Nova comanda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova comanda</h1>
      <p>Abra um atendimento com servicos e produtos.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('service-orders.store') }}">
        @csrf
        @include('service-orders.form', ['order' => null])
      </form>
    </div>
  </div>
@endsection
