@extends('layouts.admin')

@section('title', 'Nova categoria financeira - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova categoria financeira</h1>
      <p>Crie uma classificação própria para entradas ou saídas.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('financial-categories.store') }}">
        @csrf
        @include('financial-categories.form')
      </form>
    </div>
  </div>
@endsection
