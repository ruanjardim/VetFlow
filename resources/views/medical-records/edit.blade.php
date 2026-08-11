@extends('layouts.admin')

@section('title', 'Editar prontuário - VetFlow')

@section('content')
  <header class="topbar">
    <div>
      <h1>Editar prontuário</h1>
      <p>Atualize as informações clínicas sem alterar o vínculo da consulta.</p>
    </div>
  </header>

  <form class="panel" method="POST" action="{{ route('medical-records.update', $medicalRecord->id) }}">
    @csrf
    @method('PUT')
    @include('medical-records.form')
  </form>
@endsection
