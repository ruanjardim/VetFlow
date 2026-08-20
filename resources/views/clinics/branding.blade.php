@extends('layouts.admin')

@section('title', 'Identidade visual - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Identidade visual</h1>
      <p>Escolha o símbolo exibido ao lado do VetFlow para {{ $clinic->trade_name ?: $clinic->corporate_name }}.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Marca no menu</h2>
        <p>A configuração é compartilhada por todos os usuários desta clínica.</p>
      </div>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('clinic-branding.update') }}" class="form-grid">
        @csrf
        @method('PUT')
        @include('clinics.branding-fields')
        <div class="field full">
          <div class="actions">
            <button type="submit">Salvar identidade visual</button>
            <a class="button secondary" href="{{ route('dashboard') }}">Cancelar</a>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
