@extends('layouts.guest')

@section('title', 'Redefinir senha - VetFlow')

@section('content')
  <div class="auth-heading">
    <h1>Redefinir senha</h1>
    <p>Escolha uma nova senha de acesso para sua conta.</p>
  </div>

  <form method="POST" action="{{ route('password.update') }}" class="auth-form">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <label class="field" for="email">
      <span>E-mail</span>
      <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" autocomplete="email" required>
    </label>

    <div class="field">
      <label for="password">Nova senha</label>
      <span class="password-input">
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle="password" aria-controls="password" aria-label="Mostrar senha" aria-pressed="false">
          <svg class="password-toggle-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          <svg class="password-toggle-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 6.15A10.9 10.9 0 0 1 12 6c6.25 0 9.75 6 9.75 6a14.3 14.3 0 0 1-2.05 2.65M6.2 6.2C3.65 8 2.25 12 2.25 12S5.75 18 12 18c1.5 0 2.84-.35 4.02-.9M9.88 9.88A3 3 0 0 0 14.12 14.12"></path></svg>
        </button>
      </span>
    </div>

    <div class="field">
      <label for="password_confirmation">Confirmar nova senha</label>
      <span class="password-input">
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
        <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-controls="password_confirmation" aria-label="Mostrar senha" aria-pressed="false">
          <svg class="password-toggle-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          <svg class="password-toggle-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 6.15A10.9 10.9 0 0 1 12 6c6.25 0 9.75 6 9.75 6a14.3 14.3 0 0 1-2.05 2.65M6.2 6.2C3.65 8 2.25 12 2.25 12S5.75 18 12 18c1.5 0 2.84-.35 4.02-.9M9.88 9.88A3 3 0 0 0 14.12 14.12"></path></svg>
        </button>
      </span>
    </div>

    <button class="button" type="submit">Salvar nova senha</button>
  </form>
@endsection
