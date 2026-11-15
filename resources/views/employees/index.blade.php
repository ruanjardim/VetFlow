@extends('layouts.admin')

@section('title', 'Cadastros funcionais - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Cadastros funcionais</h1>
      <p>Dados profissionais e de pagamento dos colaboradores, com ou sem acesso ao VetFlow.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="{{ route('access-users.index') }}">Usuários e acessos</a>
      <a class="button" href="{{ route('employees.create', array_filter(['clinic_id' => $selectedClinicId])) }}">Novo colaborador</a>
    </div>
  </header>

  @if($clinics->isNotEmpty())
    <form method="GET" action="{{ route('employees.index') }}" class="panel panel-body" style="margin-bottom: 18px;">
      <div class="field">
        <label for="employee-clinic">Estabelecimento</label>
        <select id="employee-clinic" name="clinic_id" data-auto-submit-select>
          <option value="">Todos os estabelecimentos</option>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <div class="alert-soft" style="margin-bottom: 18px;">
    <span><strong>Escopo atual:</strong> este cadastro organiza vínculo, admissão, remuneração de referência e forma de pagamento. Ele não calcula folha, encargos, impostos nem holerite. As comissões continuam sendo fechadas no módulo de Comissões e pagas pelo Financeiro.</span>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Colaborador</th>
            <th>Vínculo</th>
            <th>Cargo</th>
            <th>Admissão</th>
            <th>Remuneração base</th>
            <th>Pagamento</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse($employees as $employee)
            @php($payment = $employee->paymentSnapshot())
            <tr>
              <td>
                <strong>{{ $employee->name }}</strong>
                <div class="muted">{{ $employee->email ?: 'E-mail não informado' }}</div>
                @if(auth()->user()?->clinic_id === null)
                  <div class="muted">{{ $employee->clinic?->trade_name ?? $employee->clinic?->corporate_name ?? '-' }}</div>
                @endif
              </td>
              <td>
                {{ $employmentTypes[$employee->employment_type] ?? $employee->employment_type }}
                <div class="muted">{{ $employee->user ? ($employee->user->active ? 'Login ativo' : 'Login inativo') : 'Sem login no sistema' }}</div>
              </td>
              <td>{{ $employee->position ?: 'Não informado' }}</td>
              <td>{{ $employee->hire_date?->format('d/m/Y') ?? 'Não informada' }}</td>
              <td>{{ $employee->base_compensation !== null ? 'R$ '.number_format((float) $employee->base_compensation, 2, ',', '.') : 'Não informada' }}</td>
              <td>
                {{ $payment['method_label'] ?? 'Não informado' }}
                @if($payment)
                  <div class="muted">{{ $payment['pix_key_masked'] ?? $payment['bank_account_masked'] ?? '' }}</div>
                @endif
              </td>
              <td><span class="badge {{ $employee->active ? 'success' : 'muted-badge' }}">{{ $employee->active ? 'Ativo' : 'Inativo' }}</span></td>
              <td>
                <div class="actions">
                  @unless($employee->user)
                    <a class="button secondary" href="{{ route('access-users.create', ['clinic_id' => $employee->clinic_id, 'employee_id' => $employee->id]) }}">Criar acesso</a>
                  @endunless
                  <a class="button secondary" href="{{ route('employees.edit', $employee->id) }}">Editar cadastro</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted">Nenhum cadastro funcional encontrado.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $employees->links() }}
@endsection
