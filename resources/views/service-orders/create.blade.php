@extends('layouts.admin')

@section('title', 'Nova comanda - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>{{ request()->filled('scheduled_at') || request('status') === 'scheduled' ? 'Novo agendamento de banho e tosa' : 'Nova comanda' }}</h1>
      <p>Agende ou abra um atendimento com serviços, produtos, profissional e horário.</p>
    </div>
  </header>

  <div class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('service-orders.store') }}">
        @csrf
        @include('service-orders.form', ['order' => null])
      </form>
    </div>
  </div>
@endsection
