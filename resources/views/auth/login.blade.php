@extends('layouts.guest')

@section('title', 'Login - VetFlow')
@section('auth-shell-class', 'auth-shell--login')
@section('auth-card-class', 'auth-card--login')

@push('head')
  <link rel="preload" as="image" href="{{ asset('images/auth-malinois-square.webp') }}" type="image/webp">
@endpush

@section('auth-visual')
  <img
    src="{{ asset('images/auth-malinois-square.webp') }}"
    alt="Pastor-belga Malinois de corpo inteiro em uma clínica veterinária"
    width="1254"
    height="1254"
    fetchpriority="high"
  >
@endsection

@section('content')
  <div class="auth-login-intro">
    <span class="auth-eyebrow">Bem-vindo de volta</span>
    <h1>Acesse o VetFlow</h1>
    <p>Gestão veterinária inteligente, segura e conectada.</p>
    <span class="auth-login-cue" aria-hidden="true"></span>
  </div>

  <div class="auth-form-card">
    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
      @csrf

      <label class="field" for="email">
        <span>E-mail</span>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus required placeholder="seu@email.com">
      </label>

      <label class="field" for="password">
        <span>Senha</span>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Digite sua senha">
      </label>

      <div class="auth-options">
        <label class="checkbox-field">
          <input type="checkbox" name="remember" value="1">
          <span>Manter conectado</span>
        </label>

        <a class="auth-link" href="{{ route('password.request') }}">Esqueci minha senha</a>
      </div>

      <button class="button" type="submit">
        <span>Entrar no VetFlow</span>
        <span aria-hidden="true">→</span>
      </button>
    </form>

    <p class="auth-security-note">
      <span aria-hidden="true">●</span>
      Ambiente protegido e acesso exclusivo da clínica
    </p>
  </div>
@endsection
