@extends('layouts.admin')

@section('title', 'Pendências de Qualidade - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>{{ $quality['label'] }}</h1>
      <p>Pendências de qualidade da clínica {{ $clinic->trade_name }}.</p>
    </div>

    <a class="button secondary" href="{{ route('implementation.index') }}">
      Voltar à implantação
    </a>
  </header>

  <section class="panel">
    <div class="panel-body">
      <div class="implementation-heading">
        <div>
          <span class="eyebrow">Fila de revisão</span>
          <h2>{{ $quality['issues']->total() }} registros para conferir</h2>
          <p class="muted">Critério: {{ $quality['description'] }}.</p>
        </div>
      </div>

      @if($quality['issues']->isEmpty())
        <div class="empty-state">
          <h3>Nenhuma pendência atual</h3>
          <p>Os dados podem ter sido corrigidos desde a abertura desta fila.</p>
        </div>
      @else
        <div class="table-wrap implementation-table">
          <table>
            <thead>
              <tr>
                <th>Registro</th>
                <th>Correções necessárias</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              @foreach($quality['issues'] as $issue)
                <tr>
                  <td>
                    <strong>{{ $issue['label'] }}</strong><br>
                    <small class="muted">ID {{ $issue['id'] }}</small>
                  </td>
                  <td>{{ implode(' · ', $issue['reasons']) }}</td>
                  <td>
                    @can($quality['permission'])
                      <a
                        class="button secondary"
                        href="{{ route($quality['edit_route'], $issue['id']) }}"
                      >
                        Corrigir cadastro
                      </a>
                    @else
                      <span class="muted">Solicite a correção ao responsável do módulo.</span>
                    @endcan
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{ $quality['issues']->links() }}
      @endif
    </div>
  </section>
@endsection
