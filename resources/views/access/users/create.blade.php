@extends('layouts.admin')

@section('title', 'Novo acesso - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>{{ $preferredEmployee ? 'Criar acesso para '.$preferredEmployee->name : 'Novo acesso' }}</h1>
      <p>Defina as credenciais e escolha os perfis operacionais.</p>
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
