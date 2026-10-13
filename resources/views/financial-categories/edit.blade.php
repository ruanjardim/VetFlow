@extends('layouts.admin')

@section('title', 'Editar categoria financeira - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar categoria financeira</h1>
      <p>{{ $category->name }}. Alterações não apagam nem recategorizam lançamentos anteriores.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('financial-categories.update', $category->id) }}">
        @csrf
        @method('PUT')
        @include('financial-categories.form')
      </form>
    </div>
  </div>
@endsection
