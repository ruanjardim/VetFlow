@php
  $selectedRoleIds = collect(old('role_ids', $accessUser?->roles?->pluck('id')->all() ?? []))
    ->map(fn ($roleId) => (int) $roleId)
    ->all();
  $isGlobalActor = auth()->user()->clinic_id === null;
@endphp

<div class="form-grid">
  <div class="field">
    <label for="name">Nome</label>
    <input id="name" name="name" value="{{ old('name', $accessUser?->name) }}" required>
  </div>

  <div class="field">
    <label for="email">E-mail de acesso</label>
    <input id="email" name="email" type="email" value="{{ old('email', $accessUser?->email) }}" autocomplete="email" required>
  </div>

  <div class="field">
    <label for="phone">Telefone</label>
    <input id="phone" name="phone" value="{{ old('phone', $accessUser?->phone) }}">
  </div>

  <div class="field">
    <label for="position">Cargo ou funcao</label>
    <input id="position" name="position" value="{{ old('position', $accessUser?->position) }}" placeholder="Ex.: Veterinaria responsavel">
  </div>

  @if($isGlobalActor)
    <div class="field">
      <label for="clinic_id">Clinica</label>
      <select id="clinic_id" name="clinic_id">
        <option value="">Acesso global</option>
        @foreach($clinics as $clinic)
          <option value="{{ $clinic->id }}" @selected((string) old('clinic_id', $accessUser?->clinic_id) === (string) $clinic->id)>
            {{ $clinic->trade_name ?? $clinic->corporate_name }}
          </option>
        @endforeach
      </select>
      <div class="field-hint">Use acesso global somente para administracao entre clinicas.</div>
    </div>
  @else
    <div class="field">
      <label>Clinica</label>
      <div class="readonly-total">
        {{ auth()->user()->clinic?->trade_name ?? auth()->user()->clinic?->corporate_name ?? 'Clinica atual' }}
      </div>
      <div class="field-hint">O colaborador sera vinculado automaticamente a sua clinica.</div>
    </div>
  @endif

  <div class="field">
    <label for="active">Status</label>
    <select id="active" name="active">
      <option value="1" @selected((string) old('active', $accessUser ? (int) $accessUser->active : 1) === '1')>Ativo</option>
      <option value="0" @selected((string) old('active', $accessUser ? (int) $accessUser->active : 1) === '0')>Inativo</option>
    </select>
  </div>

  <div class="field">
    <label for="password">{{ $accessUser ? 'Nova senha' : 'Senha inicial' }}</label>
    <input
      id="password"
      name="password"
      type="password"
      autocomplete="new-password"
      @required(! $accessUser)
    >
    @if($accessUser)
      <div class="field-hint">Deixe em branco para manter a senha atual.</div>
    @endif
  </div>

  <div class="field">
    <label for="password_confirmation">Confirmar senha</label>
    <input
      id="password_confirmation"
      name="password_confirmation"
      type="password"
      autocomplete="new-password"
      @required(! $accessUser)
    >
  </div>

  <div class="field full">
    <label>Perfis de acesso</label>
    <div class="field-hint">E possivel combinar perfis. As permissoes resultantes sao somadas.</div>

    <div class="access-role-grid">
      @foreach($roles as $role)
        <label class="access-role-option">
          <input
            type="checkbox"
            name="role_ids[]"
            value="{{ $role->id }}"
            @checked(in_array($role->id, $selectedRoleIds, true))
          >
          <span class="access-role-summary">
            <strong>{{ $role->name }}</strong>
            <small>{{ $role->description }}</small>
            <span class="access-permission-list">
              {{ $role->permissions->pluck('name')->join(' · ') }}
            </span>
          </span>
        </label>
      @endforeach
    </div>
  </div>

  <div class="field full">
    <div class="actions">
      <button type="submit">Salvar acesso</button>
      <a class="button secondary" href="{{ route('access-users.index') }}">Cancelar</a>
    </div>
  </div>
</div>
