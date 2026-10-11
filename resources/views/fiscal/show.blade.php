@extends('layouts.admin')

@section('title', 'Preparação fiscal - VetFlow')

@section('content')
  @php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $issuer = $document->issuer_snapshot ?? [];
    $recipient = $document->recipient_snapshot ?? [];
    $totals = $document->totals_snapshot ?? [];
    $statusLabels = [
      'draft' => 'Preparado',
      'queued' => 'Na fila',
      'processing' => 'Processando',
      'authorized' => 'Autorizado',
      'cancellation_queued' => 'Cancelamento na fila',
      'cancellation_failed' => 'Falha no cancelamento',
      'rejected' => 'Rejeitado',
      'cancelled' => 'Cancelado',
      'failed' => 'Falha de comunicação',
    ];
    $eventLabels = [
      'prepared' => 'Preparado manualmente',
      'payload_generated' => 'Payload gerado',
      'submission_queued' => 'Transmissão colocada na fila',
      'submission_started' => 'Transmissão iniciada',
      'provider_response' => 'Resposta inicial do provedor',
      'status_sync_queued' => 'Consulta colocada na fila',
      'status_synchronized' => 'Situação reconciliada',
      'webhook_received' => 'Webhook reconciliado',
      'submission_failed' => 'Transmissão esgotou as tentativas',
      'status_sync_failed' => 'Consulta esgotou as tentativas',
      'cancellation_queued' => 'Cancelamento colocado na fila',
      'cancellation_succeeded' => 'Cancelamento confirmado pelo provedor',
      'cancellation_failed' => 'Cancelamento não confirmado',
      'webhook_resend_queued' => 'Reenvio do webhook colocado na fila',
      'webhook_resend_requested' => 'Reenvio do webhook solicitado ao provedor',
      'webhook_resend_failed' => 'Reenvio do webhook esgotou as tentativas',
      'artifact_downloaded' => 'Arquivo fiscal baixado',
      'artifact_download_failed' => 'Download fiscal bloqueado ou indisponível',
      'allocation_approved' => 'Rateio fiscal revisado e aprovado',
    ];
  @endphp

  <header class="topbar">
    <div>
      <h1>{{ $types[$document->type] ?? strtoupper($document->type) }} em homologação</h1>
      <p>Preparação {{ $document->ulid }} · venda {{ $document->sale?->code ?? 'removida' }}</p>
    </div>
    <div class="actions">
      @if($document->sale)<a class="button secondary" href="{{ route('sales.receipt', $document->sale->id) }}">Voltar ao comprovante</a>@endif
      <a class="button secondary" href="{{ route('fiscal.index') }}">Central fiscal</a>
    </div>
  </header>

  @if(!$submissionAvailable)
    <div class="alert warning">
      <strong>Sem transmissão externa:</strong> os controles de implantação continuam desligados. Este documento de homologação não possui valor fiscal enquanto não houver autorização do provedor.
    </div>
  @else
    <div class="alert info">
      <strong>Homologação operacional:</strong> os envios desta tela utilizam a fila fiscal e nunca o ambiente de produção.
    </div>
  @endif

  <section class="grid stats">
    <div class="stat"><span>Status</span><strong>{{ $statusLabels[$document->status] ?? ucfirst($document->status) }}</strong></div>
    <div class="stat"><span>Ambiente</span><strong>Homologação</strong></div>
    <div class="stat"><span>Provedor planejado</span><strong>{{ $providerLabel }}</strong></div>
    <div class="stat"><span>Versão da configuração</span><strong>{{ $document->profile_version }}</strong></div>
    <div class="stat"><span>Total desta preparação</span><strong>{{ $money($totals['total'] ?? 0) }}</strong></div>
  </section>

  @if(($totals['requires_allocation'] ?? 'no') === 'yes')
    <div class="alert warning">
      Esta venda mistura tipos de item ou possui ajuste global. Revise e aprove o rateio abaixo antes de gerar qualquer payload.
    </div>
  @endif

  @if($allocationReview && $allocationReview['state'] !== 'not_required')
    @php($allocationSnapshot = $allocationReview['snapshot'] ?? [])
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Rateio fiscal da venda</h2>
          <p>Um único fechamento compartilhado entre NFC-e e NFS-e, com reconciliação exata em centavos.</p>
        </div>
        <span class="badge {{ $allocationReview['state'] === 'approved' ? 'success' : ($allocationReview['state'] === 'blocked' ? 'danger' : 'warning') }}">
          {{ $allocationReview['state'] === 'approved' ? 'Aprovado e congelado' : ($allocationReview['state'] === 'blocked' ? 'Bloqueado' : 'Aguardando aprovação') }}
        </span>
      </div>
      <div class="panel-body">
        <div class="alert info">
          <strong>Regras visíveis:</strong> desconto e acréscimo são proporcionais ao valor líquido das linhas; a entrega fica na NFC-e quando há produtos; pagamentos são proporcionais e o troco fica na NFC-e. Diferenças de arredondamento são distribuídas pelo maior resto, sempre em centavos.
        </div>

        @if($allocationReview['state'] === 'blocked')
          <div class="alert danger">
            <strong>O rateio ainda não pode ser aprovado.</strong>
            <ul>
              @foreach($allocationReview['errors'] as $allocationError)
                <li>{{ $allocationError }}</li>
              @endforeach
            </ul>
          </div>
        @else
          <div class="table-wrap">
            <table>
              <thead><tr><th>Documento</th><th>Linhas</th><th>Desconto global</th><th>Acréscimos</th><th>Entrega</th><th>Pago alocado</th><th>Troco</th><th>Total fiscal</th></tr></thead>
              <tbody>
                @foreach(($allocationSnapshot['documents'] ?? []) as $allocationDocument)
                  <tr>
                    <td><strong>{{ strtoupper($allocationDocument['type']) }}</strong></td>
                    <td>{{ $money($allocationDocument['selected_items_total']) }}</td>
                    <td>{{ $money($allocationDocument['allocated_sale_discount']) }}</td>
                    <td>{{ $money($allocationDocument['allocated_additions_total']) }}</td>
                    <td>{{ $money($allocationDocument['allocated_delivery_fee']) }}</td>
                    <td>{{ $money($allocationDocument['paid_total']) }}</td>
                    <td>{{ $money($allocationDocument['change_total']) }}</td>
                    <td><strong>{{ $money($allocationDocument['total']) }}</strong></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="receipt-info-grid">
            <div><span>Total da venda</span><strong>{{ $money(data_get($allocationSnapshot, 'reconciliation.sale_total', 0)) }}</strong></div>
            <div><span>Soma dos documentos</span><strong>{{ $money(data_get($allocationSnapshot, 'reconciliation.documents_total', 0)) }}</strong></div>
            <div><span>Total pago</span><strong>{{ $money(data_get($allocationSnapshot, 'reconciliation.paid_total', 0)) }}</strong></div>
            <div><span>Troco</span><strong>{{ $money(data_get($allocationSnapshot, 'reconciliation.change_total', 0)) }}</strong></div>
          </div>

          @if($allocationReview['state'] === 'approved')
            <p class="muted">
              Aprovado por {{ $allocationReview['allocation']->approver?->name ?? 'usuário removido' }} em {{ optional($allocationReview['allocation']->approved_at)->format('d/m/Y H:i:s') }}.
              O cálculo não acompanha alterações posteriores na venda ou nos cadastros.
            </p>
          @elseif($allocationReview['can_approve'])
            <form method="POST" action="{{ route('fiscal.sales.allocation.approve', $document->sale_id) }}">
              @csrf
              <label class="checkbox-row">
                <input type="checkbox" name="confirm_allocation" value="1" required>
                Revisei os valores de NFC-e e NFS-e e confirmo este rateio fiscal imutável.
              </label>
              <button type="submit">Aprovar e congelar o rateio</button>
            </form>
          @endif
        @endif
      </div>
    </section>
  @endif

  <section class="panel">
    <div class="panel-heading">
      <div><h2>Ciclo operacional</h2><p>Fila idempotente, retorno do provedor e reconciliação auditável.</p></div>
      <span class="badge {{ in_array($document->status, ['authorized'], true) ? 'success' : (in_array($document->status, ['rejected', 'failed', 'cancellation_failed'], true) ? 'danger' : 'warning') }}">{{ $statusLabels[$document->status] ?? $document->status }}</span>
    </div>
    <div class="panel-body">
      <div class="receipt-info-grid">
        <div><span>Referência idempotente</span><strong>{{ $document->provider_reference ?? 'Será criada com o payload' }}</strong></div>
        <div><span>Transmitido em</span><strong>{{ optional($document->submitted_at)->format('d/m/Y H:i:s') ?? '-' }}</strong></div>
        <div><span>Autorizado em</span><strong>{{ optional($document->authorized_at)->format('d/m/Y H:i:s') ?? '-' }}</strong></div>
        <div><span>Cancelamento solicitado em</span><strong>{{ optional($document->cancellation_requested_at)->format('d/m/Y H:i:s') ?? '-' }}</strong></div>
        <div><span>Cancelado em</span><strong>{{ optional($document->cancelled_at)->format('d/m/Y H:i:s') ?? '-' }}</strong></div>
        <div><span>Último reenvio solicitado</span><strong>{{ optional($document->webhook_resend_requested_at)->format('d/m/Y H:i:s') ?? '-' }}</strong></div>
        <div><span>Protocolo</span><strong>{{ $document->protocol ?? '-' }}</strong></div>
      </div>

      @php($payloadRoot = $document->type === 'nfce' ? 'focus_nfce' : 'focus_nfe')
      @if($submissionAvailable && $document->status === 'draft' && is_array(data_get($document->request_payload, $payloadRoot.'.payload')))
        <form method="POST" action="{{ route('fiscal.documents.submit', $document->id) }}">
          @csrf
          <button type="submit">Enviar para a fila de homologação</button>
        </form>
      @elseif($submissionAvailable && in_array($document->status, ['processing', 'authorized', 'cancellation_queued', 'cancellation_failed', 'cancelled', 'failed'], true))
        <form method="POST" action="{{ route('fiscal.documents.synchronize', $document->id) }}">
          @csrf
          <button type="submit">Consultar e reconciliar com o provedor</button>
        </form>
      @elseif(!$submissionAvailable)
        <p class="muted">A fila e o webhook já estão preparados, mas permanecem inativos até a configuração segura do segredo e do resolvedor de credenciais.</p>
      @endif

      @if($document->cancellation_reason)
        <div class="alert warning">
          <strong>Justificativa congelada:</strong> {{ $document->cancellation_reason }}
        </div>
      @endif

      @if($submissionAvailable && $document->status === 'authorized')
        <div class="alert warning">
          <strong>Atenção:</strong> o cancelamento fiscal é definitivo. Confirme a justificativa antes de colocar o pedido na fila.
          @if($document->type === 'nfce') A janela operacional configurada para a NFC-e é de {{ config('fiscal.nfce.cancellation_window_minutes', 30) }} minutos após a autorização. @endif
        </div>
        <form method="POST" action="{{ route('fiscal.documents.cancel', $document->id) }}">
          @csrf
          <div class="field">
            <label for="cancellation_reason">Justificativa do cancelamento</label>
            <textarea id="cancellation_reason" name="cancellation_reason" minlength="15" maxlength="255" required>{{ old('cancellation_reason') }}</textarea>
            <small class="muted">Entre 15 e 255 caracteres. A justificativa não poderá ser alterada nas retentativas.</small>
          </div>
          <button type="submit" class="danger">Solicitar cancelamento fiscal</button>
        </form>
      @elseif($submissionAvailable && $document->status === 'cancellation_failed')
        <div class="alert danger">
          O cancelamento não foi confirmado. Confira primeiro a situação do documento no provedor; se ele continuar autorizado, tente novamente com a mesma justificativa.
        </div>
        <form method="POST" action="{{ route('fiscal.documents.cancel', $document->id) }}">
          @csrf
          <input type="hidden" name="cancellation_reason" value="{{ $document->cancellation_reason }}">
          <button type="submit">Tentar o mesmo cancelamento novamente</button>
        </form>
      @elseif($document->status === 'cancellation_queued')
        <div class="alert info">O pedido está na fila. O status fiscal permanece pendente até a confirmação do provedor.</div>
      @endif

      @if($webhookRecoveryAvailable && in_array($document->status, ['processing', 'authorized', 'cancellation_queued', 'cancellation_failed', 'cancelled'], true))
        <form method="POST" action="{{ route('fiscal.documents.webhook-resend', $document->id) }}">
          @csrf
          <button type="submit" class="secondary">Solicitar reenvio do webhook</button>
          <small class="muted">A Focus reenviará a notificação para todos os gatilhos cadastrados. O intervalo de segurança impede solicitações repetidas em sequência.</small>
        </form>
      @endif

      @if($submissionAvailable && in_array($document->status, ['authorized', 'cancelled'], true))
        <div class="actions">
          @if(filled(data_get($document->response_payload, 'caminho_xml_nota_fiscal')))
            <a class="button secondary" href="{{ route('fiscal.documents.artifact', [$document->id, 'xml']) }}">Baixar XML protegido</a>
          @endif
          @if(filled(data_get($document->response_payload, 'caminho_danfe')))
            <a class="button secondary" href="{{ route('fiscal.documents.artifact', [$document->id, 'danfe']) }}">Baixar {{ $document->type === 'nfce' ? 'DANFCe' : 'DANFSe' }} protegido</a>
          @endif
        </div>
      @endif

      @if($document->last_error_message)
        <div class="alert danger">{{ $document->last_error_message }}</div>
      @endif
    </div>
  </section>

  @if($document->provider === 'focus_nfe' && $document->type === 'nfse')
    @php($focusPayload = data_get($document->request_payload, 'focus_nfe'))
    <section class="panel">
      <div class="panel-heading">
        <div><h2>Payload Focus NFe</h2><p>DPS Nacional preparada localmente, sem transmissão externa.</p></div>
        <span class="badge {{ $focusPayload ? 'success' : 'warning' }}">{{ $focusPayload ? 'Gerado' : 'Pendente' }}</span>
      </div>
      <div class="panel-body">
        @if($focusPayload)
          <div class="receipt-info-grid">
            <div><span>Esquema</span><strong>{{ data_get($focusPayload, 'schema_version') }}</strong></div>
            <div><span>Série DPS</span><strong>{{ $document->series }}</strong></div>
            <div><span>Número DPS</span><strong>{{ $document->document_number }}</strong></div>
            <div><span>Gerado em</span><strong>{{ data_get($focusPayload, 'generated_at') }}</strong></div>
          </div>
          <p class="muted">O conteúdo integral não é exibido nesta tela porque contém dados fiscais e pessoais do tomador.</p>
        @else
          <div class="alert warning">A geração validará emitente, tomador, sequência, totais, ISS e campos IBS/CBS. Se estiver tudo correto, reservará o próximo número da DPS, mas não enviará nada à Focus.</div>
          <form method="POST" action="{{ route('fiscal.documents.payload', $document->id) }}">
            @csrf
            <button type="submit">Gerar payload de homologação</button>
          </form>
        @endif
      </div>
    </section>
  @endif

  @if($document->provider === 'focus_nfe' && $document->type === 'nfce')
    @php($nfcePayload = data_get($document->request_payload, 'focus_nfce'))
    <section class="panel">
      <div class="panel-heading">
        <div><h2>Payload NFC-e Focus NFe</h2><p>Modelo 65 do RJ preparado localmente para o ciclo controlado de homologação.</p></div>
        <span class="badge {{ $nfcePayload ? 'success' : 'warning' }}">{{ $nfcePayload ? 'Gerado' : 'Pendente' }}</span>
      </div>
      <div class="panel-body">
        @if($nfcePayload)
          <div class="receipt-info-grid">
            <div><span>Esquema</span><strong>{{ data_get($nfcePayload, 'schema_version') }}</strong></div>
            <div><span>Modelo</span><strong>{{ data_get($nfcePayload, 'model') }}</strong></div>
            <div><span>Série</span><strong>{{ $document->series }}</strong></div>
            <div><span>Número</span><strong>{{ $document->document_number }}</strong></div>
            <div><span>Gerado em</span><strong>{{ data_get($nfcePayload, 'generated_at') }}</strong></div>
          </div>
          @if($submissionAvailable)
            <div class="alert info"><strong>Pronto para a fila:</strong> o envio continuará restrito à homologação, renovará apenas o horário de emissão e usará a mesma referência idempotente.</div>
          @else
            <div class="alert warning"><strong>Transmissão bloqueada:</strong> o payload está pronto para revisão, mas os controles operacionais do servidor ou a conta Focus da clínica ainda não foram habilitados.</div>
          @endif
          <p class="muted">O conteúdo integral não é exibido porque contém dados fiscais, itens, pagamentos e eventual identificação do consumidor.</p>
        @else
          <div class="alert warning">A geração validará emitente RJ, credenciamento, referência externa do CSC, certificado, sequência própria, modelo 65, produtos, IBS/CBS, totais e pagamentos. Se tudo fechar, reservará o próximo número sem chamar a Focus ou a SEFAZ.</div>
          <form method="POST" action="{{ route('fiscal.documents.payload', $document->id) }}">
            @csrf
            <button type="submit">Gerar payload NFC-e de homologação</button>
          </form>
        @endif
      </div>
    </section>
  @endif

  <section class="panel">
    <div class="panel-heading"><div><h2>Emitente congelado</h2><p>Snapshot preservado no momento da preparação.</p></div></div>
    <div class="panel-body receipt-info-grid">
      <div><span>Razão social</span><strong>{{ $issuer['corporate_name'] ?? '-' }}</strong></div>
      <div><span>Nome fantasia</span><strong>{{ $issuer['trade_name'] ?? '-' }}</strong></div>
      <div><span>CPF/CNPJ</span><strong>{{ $issuer['document'] ?? '-' }}</strong></div>
      <div><span>Inscrição estadual</span><strong>{{ $issuer['state_registration'] ?? '-' }}</strong></div>
      <div><span>Inscrição municipal</span><strong>{{ $issuer['municipal_registration'] ?? '-' }}</strong></div>
      <div><span>Município IBGE</span><strong>{{ $issuer['municipality_code'] ?? '-' }}</strong></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Destinatário</h2><p>Cliente associado à venda no momento da preparação.</p></div></div>
    <div class="panel-body receipt-info-grid">
      <div><span>Nome</span><strong>{{ $recipient['name'] ?? 'Consumidor não identificado' }}</strong></div>
      <div><span>CPF/CNPJ</span><strong>{{ $recipient['document'] ?? '-' }}</strong></div>
      <div><span>E-mail</span><strong>{{ $recipient['email'] ?? '-' }}</strong></div>
      <div><span>Telefone</span><strong>{{ $recipient['phone'] ?? '-' }}</strong></div>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Itens congelados</h2><p>Estes dados não acompanham alterações posteriores nos cadastros.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Descrição</th><th>Tipo</th><th>Classificação</th><th>Quantidade</th><th>Valor unitário</th><th>Desconto</th><th>Total</th></tr></thead>
        <tbody>
          @foreach($document->items_snapshot as $item)
            <tr>
              <td>{{ $item['description'] }}</td>
              <td>{{ $item['type'] === 'service' ? 'Serviço' : ($item['type'] === 'product' ? 'Produto' : 'Avulso') }}</td>
              <td>
                @if($item['type'] === 'product')
                  NCM {{ $item['fiscal']['ncm'] ?? '-' }} · CFOP {{ $item['fiscal']['default_cfop'] ?? '-' }}
                @else
                  {{ $item['fiscal']['national_taxation_code'] ?? '-' }} · ISS {{ isset($item['fiscal']['iss_rate']) ? number_format((float) $item['fiscal']['iss_rate'], 2, ',', '.').'%' : '-' }}
                @endif
                <div class="muted">Versão {{ $item['fiscal_profile_version'] ?? '-' }}</div>
              </td>
              <td>{{ number_format((float) $item['quantity'], 3, ',', '.') }}</td>
              <td>{{ $money($item['unit_price']) }}</td>
              <td>{{ $money($item['discount_total']) }}</td>
              <td>{{ $money($item['total']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-heading"><div><h2>Histórico imutável</h2><p>Eventos registrados para esta preparação fiscal.</p></div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Evento</th><th>De</th><th>Para</th><th>Responsável</th><th>Data</th></tr></thead>
        <tbody>
          @foreach($document->events as $event)
            <tr>
              <td>{{ $eventLabels[$event->event] ?? ucfirst(str_replace('_', ' ', $event->event)) }}</td>
              <td>{{ $event->from_status ? ($statusLabels[$event->from_status] ?? $event->from_status) : '-' }}</td>
              <td>{{ $event->to_status ? ($statusLabels[$event->to_status] ?? $event->to_status) : '-' }}</td>
              <td>{{ $event->actor?->name ?? 'Sistema' }}</td>
              <td>{{ optional($event->occurred_at)->format('d/m/Y H:i:s') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endsection
