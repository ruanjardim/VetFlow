@extends('layouts.admin')

@section('title', 'Fornecedores - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Fornecedores</h1>
      <p>Empresas e distribuidores de produtos, medicamentos e insumos.</p>
    </div>
    <a class="button" href="{{ route('suppliers.create') }}">Novo fornecedor</a>
  </header>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fornecedor</th>
            <th>Documento</th>
            <th>Contato</th>
            <th>Cidade</th>
            <th>Status</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          @forelse($suppliers as $supplier)
            <tr>
              <td>
                <strong>{{ $supplier->name }}</strong>
                <div class="muted">{{ $supplier->email ?: '-' }}</div>
              </td>
              <td>{{ $supplier->document ?: '-' }}</td>
              <td>
                {{ $supplier->contact_name ?: '-' }}
                <div class="muted">{{ $supplier->phone ?: $supplier->whatsapp }}</div>
              </td>
              <td>{{ trim(($supplier->city ?: '').' / '.($supplier->state ?: ''), ' /') ?: '-' }}</td>
              <td>{{ $supplier->active ? 'Ativo' : 'Inativo' }}</td>
              <td>
                <a class="button secondary" href="{{ route('suppliers.edit', $supplier->id) }}">Editar</a>
                <form class="inline" action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="danger" type="submit" data-confirm="Remover este fornecedor?">Excluir</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="muted">Nenhum fornecedor cadastrado.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $suppliers->links() }}
@endsection
