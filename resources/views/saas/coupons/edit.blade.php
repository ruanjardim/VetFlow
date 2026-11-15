@extends('layouts.admin')
@section('title', 'Editar cupom - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Editar {{ $coupon->code }}</h1><p>{{ $coupon->promotions_count }} utilização(ões). Campos essenciais ficam congelados depois do primeiro uso.</p></div></header>
  <form method="POST" action="{{ route('saas.coupons.update', $coupon) }}">@csrf @method('PUT')<section class="panel"><div class="panel-body">@include('saas.coupons._form')</div></section><div class="form-actions"><button class="button" type="submit">Salvar cupom</button><a class="button secondary" href="{{ route('saas.coupons.index') }}">Voltar</a></div></form>
@endsection
