@extends('layouts.admin')

@section('title', 'Novo produto - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Novo produto</h1>
      <p>Cadastro de item para PetShop, farmacia ou loja.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        @if(in_array(request('from'), ['sales', 'inventory'], true))
          <input type="hidden" name="return_to" value="{{ request('from') }}">
        @endif
        @include('products.form', ['product' => null])
      </form>
    </div>
  </div>
@endsection
