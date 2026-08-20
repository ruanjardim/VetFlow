@extends('layouts.admin')

@section('title', 'Editar prescrição - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar prescrição #{{ $prescription->id }}</h1>
      <p>Somente o rascunho pode ser alterado.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('prescriptions.update', $prescription->id) }}" data-prescription-form>
    @csrf
    @method('PUT')
    @include('prescriptions.form')
  </form>
@endsection
