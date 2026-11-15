@extends('layouts.admin')

@section('title', 'Novo prontuário - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo prontuário</h1>
      <p>Registre os dados clínicos vinculados à consulta.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('medical-records.store') }}">
    @csrf
    @include('medical-records.form')
  </form>
@endsection
