# Fiscal Provider Decision

Date: 2026-10-10

## Decision

VetFlow will use Focus NFe as its first fiscal-provider adapter. The first
integration slice started with NFS-e Nacional for the Niterói/RJ pilot and now
also covers RJ NFC-e model 65 in the provider's homologation environment.

This decision selects an integration boundary; it does not authorize external
traffic or claim that fiscal issuance is ready.

The commercial boundary is also explicit: each clinic contracts and pays for
its own Focus account. VetFlow connects that tenant-owned account and does not
bundle a provider subscription. A centrally managed commercial plan may be
added later without changing the document/provider boundary.

## Comparison

| Criterion | Focus NFe | PlugNotas / TecnoSpeed | Nuvem Fiscal |
| --- | --- | --- | --- |
| NFS-e Nacional | Documented | Documented | Previously offered |
| NFC-e model 65 | Documented | Documented | Previously offered |
| Homologation | Dedicated real environment | Sandbox is mocked; contracted API is needed for actual homologation | Not applicable to a new integration |
| Authentication | HTTP Basic with API token | API-specific contract | Not applicable |
| Multiple issuers | Documented | Documented | Not applicable |
| Public entry pricing | Published | Quote/contract path | Not applicable |
| Current availability | Active | Active | Official site states the service was disabled on 2026-07-31 |

Focus is the best fit for the first adapter because the public documentation
covers the required REST lifecycle, National and municipal NFS-e, webhooks,
multiple CNPJs, authentication and a usable homologation host. PlugNotas stays
as a future alternative if a commercial or municipality-coverage requirement
justifies it. Nuvem Fiscal is excluded from new adoption because its official
site announces that the service has been disabled.

## Implemented Boundary

- A clinic can connect its own Focus homologation token through a password-
  confirmed form. VetFlow encrypts it at rest, saves only an opaque reference
  in the fiscal profile and never renders the complete value again.
- `FocusNfeFiscalProvider` knows the documented NFS-e Nacional and NFC-e
  submit, consult and cancel paths plus NFC-e range consultation/inutilization.
- Requests are restricted to homologation, Niterói (`3303302`) for NFS-e or
  an RJ issuer for NFC-e, and a pre-reviewed provider payload.
- A local generator validates the frozen fiscal data and 2026 IBS/CBS fields,
  reserves the clinic/series DPS number transactionally and stores an
  idempotent versioned payload without making an HTTP request.
- Provider responses pass through an explicit allowlist before they can reach
  application state.
- A dedicated queue moves documents through queued and processing states with
  bounded retries, overlap locks, stable provider references and delayed
  status reconciliation.
- NFC-e emission refreshes only its short-lived timestamp at the start of an
  attempt. A reference conflict is reconciled by GET before failure, and a
  communication-failed document remains manually consultable.
- NFC-e inutilization has its own confirmed queue record. It locks the
  sequence, refuses future/used/overlapping ranges, consults for an existing
  exact authorized range before POST, and identity-checks the provider result.
- An authenticated, size-bounded Focus webhook reconciles allowlisted results,
  checks the issuer CNPJ and ignores exact replays without duplicating events.
- The global HTTP switch is off by default.
- Submission and webhook switches are independently off by default.
- The application resolves `FiscalProvider` to `DisabledFiscalProvider` unless
  both the Focus adapter and a reviewed credential driver are explicitly
  selected in the deployment environment.
- The `encrypted_database` driver supports customer-owned accounts with one
  clinic-scoped active credential, audited rotation and revocation that erases
  the secret. The external-file driver remains available for operations-
  managed installations.
- The external-file resolver reads a versioned store outside the application,
  matches the frozen reference/provider/environment exactly and supports token
  rotation without storing token, password or certificate content in VetFlow.
- A privacy-safe runtime panel requires adapter, credential store, clinic
  reference, asynchronous queue, outbound HTTP and submission gates. The
  authenticated webhook is an additional NFS-e recovery control and is not
  used by NFC-e.

## Work Required Before The First Homologation Call

1. Confirm the clinic's tax regime, product/service taxation, SEFAZ-RJ
   credentialing and CSC setup with its accountant and fiscal operator.
2. Have the clinic's accountant review the generated versioned Focus payload,
   including ISS and the 2026 IBS/CBS fields, using real homologation data.
3. Have the clinic provision its own Focus test account, issuer and certificate
   outside the source repository.
4. Enable the encrypted credential driver and have an authorized clinic user
   connect the homologation token through the protected VetFlow form.
5. Register the authenticated NFS-e callback and validate queued submission,
   rejection, authorization, cancellation, delayed reconciliation and one safe
   NFC-e inutilization with the provider's real homologation account.
6. Review that evidence before enabling the guarded runtime switches.
7. Design a separate, explicit production release gate. The current adapter
   rejects production documents.

The supervised activation, rotation and rollback sequence is defined in
[`docs/deployment/fiscal-homologation.md`](../deployment/fiscal-homologation.md).

## Official Sources Reviewed

- Focus NFe introduction: <https://doc.focusnfe.com.br/reference/introducao>
- Focus NFe environments: <https://doc.focusnfe.com.br/reference/ambiente>
- Focus NFe authentication: <https://doc.focusnfe.com.br/reference/autenticacao>
- Focus NFe NFS-e Nacional submission:
  <https://doc.focusnfe.com.br/reference/emitir_dps_nacional>
- Focus NFe NFS-e Nacional consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfse_nacional>
- Focus NFe NFS-e Nacional cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfse_nacional>
- Focus NFe NFC-e emission:
  <https://doc.focusnfe.com.br/reference/emitir_nfce>
- Focus NFe NFC-e consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfce>
- Focus NFe NFC-e cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfce>
- Focus NFe NFC-e range inutilization and consultation:
  <https://doc.focusnfe.com.br/reference/inutilizar_numeracao_nfce>
  and <https://doc.focusnfe.com.br/reference/consultar_inutilizacoes_nfce>
- Focus NFe webhooks: <https://doc.focusnfe.com.br/reference/webhooks>
- Focus NFe webhook creation:
  <https://doc.focusnfe.com.br/reference/criar_webhook>
- Focus NFe public pricing: <https://2025.focusnfe.com.br/precos/>
- PlugNotas first steps:
  <https://atendimento.tecnospeed.com.br/hc/pt-br/articles/23715383551767-Primeiros-Passos-com-o-Plugnotas>
- PlugNotas sandbox and actual homologation:
  <https://atendimento.tecnospeed.com.br/hc/pt-br/articles/360055363553-Entendendo-os-envios-para-o-PlugNotas>
- PlugNotas municipality homologation:
  <https://atendimento.tecnospeed.com.br/hc/pt-br/articles/360015681773-Como-solicitar-homologa%C3%A7%C3%A3o-de-munic%C3%ADpios-no-PlugNotas>
- Nuvem Fiscal discontinuation notice: <https://www.nuvemfiscal.com.br/form-sub/>
