@extends('layouts.admin')
@section('title', 'Novo cupom - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Novo cupom</h1><p>Defina percentual, validade, duração, planos e limite de uso.</p></div></header>
  <form method="POST" action="{{ route('saas.coupons.store') }}">@csrf<section class="panel"><div class="panel-body">@include('saas.coupons._form')</div></section><div class="form-actions"><button class="button" type="submit">Criar cupom</button><a class="button secondary" href="{{ route('saas.coupons.index') }}">Cancelar</a></div></form>
@endsection
