@extends('layouts.admin')

@section('title', 'Nova vacina - VetFlow')

@section('content')
  <header class="topbar"><div><h1>Nova vacina</h1><p>Agende ou registre uma aplicação.</p></div></header>
  <form class="panel" method="POST" action="{{ route('vaccinations.store') }}">@csrf @include('vaccinations.form')</form>
@endsection
