@extends('layouts.admin')

@section('title', 'Novo produto - VetFlow')

@section('content')
  @php
    $selectedClinicId = (int) old('clinic_id', request('clinic_id', $clinics->count() === 1 ? $clinics->first()->id : 0));
  @endphp

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
        <div class="form-grid">
          @include('shared.clinic-required-alert', ['clinics' => $clinics])

          @if(auth()->user()?->clinic_id === null)
            <div class="field full">
              <label for="clinic_id">Clinica</label>
              <select id="clinic_id" name="clinic_id" required>
                <option value="">Selecione</option>
                @foreach($clinics as $clinic)
                  <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
                @endforeach
              </select>
            </div>
          @endif
        </div>
        @include('products.form', ['product' => null])
      </form>
    </div>
  </div>
@endsection
