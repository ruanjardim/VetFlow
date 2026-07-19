@extends('layouts.admin')

@section('title', 'Implantacao - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Implantacao</h1>
      <p>Roteiro para migrar dados da clinica para o VetFlow com conferencia antes de gravar em producao.</p>
    </div>
    <a class="button secondary" href="{{ route('clinics.index') }}">Clinicas</a>
  </header>

  @if($clinicsCount === 0)
    <div class="alert warning action-alert">
      <div>
        <strong>Comece pela clinica destino.</strong>
        <span>A migracao precisa de uma clinica ativa para receber tutores, pacientes, produtos e financeiro.</span>
      </div>
      <a class="button secondary" href="{{ route('clinics.create') }}">Cadastrar clinica</a>
    </div>
  @endif

  <div class="panel">
    <div class="panel-body">
      <h2>Base da migracao</h2>
      <p class="muted">Este modulo marca o ponto oficial de implantacao. A proxima evolucao sera anexar arquivos, validar colunas e executar importacoes em lote.</p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Bloco</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($migrationBlocks as $block)
              <tr>
                <td>{{ $block }}</td>
                <td>Preparado para mapeamento</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-body">
      <h2>Roteiro seguro</h2>
      <ol>
        @foreach($implantationSteps as $step)
          <li>{{ $step }}</li>
        @endforeach
      </ol>
    </div>
  </div>
@endsection
