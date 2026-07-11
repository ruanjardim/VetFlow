@extends('layouts.admin')

@section('title', 'Nova entrada - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova entrada</h1>
      <p>Recebimento de produtos com lote, validade e custo.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('purchase-entries.store') }}" data-purchase-form>
        @csrf
        @include('purchase-entries.form')
      </form>
    </div>
  </div>
@endsection
