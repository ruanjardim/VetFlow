@extends('layouts.admin')

@section('title', 'Nova contagem - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Nova contagem</h1>
      <p>Defina o escopo que será fotografado para a conferência física.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('inventory-counts.index') }}">Voltar</a>
    </div>
  </header>

  <div class="alert-soft">
    <strong>Saldo protegido durante a conferência</strong>
    <span>O VetFlow registra o saldo esperado no momento da abertura. Se uma venda, entrada ou outro movimento alterar esse saldo antes da finalização, o ajuste será bloqueado para evitar sobrescrever uma operação legítima.</span>
  </div>

  <section class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('inventory-counts.store') }}">
        @csrf
        <div class="form-grid">
          @if(auth()->user()?->clinic_id === null)
            <div class="field">
              <label for="inventory-count-clinic">Clínica</label>
              <select id="inventory-count-clinic" name="clinic_id" required>
                <option value="">Selecione</option>
                @foreach($clinics as $clinic)
                  <option value="{{ $clinic->id }}" @selected((string) old('clinic_id') === (string) $clinic->id)>{{ $clinic->trade_name }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="field">
            <label for="inventory-count-title">Título</label>
            <input id="inventory-count-title" name="title" value="{{ old('title') }}" maxlength="120" required placeholder="Ex.: Conferência semanal da farmácia">
          </div>

          <div class="field">
            <label for="inventory-count-category">Categoria</label>
            <select id="inventory-count-category" name="category">
              <option value="">Todos os produtos ativos</option>
              @foreach($categories as $category)
                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
              @endforeach
            </select>
            <div class="field-hint">A lista de produtos fica fixa após a abertura.</div>
          </div>

          <div class="field full">
            <label for="inventory-count-notes">Observações</label>
            <textarea id="inventory-count-notes" name="notes" rows="4" maxlength="2000" placeholder="Equipe, área física ou instruções da conferência">{{ old('notes') }}</textarea>
          </div>
        </div>

        <div class="actions">
          <button type="submit">Abrir contagem</button>
          <a class="button secondary" href="{{ route('inventory-counts.index') }}">Cancelar</a>
        </div>
      </form>
    </div>
  </section>
@endsection
