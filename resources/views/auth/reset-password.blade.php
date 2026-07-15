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

    <label class="field" for="password">
      <span>Nova senha</span>
      <input id="password" type="password" name="password" autocomplete="new-password" required>
    </label>

    <label class="field" for="password_confirmation">
      <span>Confirmar nova senha</span>
      <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>

    <button class="button" type="submit">Salvar nova senha</button>
  </form>
@endsection
