@extends('layouts.admin')

@section('title', 'Novo colaborador - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo colaborador</h1>
      <p>Crie o acesso e escolha os perfis operacionais.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('access-users.store') }}">
        @csrf
        @include('access.users.form')
      </form>
    </div>
  </div>
@endsection
