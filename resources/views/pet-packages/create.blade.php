@extends('layouts.admin')

@section('title', 'Vender pacote - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Vender pacote</h1>
      <p>O saldo é liberado quando a venda for concluída no PDV.</p>
    </div>
  </header>

  @if($templates->isEmpty())
    <div class="panel"><p>Nenhum modelo de pacote ativo. @can('petshop-services.manage')<a href="{{ route('petshop-packages.create') }}">Cadastre um pacote</a> primeiro.@endcan</p></div>
  @else
    <div class="panel">
      <div class="panel-body">
        <form method="POST" action="{{ route('pet-packages.store') }}">
          @csrf
          <div class="form-grid">
            <div class="field">
              <label for="petshop_package_id">Pacote</label>
              <select id="petshop_package_id" name="petshop_package_id" required>
                <option value="">Selecione</option>
                @foreach($templates as $template)
                  <option value="{{ $template->id }}" @selected((int) old('petshop_package_id', $selectedTemplateId) === $template->id)>
                    {{ $template->name }} — R$ {{ number_format((float) $template->price, 2, ',', '.') }}
                    ({{ $template->items->map(fn ($item) => $item->quantity.'× '.($item->service?->name ?? 'Serviço'))->implode(', ') }}{{ $template->validity_days ? ' · '.$template->validity_days.' dias' : '' }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label for="patient_id">Pet</label>
              <select id="patient_id" name="patient_id" required>
                <option value="">Selecione</option>
                @foreach($patients as $patient)
                  <option value="{{ $patient->id }}" @selected((int) old('patient_id', $selectedPatientId) === $patient->id)>{{ $patient->name }}{{ $patient->tutor ? ' — '.$patient->tutor->name : '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label for="starts_on">Início da validade</label>
              <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', today()->toDateString()) }}" required>
            </div>
            <div class="field">
              <label for="price">Preço cobrado (R$)</label>
              <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" placeholder="Preço do pacote">
            </div>
            <div class="field full">
              <label for="notes">Observações</label>
              <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            @can('sales.manage')
              <div class="field full">
                <label class="checkbox-inline">
                  <input type="hidden" name="activate_now" value="0">
                  <input type="checkbox" name="activate_now" value="1" @checked(old('activate_now'))>
                  Já foi pago fora do PDV — liberar o saldo agora
                </label>
              </div>
            @endcan
            <div class="field full">
              <div class="actions">
                <button type="submit">Registrar e receber</button>
                <a class="button secondary" href="{{ route('pet-packages.index') }}">Cancelar</a>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection
