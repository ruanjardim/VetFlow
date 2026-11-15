@php
  $details = $employee?->payment_details ?? [];
  $isGlobalActor = auth()->user()?->clinic_id === null;
@endphp

<input type="hidden" name="clinic_id" value="{{ old('clinic_id', $selectedClinicId) }}">

<div class="form-grid">
  <div class="field full">
    <h2 style="margin-bottom: 4px;">Identificação e vínculo</h2>
    <p class="muted">O acesso ao VetFlow é opcional. Colaboradores sem login ainda podem ter cadastro profissional e dados de pagamento.</p>
  </div>

  <div class="field">
    <label for="name">Nome completo</label>
    <input id="name" name="name" value="{{ old('name', $employee?->name) }}" required maxlength="255" autocomplete="name">
  </div>

  <div class="field">
    <label for="user_id">Usuário vinculado</label>
    <select id="user_id" name="user_id">
      <option value="">Sem acesso ao VetFlow</option>
      @foreach($users as $user)
        <option value="{{ $user->id }}" @selected((string) old('user_id', $employee?->user_id) === (string) $user->id)>
          {{ $user->name }} — {{ $user->email }}{{ $user->active ? '' : ' (login inativo)' }}
        </option>
      @endforeach
    </select>
    <small class="muted">Senha e permissões são administradas em Usuários e acessos.</small>
  </div>

  <div class="field">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" value="{{ old('email', $employee?->email) }}" maxlength="255" autocomplete="email">
  </div>

  <div class="field">
    <label for="phone">Telefone</label>
    <input id="phone" name="phone" value="{{ old('phone', $employee?->phone) }}" maxlength="30" autocomplete="tel">
  </div>

  <div class="field">
    <label for="document">CPF ou documento</label>
    <input id="document" name="document" value="{{ old('document', $employee?->document) }}" maxlength="40" autocomplete="off">
    <small class="muted">Armazenado de forma criptografada.</small>
  </div>

  <div class="field">
    <label for="position">Cargo ou função</label>
    <input id="position" name="position" value="{{ old('position', $employee?->position) }}" maxlength="120">
  </div>

  <div class="field">
    <label for="employment_type">Tipo de vínculo</label>
    <select id="employment_type" name="employment_type" required>
      @foreach($employmentTypes as $value => $label)
        <option value="{{ $value }}" @selected(old('employment_type', $employee?->employment_type ?? 'employee') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="field">
    <label for="hire_date">Data de admissão/início</label>
    <input id="hire_date" name="hire_date" type="date" value="{{ old('hire_date', $employee?->hire_date?->toDateString()) }}">
  </div>

  <div class="field">
    <label for="termination_date">Data de desligamento</label>
    <input id="termination_date" name="termination_date" type="date" value="{{ old('termination_date', $employee?->termination_date?->toDateString()) }}">
  </div>

  <div class="field">
    <label for="base_compensation">Remuneração base de referência</label>
    <input id="base_compensation" name="base_compensation" inputmode="decimal" value="{{ old('base_compensation', $employee?->base_compensation !== null ? number_format((float) $employee->base_compensation, 2, ',', '.') : '') }}" placeholder="0,00">
    <small class="muted">Informativa; não gera folha, encargos ou lançamento financeiro automático.</small>
  </div>

  <div class="field full">
    <h2 style="margin: 12px 0 4px;">Forma de pagamento</h2>
    <p class="muted">Os dados abaixo são criptografados. No fechamento de comissão, o histórico guarda somente uma versão mascarada.</p>
  </div>

  <div class="field">
    <label for="payment_method">Forma preferencial</label>
    <select id="payment_method" name="payment_method">
      <option value="">Não informada</option>
      @foreach($paymentMethods as $value => $label)
        <option value="{{ $value }}" @selected(old('payment_method', $employee?->payment_method) === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="field">
    <label for="pix_key">Chave Pix</label>
    <input id="pix_key" name="pix_key" value="{{ old('pix_key', $details['pix_key'] ?? '') }}" maxlength="255" autocomplete="off">
  </div>

  <div class="field">
    <label for="bank_name">Banco</label>
    <input id="bank_name" name="bank_name" value="{{ old('bank_name', $details['bank_name'] ?? '') }}" maxlength="120" autocomplete="off">
  </div>

  <div class="field">
    <label for="bank_branch">Agência</label>
    <input id="bank_branch" name="bank_branch" value="{{ old('bank_branch', $details['bank_branch'] ?? '') }}" maxlength="40" autocomplete="off">
  </div>

  <div class="field">
    <label for="bank_account">Conta</label>
    <input id="bank_account" name="bank_account" value="{{ old('bank_account', $details['bank_account'] ?? '') }}" maxlength="60" autocomplete="off">
  </div>

  <div class="field">
    <label for="account_holder">Titular</label>
    <input id="account_holder" name="account_holder" value="{{ old('account_holder', $details['account_holder'] ?? '') }}" maxlength="255" autocomplete="off">
  </div>

  <div class="field full">
    <label for="notes">Observações internas</label>
    <textarea id="notes" name="notes" rows="4" maxlength="2000">{{ old('notes', $employee?->notes) }}</textarea>
  </div>

  <div class="field full">
    <label class="checkbox-row">
      <input type="checkbox" name="active" value="1" @checked(old('active', $employee?->active ?? true))>
      <span>Cadastro funcional ativo</span>
    </label>
    <small class="muted">Este status é independente do login. Desativar o cadastro não revoga o acesso do usuário vinculado.</small>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar cadastro funcional</button>
      <a class="button secondary" href="{{ route('employees.index', array_filter(['clinic_id' => $isGlobalActor ? $selectedClinicId : null])) }}">Cancelar</a>
    </div>
  </div>
</div>
