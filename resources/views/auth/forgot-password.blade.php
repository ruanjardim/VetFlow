@extends('layouts.guest')

@section('title', 'Recuperar senha - VetFlow')

@section('content')
  <div class="auth-heading">
    <h1>Recuperar senha</h1>
    <p>Informe seu e-mail para receber as instrucoes de redefinicao.</p>
  </div>

  <form method="POST" action="{{ route('password.email') }}" class="auth-form">
    @csrf

    <label class="field" for="email">
      <span>E-mail</span>
      <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
    </label>

    <button class="button" type="submit">Enviar link</button>

    <a class="auth-link" href="{{ route('login') }}">Voltar ao login</a>
  </form>
@endsection
