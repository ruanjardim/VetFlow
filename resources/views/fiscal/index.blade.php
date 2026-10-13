@extends('layouts.admin')

@section('title', 'Fiscal - VetFlow')

@section('content')
  @php
    $statusLabels = [
      'draft' => 'Preparado',
      'queued' => 'Na fila',
      'processing' => 'Processando',
      'authorized' => 'Autorizado',
      'cancellation_queued' => 'Cancelamento na fila',
      'cancellation_failed' => 'Falha no cancelamento',
      'rejected' => 'Rejeitado',
      'cancelled' => 'Cancelado',
      'failed' => 'Falhou',
    ];
    $inutilizationStatusLabels = [
      'queued' => 'Na fila',
      'processing' => 'Processando',
      'authorized' => 'Autorizada',
      'rejected' => 'Rejeitada',
      'failed' => 'Sem confirmação',
    ];
    $homologationDecisionLabels = [
      'approved_for_production_review' => 'Aprovada para análise técnica',
      'follow_up_required' => 'Acompanhamento necessário',
    ];
    $municipality = $municipalityReadiness['municipality'];
  @endphp

  <header class="topbar">
    <div>
      <h1>Módulo fiscal</h1>
      <p>Prepare, revise e acompanhe NFC-e e NFS-e usando a conta Focus contratada pela própria clínica.</p>
    </div>
    <div class="row-actions">
      <a class="button secondary" href="{{ route('fiscal.documents.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Central de documentos</a>
      <a class="button secondary" href="{{ route('fiscal.reconciliation.index', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Conciliação diária</a>
    </div>
  </header>

  <div class="alert info">
    <strong>Modelo comercial:</strong> a assinatura, o certificado e o relacionamento com a Focus NFe são de responsabilidade da clínica.
    O VetFlow apenas integra a conta conectada. O comprovante não fiscal e o funcionamento do caixa independem dessa conexão.
  </div>

  @if($externalOperationsAvailable)
    <div class="alert info">
      <strong>Homologação operacional:</strong> a fila externa está habilitada para o piloto revisado. Produção permanece indisponível e cada envio continua exigindo preparação e ação explícitas.
    </div>
  @else
    <div class="alert warning">
      <strong>Modo seguro:</strong> somente homologação está disponível. O VetFlow não transmite, autoriza ou cancela documentos fiscais automaticamente. Uma numeração só é reservada após a geração explícita de um payload local.
    </div>
  @endif

  @if($requiresClinic)
    <form method="GET" action="{{ route('fiscal.index') }}" class="panel panel-body">
      <div class="field">
        <label for="fiscal-clinic">Estabelecimento</label>
        <select id="fiscal-clinic" name="clinic_id" data-auto-submit-select>
          @foreach($clinics as $clinic)
            <option value="{{ $clinic->id }}" @selected($selectedClinicId === $clinic->id)>{{ $clinic->trade_name ?: $clinic->corporate_name }}</option>
          @endforeach
        </select>
      </div>
    </form>
  @endif

  <section class="grid stats">
    <div class="stat">
      <span>Produtos prontos</span>
      <strong>{{ $taxCatalogStats['product_ready'] }}/{{ $taxCatalogStats['product_total'] }}</strong>
      <a href="{{ route('fiscal.products.index', array_filter(['clinic_id' => $requiresClinic ? $selectedClinicId : null, 'status' => 'pending'])) }}">{{ $taxCatalogStats['product_pending'] }} pendente(s)</a>
    </div>
    <div class="stat">
      <span>Serviços prontos</span>
      <strong>{{ $taxCatalogStats['service_ready'] }}/{{ $taxCatalogStats['service_total'] }}</strong>
      <a href="{{ route('fiscal.services.index', array_filter(['clinic_id' => $requiresClinic ? $selectedClinicId : null, 'status' => 'pending'])) }}">{{ $taxCatalogStats['service_pending'] }} pendente(s)</a>
    </div>
    <div class="stat">
      <span>Transmissão externa</span>
      <strong>{{ $externalOperationsAvailable ? 'Homologação habilitada' : 'Bloqueada' }}</strong>
      <span class="muted">{{ $runtimeReadiness['ready_count'] }}/{{ $runtimeReadiness['total_count'] }} controles operacionais</span>
    </div>
    <div class="stat">
      <span>Município piloto</span>
      <strong>{{ $municipality['label'] ?? 'A definir' }}</strong>
      <span class="muted">{{ $municipalityReadiness['ready_count'] }}/{{ $municipalityReadiness['total_count'] }} itens de configuração</span>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Gate seguro de homologação</h2>
        <p>Evidências técnicas e revisão humana por estabelecimento. Este gate qualifica uma futura análise de produção, mas nunca ativa produção.</p>
      </div>
      @if($homologationReadiness['eligible_for_production_review'])
        <span class="badge success">Apta para análise técnica</span>
      @elseif($homologationReadiness['technical_ready'])
        <span class="badge warning">Revisão humana pendente</span>
      @else
        <span class="badge warning">Homologação incompleta</span>
      @endif
    </div>
    <div class="panel-body">
      <div class="actions">
        <a class="button secondary" href="{{ route('fiscal.homologation-report', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Abrir dossiê</a>
        <a class="button secondary" href="{{ route('fiscal.homologation-report.json', $requiresClinic ? ['clinic_id' => $selectedClinicId] : []) }}">Baixar JSON seguro</a>
      </div>
      <div class="alert warning">
        <strong>Produção bloqueada:</strong> nenhuma decisão registrada nesta tela habilita emissão em produção.
        A ativação futura exigirá uma implementação separada, credenciais de produção isoladas e nova autorização explícita.
      </div>

      <p>
        <strong>{{ $homologationReadiness['ready_count'] }}/{{ $homologationReadiness['total_count'] }}</strong>
        evidências atuais prontas.
        @if($homologationReadiness['latest_review'])
          A última revisão foi
          <strong>{{ $homologationDecisionLabels[$homologationReadiness['latest_review']->decision] ?? $homologationReadiness['latest_review']->decision }}</strong>
          e está
          <strong>{{ $homologationReadiness['latest_review_current'] ? 'válida para as evidências atuais' : 'desatualizada' }}</strong>.
        @else
          Ainda não existe revisão humana registrada.
        @endif
      </p>

      <div class="table-wrap">
        <table>
          <thead><tr><th>Área</th><th>Evidência exigida</th><th>Status</th><th>Próximo passo</th></tr></thead>
          <tbody>
            @foreach($homologationReadiness['checks'] as $check)
              <tr>
                <td>{{ $check['category'] }}</td>
                <td>
                  {{ $check['label'] }}
                  @if($check['detail'])<div class="muted">{{ $check['detail'] }}</div>@endif
                </td>
                <td><span class="badge {{ $check['ready'] ? 'success' : 'warning' }}">{{ $check['ready'] ? 'Comprovada' : 'Pendente' }}</span></td>
                <td>{{ $check['ready'] ? 'Nenhuma ação necessária.' : $check['guidance'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <details>
        <summary>Registrar revisão humana das evidências atuais</summary>
        <form method="POST" action="{{ route('fiscal.homologation-reviews.store') }}" class="form-grid">
          @csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <div class="field">
            <label for="homologation_decision">Decisão</label>
            <select id="homologation_decision" name="decision" required>
              <option value="follow_up_required" @selected(old('decision') === 'follow_up_required')>Acompanhamento necessário</option>
              @if($homologationReadiness['technical_ready'])
                <option value="approved_for_production_review" @selected(old('decision') === 'approved_for_production_review')>Aprovar para análise técnica de produção</option>
              @endif
            </select>
            @unless($homologationReadiness['technical_ready'])
              <small class="muted">A aprovação fica indisponível até todas as evidências técnicas estarem comprovadas.</small>
            @endunless
          </div>
          <div class="field">
            <label for="homologation_reviewer_name">Responsável técnico/contábil</label>
            <input id="homologation_reviewer_name" name="reviewer_name" maxlength="120" value="{{ old('reviewer_name', auth()->user()?->name) }}" required>
          </div>
          <div class="field full">
            <label for="homologation_notes">Parecer ou acompanhamento</label>
            <textarea id="homologation_notes" name="notes" maxlength="1000" placeholder="Obrigatório quando houver acompanhamento pendente.">{{ old('notes') }}</textarea>
          </div>
          <div class="field">
            <label for="homologation_current_password">Sua senha do VetFlow</label>
            <input id="homologation_current_password" name="current_password" type="password" autocomplete="current-password" required>
          </div>
          <label class="checkbox-field full">
            <input type="checkbox" name="confirm_review" value="1" required>
            <span>Confirmo que revisei as evidências exibidas e entendo que esta decisão não ativa produção.</span>
          </label>
          <div class="field full"><button type="submit">Registrar revisão imutável</button></div>
        </form>
      </details>
    </div>

    @if($homologationReadiness['reviews']->isNotEmpty())
      <div class="table-wrap">
        <table>
          <thead><tr><th>Decisão</th><th>Responsável informado</th><th>Registrado por</th><th>Evidência</th><th>Data</th></tr></thead>
          <tbody>
            @foreach($homologationReadiness['reviews'] as $review)
              <tr>
                <td>{{ $homologationDecisionLabels[$review->decision] ?? $review->decision }}</td>
                <td>
                  {{ $review->reviewer_name }}
                  @if($review->notes)<div class="muted">{{ $review->notes }}</div>@endif
                </td>
                <td>{{ $review->reviewer?->name ?? 'Usuário removido' }}</td>
                <td><span class="badge {{ hash_equals($homologationReadiness['evidence_fingerprint'], $review->evidence_fingerprint) ? 'success' : 'muted-badge' }}">{{ hash_equals($homologationReadiness['evidence_fingerprint'], $review->evidence_fingerprint) ? 'Atual' : 'Superada' }}</span></td>
                <td>{{ optional($review->occurred_at)->format('d/m/Y H:i') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Conta Focus da clínica</h2>
        <p>Conecte exclusivamente o token de homologação da conta contratada por este estabelecimento.</p>
      </div>
      <span class="badge {{ $focusCredential ? 'success' : 'warning' }}">
        {{ $focusCredential ? 'Conta conectada' : 'Não conectada' }}
      </span>
    </div>
    <div class="panel-body">
      @if(!$customerManagedFocusCredentials)
        <div class="alert warning">
          A conexão autônoma ainda não foi habilitada neste servidor. O administrador da implantação deve selecionar o armazenamento criptografado de credenciais antes de receber tokens de clientes.
        </div>
      @elseif($focusCredential)
        <div class="alert info">
          <strong>Token de homologação conectado:</strong> final {{ $focusCredential->token_last_four ? '••••'.$focusCredential->token_last_four : 'protegido' }}.
          Configurado em {{ optional($focusCredential->rotated_at)->format('d/m/Y H:i') }} por {{ $focusCredential->configuredBy?->name ?? 'usuário autorizado' }}.
          O valor completo é criptografado e nunca volta a ser exibido.
        </div>

        <details>
          <summary>Substituir o token de homologação</summary>
          <form method="POST" action="{{ route('fiscal.focus-credential.connect') }}" class="form-grid">
            @csrf
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <div class="field">
              <label for="focus_token">Novo token Focus</label>
              <input id="focus_token" name="focus_token" type="password" minlength="16" maxlength="512" autocomplete="off" required>
            </div>
            <div class="field">
              <label for="focus_token_confirmation">Confirme o novo token</label>
              <input id="focus_token_confirmation" name="focus_token_confirmation" type="password" minlength="16" maxlength="512" autocomplete="off" required>
            </div>
            <div class="field">
              <label for="focus_current_password_rotate">Sua senha do VetFlow</label>
              <input id="focus_current_password_rotate" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <label class="checkbox-field full">
              <input type="checkbox" name="accept_focus_responsibility" value="1" required>
              <span>Confirmo que esta conta Focus pertence à clínica e que sua assinatura e configuração fiscal são responsabilidade do estabelecimento.</span>
            </label>
            <div class="field full"><button type="submit">Criptografar e substituir token</button></div>
          </form>
        </details>

        <details>
          <summary>Desconectar conta Focus</summary>
          <form method="POST" action="{{ route('fiscal.focus-credential.disconnect') }}" class="form-grid">
            @csrf
            @method('DELETE')
            @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
            <div class="field">
              <label for="focus_current_password_disconnect">Sua senha do VetFlow</label>
              <input id="focus_current_password_disconnect" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <label class="checkbox-field full">
              <input type="checkbox" name="confirm_disconnect" value="1" required>
              <span>Entendo que a desconexão bloqueia novas emissões, consultas, cancelamentos e inutilizações fiscais desta clínica.</span>
            </label>
            <div class="field full"><button type="submit" class="danger">Desconectar conta</button></div>
          </form>
        </details>
      @else
        <div class="alert warning">
          O VetFlow não vende nem inclui uma assinatura Focus. A clínica deve criar sua própria conta, configurar a empresa e então conectar aqui o token de homologação.
          <a href="https://focusnfe.com.br/cadastro/" target="_blank" rel="noopener">Abrir cadastro oficial da Focus</a>.
        </div>
        <form method="POST" action="{{ route('fiscal.focus-credential.connect') }}" class="form-grid">
          @csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <div class="field">
            <label for="focus_token">Token Focus de homologação</label>
            <input id="focus_token" name="focus_token" type="password" minlength="16" maxlength="512" autocomplete="off" required>
            <small class="muted">O token será criptografado com a chave da instalação e nunca será exibido novamente.</small>
          </div>
          <div class="field">
            <label for="focus_token_confirmation">Confirme o token</label>
            <input id="focus_token_confirmation" name="focus_token_confirmation" type="password" minlength="16" maxlength="512" autocomplete="off" required>
          </div>
          <div class="field">
            <label for="focus_current_password">Sua senha do VetFlow</label>
            <input id="focus_current_password" name="current_password" type="password" autocomplete="current-password" required>
          </div>
          <label class="checkbox-field full">
            <input type="checkbox" name="accept_focus_responsibility" value="1" required>
            <span>Confirmo que esta conta Focus pertence à clínica e que sua assinatura e configuração fiscal são responsabilidade do estabelecimento.</span>
          </label>
          <div class="field full"><button type="submit">Conectar conta de homologação</button></div>
        </form>
      @endif
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Preparação NFC-e do RJ</h2>
        <p>Checklist do modelo 65 em homologação. O CSC permanece fora do VetFlow e a transmissão exige todos os controles operacionais do servidor.</p>
      </div>
      <span class="badge {{ $nfceReadiness['configuration_ready'] ? 'success' : 'warning' }}">
        {{ $nfceReadiness['configuration_ready'] ? 'Configuração completa' : 'Configuração pendente' }}
      </span>
    </div>
    <div class="panel-body">
      <div class="alert info">
        <strong>{{ $nfceReadiness['scope'] }}:</strong> a SEFAZ-RJ exige inscrição estadual habilitada, credenciamento, certificado ICP-Brasil e CSC de homologação.
        O VetFlow guarda somente a referência externa do CSC e seu identificador público de 6 dígitos.
        <a href="https://portal.fazenda.rj.gov.br/dfe/wp-content/uploads/sites/17/2023/01/DF-e_NFC-e.pdf" target="_blank" rel="noopener">Ver manual oficial da SEFAZ-RJ</a>.
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Verificação da NFC-e</th><th>Status</th></tr></thead>
          <tbody>
            @foreach($nfceReadiness['checks'] as $check)
              <tr>
                <td>{{ $check['label'] }}</td>
                <td><span class="badge {{ $check['ready'] ? 'success' : 'warning' }}">{{ $check['ready'] ? 'Pronto' : 'Pendente' }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Inutilização de faixa da NFC-e</h2>
        <p>Declara à SEFAZ-RJ que uma faixa já ultrapassada da série não será utilizada.</p>
      </div>
      <span class="badge {{ $nfceInutilizationAvailable ? 'success' : 'warning' }}">
        {{ $nfceInutilizationAvailable ? 'Homologação disponível' : 'Operação bloqueada' }}
      </span>
    </div>
    <div class="panel-body">
      <div class="alert warning">
        <strong>Operação fiscal definitiva:</strong> use somente para lacunas confirmadas com a contabilidade. O VetFlow bloqueia números futuros, faixas sobrepostas e qualquer número já reservado para uma NFC-e.
      </div>

      @if($nfceInutilizationAvailable && $nfceSequence && $nfceReadiness['configuration_ready'])
        <form method="POST" action="{{ route('fiscal.nfce.inutilizations.store') }}">
          @csrf
          @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
          <div class="form-grid">
            <div class="field">
              <label>Série configurada</label>
              <input value="{{ $nfceSequence->series }}" disabled>
            </div>
            <div class="field">
              <label>Próximo número disponível</label>
              <input value="{{ $nfceSequence->next_number }}" disabled>
              <small class="muted">A faixa deve terminar em {{ max(0, $nfceSequence->next_number - 1) }} ou antes.</small>
            </div>
            <div class="field">
              <label for="number_start">Primeiro número da faixa</label>
              <input id="number_start" name="number_start" type="number" min="1" max="999999999" value="{{ old('number_start') }}" required>
            </div>
            <div class="field">
              <label for="number_end">Último número da faixa</label>
              <input id="number_end" name="number_end" type="number" min="1" max="999999999" value="{{ old('number_end') }}" required>
            </div>
            <div class="field full">
              <label for="justification">Justificativa</label>
              <textarea id="justification" name="justification" minlength="15" maxlength="255" required>{{ old('justification') }}</textarea>
              <small class="muted">Entre 15 e 255 caracteres. O texto será enviado à SEFAZ exatamente como informado.</small>
            </div>
          </div>
          <label class="checkbox-field">
            <input type="checkbox" name="confirm_inutilization" value="1" required>
            <span>Revisei a série e a faixa e confirmo que esses números não serão utilizados.</span>
          </label>
          <button type="submit" class="danger">Colocar inutilização na fila de homologação</button>
        </form>
      @else
        <p class="muted">Complete o checklist da NFC-e, conecte a conta Focus da clínica e habilite adaptador, fila e tráfego de homologação no servidor para liberar esta operação.</p>
      @endif
    </div>

    @if($nfceInutilizations->isNotEmpty())
      <div class="table-wrap">
        <table>
          <thead><tr><th>Série</th><th>Faixa</th><th>Status</th><th>Protocolo</th><th>Solicitado por</th><th>Data</th></tr></thead>
          <tbody>
            @foreach($nfceInutilizations as $inutilization)
              <tr>
                <td>{{ $inutilization->series }}</td>
                <td>{{ $inutilization->number_start }}–{{ $inutilization->number_end }}</td>
                <td>
                  <span class="badge {{ $inutilization->status === 'authorized' ? 'success' : (in_array($inutilization->status, ['rejected', 'failed'], true) ? 'danger' : 'warning') }}">
                    {{ $inutilizationStatusLabels[$inutilization->status] ?? $inutilization->status }}
                  </span>
                  @if($inutilization->last_error_message)<div class="muted">{{ $inutilization->last_error_message }}</div>@endif
                </td>
                <td>{{ $inutilization->protocol ?? '-' }}</td>
                <td>{{ $inutilization->requester?->name ?? 'Sistema' }}</td>
                <td>{{ optional($inutilization->queued_at)->format('d/m/Y H:i:s') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  @if($fiscalAlerts['total'] > 0)
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Atenção operacional</h2>
          <p>Documentos com falha ou aguardando o provedor além do prazo configurado.</p>
        </div>
        <span class="badge {{ $fiscalAlerts['critical_count'] > 0 ? 'danger' : 'warning' }}">{{ $fiscalAlerts['total'] }} alerta(s)</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Documento</th><th>Alerta</th><th>Detalhe</th><th>Última mudança</th><th>Ação</th></tr></thead>
          <tbody>
            @foreach($fiscalAlerts['items'] as $alert)
              @php($alertDocument = $alert['document'])
              <tr>
                <td>
                  <strong>{{ $types[$alertDocument->type] ?? strtoupper($alertDocument->type) }}</strong>
                  <div class="muted">{{ $alertDocument->sale?->code ?? $alertDocument->ulid }}</div>
                </td>
                <td><span class="badge {{ $alert['severity'] }}">{{ $alert['title'] }}</span></td>
                <td>{{ $alert['message'] }}</td>
                <td>{{ optional($alertDocument->updated_at)->diffForHumans() }}</td>
                <td><a class="button secondary" href="{{ route('fiscal.documents.show', $alertDocument->id) }}">Revisar</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($fiscalAlerts['displayed_count'] < $fiscalAlerts['total'])
        <div class="panel-body muted">Exibindo os {{ $fiscalAlerts['displayed_count'] }} alertas mais antigos e críticos de {{ $fiscalAlerts['total'] }}.</div>
      @endif
    </section>
  @endif

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Roteamento municipal da NFS-e</h2>
        <p>O município define o padrão técnico e as validações usadas antes de qualquer integração.</p>
      </div>
      <span class="badge {{ $municipalityReadiness['configuration_ready'] ? 'success' : 'warning' }}">
        {{ $municipalityReadiness['configuration_ready'] ? 'Configuração completa' : 'Configuração pendente' }}
      </span>
    </div>
    <div class="panel-body">
      @if($municipality)
        <div class="alert info">
          <strong>{{ $municipality['label'] }}:</strong> desde 01/01/2026, a emissão ocorre no Emissor Nacional por uma DPS.
          O piloto está mapeado para {{ $municipality['homologation_environment'] }}, com referência ao leiaute {{ $municipality['nfse_layout_version'] }}.
          <a href="{{ $municipality['source_url'] }}" target="_blank" rel="noopener">Ver orientação oficial</a>.
        </div>
      @elseif($profile->municipality_code)
        <div class="alert warning">
          O código IBGE {{ $profile->municipality_code }} ainda não possui uma rota municipal revisada no piloto. A preparação interna continua disponível, mas a futura transmissão seguirá bloqueada.
        </div>
      @else
        <div class="alert warning">
          Informe o município emissor. O primeiro perfil revisado é Niterói/RJ, código IBGE 3303302.
        </div>
      @endif

      @if($profile->provider && !$runtimeReadiness['operational_ready'])
        <div class="alert warning">
          <strong>{{ $providerLabel }} selecionada para homologação:</strong> isso prepara a futura integração, mas não conecta o VetFlow ao provedor. O cliente HTTP, as credenciais reais e toda transmissão continuam desabilitados.
        </div>
      @elseif($profile->provider)
        <div class="alert info">
          <strong>{{ $providerLabel }} operacional em homologação:</strong> o token da clínica permanece protegido pelo resolvedor configurado e nunca é incluído em documentos, logs ou respostas da interface.
        </div>
      @endif

      <div class="table-wrap">
        <table>
          <thead><tr><th>Verificação para integração</th><th>Status</th></tr></thead>
          <tbody>
            @foreach($municipalityReadiness['checks'] as $check)
              <tr>
                <td>{{ $check['label'] }}</td>
                <td><span class="badge {{ $check['ready'] ? 'success' : 'warning' }}">{{ $check['ready'] ? 'Pronto' : 'Pendente' }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Ativação operacional da homologação</h2>
        <p>Controles do servidor. Esta tela mostra apenas disponibilidade, nunca caminhos, referências resolvidas ou segredos.</p>
      </div>
      <span class="badge {{ $runtimeReadiness['operational_ready'] ? 'success' : 'warning' }}">
        {{ $runtimeReadiness['operational_ready'] ? 'Pronta para piloto' : 'Bloqueada por segurança' }}
      </span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Controle operacional</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($runtimeReadiness['checks'] as $check)
            <tr>
              <td>{{ $check['label'] }}</td>
              <td><span class="badge {{ $check['ready'] ? 'success' : 'warning' }}">{{ $check['ready'] ? 'Pronto' : 'Pendente' }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Configuração de homologação</h2>
        <p>Os dados abaixo são versionados. O token Focus é administrado separadamente na conexão criptografada da clínica.</p>
      </div>
      <span class="badge {{ $profile->enabled ? 'success' : 'muted-badge' }}">{{ $profile->enabled ? 'Ativo para preparação' : 'Desativado' }}</span>
    </div>
    <div class="panel-body">
      <form method="POST" action="{{ route('fiscal.update') }}">
        @csrf
        @method('PUT')
        @if($requiresClinic)<input type="hidden" name="clinic_id" value="{{ $selectedClinicId }}">@endif
        <input type="hidden" name="environment" value="homologation">

        <div class="form-grid">
          <div class="field">
            <label for="enabled">Preparação fiscal</label>
            <select id="enabled" name="enabled" required>
              <option value="0" @selected(! old('enabled', $profile->enabled))>Desativada</option>
              <option value="1" @selected(old('enabled', $profile->enabled))>Ativa em homologação</option>
            </select>
          </div>
          <div class="field">
            <label>Ambiente</label>
            <input value="Homologação (sem valor fiscal)" disabled>
          </div>
          <div class="field">
            <label for="provider">Provedor fiscal</label>
            <select id="provider" name="provider">
              <option value="">Nenhum (somente preparação)</option>
              @foreach($providerOptions as $providerValue => $providerOptionLabel)
                <option value="{{ $providerValue }}" @selected(old('provider', $profile->provider) === $providerValue)>{{ $providerOptionLabel }}</option>
              @endforeach
            </select>
            <small class="muted">A seleção não habilita tráfego externo.</small>
          </div>
          @unless($customerManagedFocusCredentials)
            <div class="field">
              <label for="provider_credential_reference">Referência externa da credencial do provedor</label>
              <input id="provider_credential_reference" name="provider_credential_reference" value="{{ old('provider_credential_reference', data_get($profile->metadata, 'provider_credential_reference')) }}" maxlength="160" placeholder="Ex.: vault://vetflow/focus/niteroi">
              <small class="muted">Informe somente o identificador no cofre. Nunca cole token, senha ou certificado.</small>
            </div>
          @endunless
          <div class="field">
            <label for="nfce_series">Série da NFC-e</label>
            <input id="nfce_series" name="nfce_series" type="number" min="0" max="999" value="{{ old('nfce_series', data_get($profile->metadata, 'focus_nfce.series')) }}" placeholder="Ex.: 0">
            <small class="muted">A série única é 0. A numeração é independente da NF-e modelo 55 e da NFS-e.</small>
          </div>
          <div class="field">
            <label for="nfce_next_number">Próximo número da NFC-e</label>
            <input id="nfce_next_number" name="nfce_next_number" type="number" min="1" max="999999999" value="{{ old('nfce_next_number', $nfceSequence?->next_number) }}" placeholder="Confirme antes de iniciar">
            <small class="muted">Cada payload local reserva um número; após o uso, a sequência nunca pode retroceder.</small>
          </div>
          <div class="field">
            <label for="nfce_csc_id">Identificador do CSC de homologação</label>
            <input id="nfce_csc_id" name="nfce_csc_id" value="{{ old('nfce_csc_id', data_get($profile->metadata, 'focus_nfce.csc_id')) }}" inputmode="numeric" minlength="6" maxlength="6" placeholder="6 dígitos">
            <small class="muted">Este é o ID público do token, não o CSC.</small>
          </div>
          <div class="field">
            <label for="nfce_csc_reference">Referência externa do CSC</label>
            <input id="nfce_csc_reference" name="nfce_csc_reference" value="{{ old('nfce_csc_reference', data_get($profile->metadata, 'focus_nfce.csc_reference')) }}" maxlength="160" placeholder="Ex.: focus://empresa/nfce/homologacao/csc">
            <small class="muted">Nunca cole o CSC. Informe apenas onde ele foi provisionado no provedor/cofre.</small>
          </div>
          <div class="field">
            <label for="nfce_pix_payment_code">Código fiscal do Pix</label>
            <select id="nfce_pix_payment_code" name="nfce_pix_payment_code">
              <option value="">Selecione antes de emitir venda com Pix</option>
              <option value="17" @selected(old('nfce_pix_payment_code', data_get($profile->metadata, 'focus_nfce.pix_payment_code')) === '17')>17 — Pix dinâmico</option>
              <option value="20" @selected(old('nfce_pix_payment_code', data_get($profile->metadata, 'focus_nfce.pix_payment_code')) === '20')>20 — Pix estático</option>
            </select>
          </div>
          <div class="field">
            <label for="nfce_sefaz_credentialed">Credenciamento na SEFAZ-RJ</label>
            <select id="nfce_sefaz_credentialed" name="nfce_sefaz_credentialed">
              <option value="0" @selected(! old('nfce_sefaz_credentialed', data_get($profile->metadata, 'focus_nfce.sefaz_credentialed', false)))>Ainda não confirmado</option>
              <option value="1" @selected(old('nfce_sefaz_credentialed', data_get($profile->metadata, 'focus_nfce.sefaz_credentialed', false)))>Confirmado para homologação</option>
            </select>
            <small class="muted">Confirme somente após consultar o credenciamento do estabelecimento.</small>
          </div>
          <div class="field">
            <label for="nfse_dps_series">Série da DPS Nacional</label>
            <input id="nfse_dps_series" name="nfse_dps_series" type="number" min="1" max="49999" value="{{ old('nfse_dps_series', data_get($profile->metadata, 'focus_nfe.dps_series')) }}" placeholder="Ex.: 1">
            <small class="muted">Faixa aceita pela API: 1 a 49.999.</small>
          </div>
          <div class="field">
            <label for="nfse_dps_next_number">Próximo número da DPS</label>
            <input id="nfse_dps_next_number" name="nfse_dps_next_number" type="number" min="1" max="999999999999999" value="{{ old('nfse_dps_next_number', $dpsSequence?->next_number) }}" placeholder="Confirme antes de iniciar">
            <small class="muted">Cada payload gerado reserva um número. Não reduza a sequência após o primeiro uso.</small>
          </div>
          <div class="field">
            <label for="simple_national_option">Situação perante o Simples Nacional</label>
            <select id="simple_national_option" name="simple_national_option">
              <option value="">Selecione com a contabilidade</option>
              <option value="1" @selected(old('simple_national_option', data_get($profile->metadata, 'focus_nfe.simple_national_option')) === '1')>1 — Não optante</option>
              <option value="2" @selected(old('simple_national_option', data_get($profile->metadata, 'focus_nfe.simple_national_option')) === '2')>2 — Optante MEI</option>
              <option value="3" @selected(old('simple_national_option', data_get($profile->metadata, 'focus_nfe.simple_national_option')) === '3')>3 — Optante ME/EPP</option>
            </select>
          </div>
          <div class="field">
            <label for="simple_national_tax_regime">Apuração do Simples Nacional</label>
            <select id="simple_national_tax_regime" name="simple_national_tax_regime">
              <option value="">Não aplicável ou a confirmar</option>
              <option value="1" @selected(old('simple_national_tax_regime', data_get($profile->metadata, 'focus_nfe.simple_national_tax_regime')) === '1')>1 — Tributos federais e ISS pelo Simples</option>
              <option value="2" @selected(old('simple_national_tax_regime', data_get($profile->metadata, 'focus_nfe.simple_national_tax_regime')) === '2')>2 — Federais pelo Simples e ISS por fora</option>
              <option value="3" @selected(old('simple_national_tax_regime', data_get($profile->metadata, 'focus_nfe.simple_national_tax_regime')) === '3')>3 — Federais e ISS por fora</option>
            </select>
          </div>
          <div class="field">
            <label for="special_tax_regime">Regime especial municipal</label>
            <select id="special_tax_regime" name="special_tax_regime">
              <option value="">Selecione com a contabilidade</option>
              <option value="0" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '0')>0 — Nenhum</option>
              <option value="1" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '1')>1 — Ato cooperado</option>
              <option value="2" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '2')>2 — Estimativa</option>
              <option value="3" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '3')>3 — Microempresa municipal</option>
              <option value="4" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '4')>4 — Notário ou registrador</option>
              <option value="5" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '5')>5 — Profissional autônomo</option>
              <option value="6" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '6')>6 — Sociedade de profissionais</option>
              <option value="9" @selected(old('special_tax_regime', data_get($profile->metadata, 'focus_nfe.special_tax_regime')) === '9')>9 — Outros</option>
            </select>
          </div>
          <div class="field">
            <label for="nfce_enabled">NFC-e de produtos</label>
            <select id="nfce_enabled" name="nfce_enabled" required>
              <option value="0" @selected(! old('nfce_enabled', $profile->nfce_enabled))>Desabilitada</option>
              <option value="1" @selected(old('nfce_enabled', $profile->nfce_enabled))>Habilitada para preparação</option>
            </select>
          </div>
          <div class="field">
            <label for="nfse_enabled">NFS-e de serviços</label>
            <select id="nfse_enabled" name="nfse_enabled" required>
              <option value="0" @selected(! old('nfse_enabled', $profile->nfse_enabled))>Desabilitada</option>
              <option value="1" @selected(old('nfse_enabled', $profile->nfse_enabled))>Habilitada para preparação</option>
            </select>
          </div>
          <div class="field">
            <label for="state_registration">Inscrição estadual</label>
            <input id="state_registration" name="state_registration" value="{{ old('state_registration', $profile->state_registration) }}" maxlength="30">
          </div>
          <div class="field">
            <label for="municipal_registration">Inscrição municipal</label>
            <input id="municipal_registration" name="municipal_registration" value="{{ old('municipal_registration', $profile->municipal_registration) }}" maxlength="30">
          </div>
          <div class="field">
            <label for="tax_regime">Regime tributário</label>
            <select id="tax_regime" name="tax_regime">
              <option value="">Selecione</option>
              <option value="simple_national" @selected(old('tax_regime', $profile->tax_regime) === 'simple_national')>Simples Nacional</option>
              <option value="simple_excess_sublimit" @selected(old('tax_regime', $profile->tax_regime) === 'simple_excess_sublimit')>Simples Nacional — excesso de sublimite</option>
              <option value="normal" @selected(old('tax_regime', $profile->tax_regime) === 'normal')>Regime normal</option>
            </select>
          </div>
          <div class="field">
            <label for="municipality_code">Código IBGE do município</label>
            <input id="municipality_code" name="municipality_code" value="{{ old('municipality_code', $profile->municipality_code) }}" inputmode="numeric" maxlength="7" placeholder="7 dígitos" list="fiscal-municipalities">
            <datalist id="fiscal-municipalities">
              @foreach($municipalitySuggestions as $suggestion)
                <option value="{{ $suggestion['ibge_code'] }}">{{ $suggestion['label'] }}</option>
              @endforeach
            </datalist>
            <small class="muted">Niterói/RJ: 3303302. Outros municípios serão adicionados sem alterar os documentos já preparados.</small>
          </div>
          <div class="field">
            <label for="cnae">CNAE principal</label>
            <input id="cnae" name="cnae" value="{{ old('cnae', $profile->cnae) }}" inputmode="numeric" maxlength="7" placeholder="7 dígitos, sem pontuação">
          </div>
          <div class="field">
            <label for="certificate_reference">Referência externa do certificado</label>
            <input id="certificate_reference" name="certificate_reference" value="{{ old('certificate_reference', $profile->certificate_reference) }}" maxlength="160" placeholder="Ex.: cofre/provedor/certificado-clinica">
            <small class="muted">Informe apenas um identificador seguro. Nunca cole certificado ou senha.</small>
          </div>
          <div class="field">
            <label for="certificate_fingerprint">Impressão digital do certificado</label>
            <input id="certificate_fingerprint" name="certificate_fingerprint" value="{{ old('certificate_fingerprint', $profile->certificate_fingerprint) }}" maxlength="128">
          </div>
          <div class="field">
            <label for="certificate_expires_at">Validade do certificado</label>
            <input id="certificate_expires_at" name="certificate_expires_at" type="date" value="{{ old('certificate_expires_at', optional($profile->certificate_expires_at)->format('Y-m-d')) }}">
          </div>
          <div class="field full">
            <div class="actions">
              <button type="submit">Salvar configuração</button>
              <span class="muted">Versão atual: {{ $profile->configuration_version ?? 1 }}</span>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading">
      <div>
        <h2>Preparações fiscais</h2>
        <p>Registros criados manualmente a partir de vendas concluídas.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Documento</th>
            <th>Venda</th>
            <th>Status</th>
            <th>Ambiente</th>
            <th>Responsável</th>
            <th>Data</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          @forelse($documents as $document)
            <tr>
              <td><strong>{{ $types[$document->type] ?? strtoupper($document->type) }}</strong></td>
              <td>{{ $document->sale?->code ?? 'Venda removida' }}</td>
              <td><span class="badge">{{ $statusLabels[$document->status] ?? ucfirst($document->status) }}</span></td>
              <td>Homologação</td>
              <td>{{ $document->creator?->name ?? 'Sistema' }}</td>
              <td>{{ optional($document->created_at)->format('d/m/Y H:i') }}</td>
              <td><a class="button secondary" href="{{ route('fiscal.documents.show', $document->id) }}">Revisar</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Nenhum documento foi preparado. Abra o comprovante de uma venda concluída para iniciar.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{ $documents->withQueryString()->links() }}
@endsection
