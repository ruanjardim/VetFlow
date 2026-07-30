@extends('layouts.admin')

@section('title', 'Editar acesso - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar acesso</h1>
      <p>{{ $accessUser->name }} · {{ $accessUser->email }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('access-users.update', $accessUser->id) }}">
        @csrf
        @method('PUT')
        @include('access.users.form')
      </form>
    </div>
  </div>
@endsection
