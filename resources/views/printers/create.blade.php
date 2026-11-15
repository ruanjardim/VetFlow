@extends('layouts.admin')

@section('title', 'Nova impressora - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova impressora</h1>
      <p>Cadastre o equipamento e a forma como ele será usado no estabelecimento.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('printers.store') }}">
        @csrf
        @include('printers.form')
      </form>
    </div>
  </div>
@endsection
