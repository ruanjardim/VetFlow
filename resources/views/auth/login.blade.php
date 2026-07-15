@extends('layouts.guest')

@section('title', 'Login - VetFlow')

@section('content')
  <div class="auth-heading">
    <h1>Acesse o VetFlow</h1>
    <p>Use seu usuario autorizado para acessar a operacao da clinica.</p>
  </div>

  <form method="POST" action="{{ route('login.store') }}" class="auth-form">
    @csrf

    <label class="field" for="email">
      <span>E-mail</span>
      <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
    </label>

    <label class="field" for="password">
      <span>Senha</span>
      <input id="password" type="password" name="password" autocomplete="current-password" required>
    </label>

    <label class="checkbox-field">
      <input type="checkbox" name="remember" value="1">
      <span>Manter conectado neste dispositivo</span>
    </label>

    <button class="button" type="submit">Entrar</button>

    <a class="auth-link" href="{{ route('password.request') }}">Esqueci minha senha</a>
  </form>
@endsection
