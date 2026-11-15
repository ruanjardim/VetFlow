@extends('layouts.admin')
@section('title', 'Editar plano - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Editar {{ $plan->name }}</h1><p>As mudanças passam a valer para assinaturas vinculadas, salvo exceções individuais.</p></div></header>
  <form method="POST" action="{{ route('saas.plans.update', $plan) }}">@csrf @method('PUT') @include('saas.plans._form')</form>
@endsection
