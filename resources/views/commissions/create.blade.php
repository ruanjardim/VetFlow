@extends('layouts.admin')

@section('title', 'Nova regra de comissao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova regra de comissao</h1>
      <p>Defina a regra comercial que sera usada apenas na previa.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('commissions.store') }}">
    @csrf
    @include('commissions.form', ['rule' => null])
  </form>
@endsection
