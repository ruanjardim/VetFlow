@extends('layouts.admin')

@section('title', 'Editar entrada - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar entrada</h1>
      <p>{{ $entry->code }}</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('purchase-entries.update', $entry->id) }}" data-purchase-form>
        @csrf
        @method('PUT')
        @include('purchase-entries.form')
      </form>
    </div>
  </div>
@endsection
