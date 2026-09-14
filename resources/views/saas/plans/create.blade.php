@extends('layouts.admin')
@section('title', 'Novo plano - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Novo plano</h1><p>Defina a composição inicial e os limites comerciais.</p></div></header>
  <form method="POST" action="{{ route('saas.plans.store') }}">@csrf @include('saas.plans._form')</form>
@endsection
