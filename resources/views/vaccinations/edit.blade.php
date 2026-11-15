@extends('layouts.admin')

@section('title', 'Editar vacina - VetFlow')

@section('content')
  <header class="topbar"><div><h1>Editar vacina</h1><p>Atualize o registro da carteira de vacinação.</p></div></header>
  <form class="panel" method="POST" action="{{ route('vaccinations.update', $vaccination->id) }}">@csrf @method('PUT') @include('vaccinations.form')</form>
@endsection
