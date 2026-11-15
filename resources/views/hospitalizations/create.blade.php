@extends('layouts.admin')

@section('title', 'Nova internação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova internação</h1>
      <p>Registre a admissão; as condutas clínicas continuam no prontuário.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('hospitalizations.store') }}">
    @csrf
    @include('hospitalizations.form')
  </form>
@endsection
