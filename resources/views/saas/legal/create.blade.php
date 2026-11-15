@extends('layouts.admin')
@section('title', 'Nova versão jurídica - VetFlow')
@section('content')
  <header class="topbar"><div><h1>Nova versão jurídica</h1><p>Crie uma minuta separada. O documento anterior permanece preservado.</p></div></header>
  <form method="POST" action="{{ route('saas.legal.store') }}">@csrf<section class="panel"><div class="panel-body"><div class="form-grid">
    <label class="field"><span>Tipo</span><select name="type" required>@foreach($types as $key => $label)<option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>@endforeach</select></label>
    <label class="field"><span>Título</span><input name="title" maxlength="160" value="{{ old('title') }}" required></label>
    <label class="field"><span>Versão</span><input name="version" maxlength="64" value="{{ old('version', now()->format('Y.m').'-draft-1') }}" required></label>
    <label class="field"><span>Vigência pretendida</span><input type="datetime-local" name="effective_at" value="{{ old('effective_at') }}"></label>
  </div><label class="field" style="margin-top: 18px;"><span>Conteúdo integral</span><textarea name="body" rows="30" required>{{ old('body', "MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA\n\n") }}</textarea><small class="field-hint">O hash do conteúdo será calculado e congelado ao salvar.</small></label></div></section><div class="form-actions"><button class="button" type="submit">Salvar como minuta</button><a class="button secondary" href="{{ route('saas.legal.index') }}">Cancelar</a></div></form>
@endsection
