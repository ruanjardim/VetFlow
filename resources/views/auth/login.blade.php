@extends('layouts.guest')

@section('title', 'Login - VetFlow')
@section('auth-shell-class', 'auth-shell--login')
@section('auth-card-class', 'auth-card--login')

@push('head')
  <link rel="preload" as="image" href="{{ asset('images/auth-malinois-square.webp') }}" type="image/webp">
@endpush

@section('auth-visual')
  <div class="auth-visual-slideshow" aria-hidden="true">
    <img
      class="auth-visual-slide"
      src="{{ asset('images/auth-malinois-square.webp') }}"
      alt=""
      width="1254"
      height="1254"
      fetchpriority="high"
    >
    <img
      class="auth-visual-slide"
      src="{{ asset('images/auth-pintabian-horse-square.png') }}"
      alt=""
      width="1254"
      height="1254"
    >
    <img
      class="auth-visual-slide"
      src="{{ asset('images/auth-beagle-square.png') }}"
      alt=""
      width="1254"
      height="1254"
    >
    <img
      class="auth-visual-slide"
      src="{{ asset('images/auth-gray-cat-square.png') }}"
      alt=""
      width="1254"
      height="1254"
    >
    <img
      class="auth-visual-slide"
      src="{{ asset('images/auth-white-kitten-square.png') }}"
      alt=""
      width="1254"
      height="1254"
    >
  </div>
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

      <div class="field">
        <label for="password">Senha</label>
        <span class="password-input">
          <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Digite sua senha">
          <button
            class="password-toggle"
            type="button"
            data-password-toggle="password"
            aria-controls="password"
            aria-label="Mostrar senha"
            aria-pressed="false"
          >
            <svg class="password-toggle-show" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6S2.25 12 2.25 12Z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="password-toggle-hide" viewBox="0 0 24 24" aria-hidden="true">
              <path d="m3 3 18 18"></path>
              <path d="M10.6 6.15A10.9 10.9 0 0 1 12 6c6.25 0 9.75 6 9.75 6a14.3 14.3 0 0 1-2.05 2.65M6.2 6.2C3.65 8 2.25 12 2.25 12S5.75 18 12 18c1.5 0 2.84-.35 4.02-.9M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
            </svg>
          </button>
        </span>
      </div>

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
