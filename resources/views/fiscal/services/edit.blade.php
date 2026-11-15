@extends('layouts.admin')

@section('title', 'Classificação fiscal de serviço - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Classificação fiscal do serviço</h1><p>{{ $service->name }}</p></div>
    <a class="button secondary" href="{{ route('fiscal.services.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar</a>
  </header>

  @if($missingLabels)
    <div class="alert warning"><strong>Pendente:</strong> {{ implode(', ', $missingLabels) }}.</div>
  @else
    <div class="alert success"><strong>Cadastro mínimo completo.</strong> Confirme os códigos e a incidência com a contabilidade antes da produção.</div>
  @endif

  <section class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('fiscal.services.update', $service->id) }}">
        @csrf
        @method('PUT')
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        <div class="form-grid">
          <div class="field"><label for="national_taxation_code">Código de tributação nacional</label><input id="national_taxation_code" name="national_taxation_code" value="{{ old('national_taxation_code', $profile->national_taxation_code) }}" maxlength="20"></div>
          <div class="field"><label for="municipal_taxation_code">Código de tributação municipal</label><input id="municipal_taxation_code" name="municipal_taxation_code" value="{{ old('municipal_taxation_code', $profile->municipal_taxation_code) }}" maxlength="30"><small class="muted">Opcional quando o município não exigir código próprio.</small></div>
          <div class="field"><label for="service_list_item">Item da lista de serviços</label><input id="service_list_item" name="service_list_item" value="{{ old('service_list_item', $profile->service_list_item) }}" maxlength="10" placeholder="Ex.: 05.01"></div>
          <div class="field"><label for="cnae">CNAE</label><input id="cnae" name="cnae" value="{{ old('cnae', $profile->cnae) }}" inputmode="numeric" maxlength="9" placeholder="7 dígitos"></div>
          <div class="field"><label for="nbs_code">NBS</label><input id="nbs_code" name="nbs_code" value="{{ old('nbs_code', $profile->nbs_code) }}" maxlength="15"></div>
          <div class="field"><label for="municipality_code">Município de incidência — IBGE</label><input id="municipality_code" name="municipality_code" value="{{ old('municipality_code', $profile->municipality_code) }}" inputmode="numeric" maxlength="9" placeholder="7 dígitos"></div>
          <div class="field"><label for="iss_rate">Alíquota de ISS (%)</label><input id="iss_rate" name="iss_rate" value="{{ old('iss_rate', $profile->iss_rate) }}" inputmode="decimal" placeholder="Ex.: 2,00"></div>
          <div class="field"><label for="iss_withheld">Retenção de ISS</label><select id="iss_withheld" name="iss_withheld"><option value="0" @selected(! old('iss_withheld', $profile->iss_withheld))>Não retido</option><option value="1" @selected(old('iss_withheld', $profile->iss_withheld))>Retido</option></select></div>
          <div class="field"><label for="iss_taxation">Tributação do ISS</label><select id="iss_taxation" name="iss_taxation"><option value="">Selecione com a contabilidade</option><option value="1" @selected(old('iss_taxation', $profile->iss_taxation) === '1')>1 — Operação tributável</option><option value="2" @selected(old('iss_taxation', $profile->iss_taxation) === '2')>2 — Imunidade</option><option value="3" @selected(old('iss_taxation', $profile->iss_taxation) === '3')>3 — Exportação de serviço</option><option value="4" @selected(old('iss_taxation', $profile->iss_taxation) === '4')>4 — Não incidência</option></select></div>
          <div class="field"><label for="ibs_cbs_tax_status">CST do IBS/CBS</label><input id="ibs_cbs_tax_status" name="ibs_cbs_tax_status" value="{{ old('ibs_cbs_tax_status', $profile->ibs_cbs_tax_status) }}" inputmode="numeric" maxlength="3" placeholder="3 dígitos"></div>
          <div class="field"><label for="ibs_cbs_classification_code">Classificação IBS/CBS</label><input id="ibs_cbs_classification_code" name="ibs_cbs_classification_code" value="{{ old('ibs_cbs_classification_code', $profile->ibs_cbs_classification_code) }}" inputmode="numeric" maxlength="6" placeholder="6 dígitos"></div>
          <div class="field"><label for="operation_indicator">Indicador da operação IBS/CBS</label><input id="operation_indicator" name="operation_indicator" value="{{ old('operation_indicator', $profile->operation_indicator) }}" inputmode="numeric" maxlength="6" placeholder="6 dígitos"><small class="muted">Opcional; use somente o código confirmado na tabela vigente.</small></div>
          <div class="field full"><label for="notes">Observações contábeis</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes', $profile->notes) }}</textarea></div>
          <div class="field full"><div class="actions"><button type="submit">Salvar classificação</button><span class="muted">Versão {{ $profile->configuration_version ?: 1 }}</span></div></div>
        </div>
      </form>
    </div>
  </section>
@endsection
