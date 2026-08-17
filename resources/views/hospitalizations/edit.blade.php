@extends('layouts.admin')

@section('title', 'Internação - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Internação de {{ $hospitalization->patient?->name }}</h1>
      <p>Atualize o acompanhamento operacional sem alterar o prontuário original.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('hospitalizations.update', $hospitalization->id) }}">
    @csrf
    @method('PUT')
    @include('hospitalizations.form')
  </form>
@endsection
