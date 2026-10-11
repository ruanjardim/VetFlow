@extends('layouts.admin')

@section('title', 'Classificação fiscal de produto - VetFlow')

@section('content')
  <header class="topbar">
    <div><h1>Classificação fiscal do produto</h1><p>{{ $product->name }}</p></div>
    <a class="button secondary" href="{{ route('fiscal.products.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Voltar</a>
  </header>

  @if($missingLabels)
    <div class="alert warning"><strong>Pendente:</strong> {{ implode(', ', $missingLabels) }}.</div>
  @else
    <div class="alert success"><strong>Cadastro mínimo completo.</strong> Os códigos ainda devem ser confirmados pela contabilidade antes da produção.</div>
  @endif

  <section class="panel">
    <div class="panel-body">
      <form method="POST" action="{{ route('fiscal.products.update', $product->id) }}">
        @csrf
        @method('PUT')
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        <div class="form-grid">
          <div class="field"><label for="ncm">NCM</label><input id="ncm" name="ncm" value="{{ old('ncm', $profile->ncm) }}" inputmode="numeric" maxlength="10" placeholder="8 dígitos"></div>
          <div class="field"><label for="cest">CEST, quando aplicável</label><input id="cest" name="cest" value="{{ old('cest', $profile->cest) }}" inputmode="numeric" maxlength="9" placeholder="7 dígitos"></div>
          <div class="field">
            <label for="origin_code">Origem da mercadoria</label>
            <select id="origin_code" name="origin_code">
              <option value="">Selecione</option>
              @foreach(['0' => '0 — Nacional', '1' => '1 — Estrangeira, importação direta', '2' => '2 — Estrangeira, mercado interno', '3' => '3 — Nacional com conteúdo importado superior a 40%', '4' => '4 — Nacional, processo básico', '5' => '5 — Nacional com conteúdo importado até 40%', '6' => '6 — Estrangeira sem similar nacional, importação direta', '7' => '7 — Estrangeira sem similar nacional, mercado interno', '8' => '8 — Nacional com conteúdo importado superior a 70%'] as $value => $label)
                <option value="{{ $value }}" @selected(old('origin_code', $profile->origin_code) === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="field"><label for="default_cfop">CFOP padrão interno</label><input id="default_cfop" name="default_cfop" value="{{ old('default_cfop', $profile->default_cfop) }}" inputmode="numeric" maxlength="5" placeholder="4 dígitos"><small class="muted">É uma sugestão; a operação e o destino podem exigir outro CFOP.</small></div>
          <div class="field"><label for="icms_code">CST/CSOSN do ICMS</label><input id="icms_code" name="icms_code" value="{{ old('icms_code', $profile->icms_code) }}" inputmode="numeric" maxlength="3" placeholder="2 ou 3 dígitos"></div>
          <div class="field"><label for="fiscal_unit">Unidade tributável</label><input id="fiscal_unit" name="fiscal_unit" value="{{ old('fiscal_unit', $profile->fiscal_unit ?: strtoupper($product->unit ?: 'UN')) }}" maxlength="6" placeholder="UN, KG, CX"></div>
          <div class="field"><label for="pis_code">CST do PIS</label><input id="pis_code" name="pis_code" value="{{ old('pis_code', $profile->pis_code) }}" inputmode="numeric" maxlength="2" placeholder="2 dígitos"></div>
          <div class="field"><label for="cofins_code">CST da COFINS</label><input id="cofins_code" name="cofins_code" value="{{ old('cofins_code', $profile->cofins_code) }}" inputmode="numeric" maxlength="2" placeholder="2 dígitos"></div>
          <div class="field"><label for="tax_benefit_code">Código de benefício fiscal</label><input id="tax_benefit_code" name="tax_benefit_code" value="{{ old('tax_benefit_code', $profile->tax_benefit_code) }}" maxlength="20"></div>
          <div class="field"><label for="ibs_cbs_tax_status">CST do IBS/CBS</label><input id="ibs_cbs_tax_status" name="ibs_cbs_tax_status" value="{{ old('ibs_cbs_tax_status', $profile->ibs_cbs_tax_status) }}" inputmode="numeric" maxlength="3" placeholder="3 dígitos"></div>
          <div class="field"><label for="ibs_cbs_classification_code">Classificação IBS/CBS</label><input id="ibs_cbs_classification_code" name="ibs_cbs_classification_code" value="{{ old('ibs_cbs_classification_code', $profile->ibs_cbs_classification_code) }}" inputmode="numeric" maxlength="6" placeholder="6 dígitos"><small class="muted">Confirme ambos os códigos na tabela oficial vigente com a contabilidade.</small></div>
          <div class="field full"><label for="notes">Observações contábeis</label><textarea id="notes" name="notes" maxlength="2000">{{ old('notes', $profile->notes) }}</textarea></div>
          <div class="field full"><div class="actions"><button type="submit">Salvar classificação</button><span class="muted">Versão {{ $profile->configuration_version ?: 1 }}</span></div></div>
        </div>
      </form>
    </div>
  </section>
@endsection
