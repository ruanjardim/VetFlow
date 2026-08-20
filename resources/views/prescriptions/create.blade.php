@extends('layouts.admin')

@section('title', 'Nova prescrição - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova prescrição</h1>
      <p>Crie um rascunho revisável antes da finalização.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('prescriptions.store') }}" data-prescription-form>
    @csrf
    @include('prescriptions.form')
  </form>
@endsection
