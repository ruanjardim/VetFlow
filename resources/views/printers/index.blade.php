@extends('layouts.admin')

@section('title', 'Impressoras - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Configuração de impressoras</h1>
      <p>Organize as impressoras fiscais, não fiscais, de etiquetas e documentos deste estabelecimento.</p>
    </div>
    <a class="button" href="{{ route('printers.create', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Nova impressora</a>
  </header>

  @if($requiresClinic)
    <form method="GET" action="{{ route('printers.index') }}" class="panel panel-body">
      <div class="field">
        <label for="printer-clinic">Estabelecimento</label>
        <select id="printer-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="alert-soft printer-guidance">
    <span>
      <strong>Como funciona:</strong> o VetFlow guarda a finalidade, o papel e os dados de conexão. Ao imprimir pelo navegador, a seleção física ocorre na janela de impressão do computador. Equipamentos fiscais dependem do driver ou provedor homologado usado pelo estabelecimento.
    </span>
  </div>

  <section class="grid stats printer-stats">
    <div class="stat">
      <span>Cadastradas</span>
      <strong>{{ $printers->total() }}</strong>
    </div>
    <div class="stat">
      <span>Ativas nesta página</span>
      <strong>{{ $printers->getCollection()->where('active', true)->count() }}</strong>
    </div>
    <div class="stat">
      <span>Impressora padrão</span>
      <strong class="printer-default-name">{{ $printers->getCollection()->firstWhere('is_default', true)?->name ?? 'Não definida' }}</strong>
    </div>
  </section>

  <div class="panel">
    <div class="panel-heading">
      <div>
        <h2>Impressoras do estabelecimento</h2>
        <p>A configuração é compartilhada com os usuários autorizados deste estabelecimento.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Impressora</th>
            <th>Tipo</th>
            <th>Finalidade</th>
            <th>Conexão</th>
            <th>Papel</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($printers as $printer)
            <tr>
              <td>
                <strong>{{ $printer->name }}</strong>
                @if($printer->is_default)
                  <span class="badge success">Padrão</span>
                @endif
                <div class="muted">{{ trim(($printer->manufacturer ?: '').' '.($printer->model ?: '')) ?: ($printer->queue_name ?: 'Sem modelo informado') }}</div>
              </td>
              <td>{{ $types[$printer->type] ?? $printer->type }}</td>
              <td>{{ $purposes[$printer->purpose] ?? $printer->purpose }}</td>
              <td>
                {{ $connections[$printer->connection_type] ?? $printer->connection_type }}
                @if($printer->connection_type === 'network' && $printer->network_host)
                  <div class="muted">{{ $printer->network_host }}{{ $printer->network_port ? ':'.$printer->network_port : '' }}</div>
                @endif
              </td>
              <td>{{ $paperSizes[$printer->paper_size] ?? $printer->paper_size }}</td>
              <td><span class="badge {{ $printer->active ? 'success' : 'muted-badge' }}">{{ $printer->active ? 'Ativa' : 'Inativa' }}</span></td>
              <td>
                <div class="actions printer-row-actions">
                  <a class="button secondary" href="{{ route('printers.test', array_filter(['printer' => $printer->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Testar</a>
                  <a class="button secondary" href="{{ route('printers.edit', array_filter(['printer' => $printer->id, 'clinic_id' => $requiresClinic ? $selectedClinicId : null])) }}">Editar</a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="muted">Nenhuma impressora cadastrada. Use “Nova impressora” para começar.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $printers->withQueryString()->links() }}
@endsection
