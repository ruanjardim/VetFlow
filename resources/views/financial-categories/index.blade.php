@extends('layouts.admin')

@section('title', 'Categorias financeiras - VetFlow')

@section('content')
  @php($clinicQuery = $requiresClinic ? ['clinic_id' => $selectedClinicId] : [])

  <header class="topbar">
    <div>
      <h1>Categorias financeiras</h1>
      <p>Organize entradas e saídas sem perder o histórico dos lançamentos.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('financial-transactions.index') }}">Ver lançamentos</a>
      <a class="button" href="{{ route('financial-categories.create', $clinicQuery) }}">Nova categoria</a>
    </div>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('financial-categories.index') }}" class="panel panel-body">
      <div class="field">
        <label for="financial-category-clinic">Estabelecimento</label>
        <select id="financial-category-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="alert-soft">
    <span>
      As categorias padrão classificam automaticamente vendas, compras, comissões, estornos, taxas de cartão e despesas de caixa.
      Você pode renomeá-las ou tirá-las da seleção manual. O tipo de uma categoria não muda, protegendo os relatórios históricos.
    </span>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Categoria</th>
            <th>Tipo</th>
            <th>Origem</th>
            <th>Ordem</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categories as $category)
            <tr>
              <td><strong>{{ $category->name }}</strong></td>
              <td>{{ $category->typeLabel() }}</td>
              <td>{{ $category->isSystem() ? 'Padrão do sistema' : 'Personalizada' }}</td>
              <td>{{ $category->sort_order }}</td>
              <td><span class="badge {{ $category->active ? 'success' : 'muted-badge' }}">{{ $category->active ? 'Ativa' : 'Inativa' }}</span></td>
              <td>
                <a class="button secondary" href="{{ route('financial-categories.edit', array_merge(['financialCategory' => $category->id], $clinicQuery)) }}">Editar</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhuma categoria cadastrada.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
