@extends('layouts.admin')

@section('title', 'Editar regra de comissao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar regra de comissao</h1>
      <p>Ajuste a vigencia ou os criterios para as proximas previas.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('commissions.update', $rule->id) }}">
    @csrf
    @method('PUT')
    @include('commissions.form')
  </form>
@endsection
