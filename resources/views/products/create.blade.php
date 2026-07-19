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
        @if(in_array(request('return_to', request('from')), ['sales', 'inventory', 'purchase'], true))
          <input type="hidden" name="return_to" value="{{ request('return_to', request('from')) }}">
        @endif
        @if(auth()->user()?->clinic_id === null && request('clinic_id'))
          <input type="hidden" name="clinic_id" value="{{ request('clinic_id') }}">
        @endif
        @include('products.form', ['product' => null])
      </form>
    </div>
  </div>
@endsection
