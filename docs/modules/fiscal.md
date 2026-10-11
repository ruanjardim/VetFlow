# Fiscal

## Purpose

The Fiscal module introduces the tenant-safe foundation for outgoing Brazilian
fiscal documents. It prepares and reviews NFC-e and NFS-e data and provides a
local RJ NFC-e model 65 payload plus guarded Focus NFS-e and NFC-e
homologation lifecycles. Every external control is disabled by default and
production issuance remains unavailable.

Focus follows a bring-your-own-account model: each clinic contracts and pays
the provider directly. VetFlow only connects that tenant-owned account and the
PDV/non-fiscal receipt continues to work without it.

The safety boundary is deliberate: completing a sale never creates or sends a
fiscal document automatically.

## Current Workflow

1. An authorized user opens `Fiscal` under the Sales menu.
2. The clinic saves a homologation-only profile and chooses whether NFC-e,
   NFS-e, or both may be prepared.
3. An authorized operator can connect the clinic's own Focus homologation token
   after confirming the current VetFlow password and commercial responsibility.
   The token is encrypted and never displayed again.
4. VetFlow resolves the reviewed municipal integration route from the IBGE
   code and reports the issuer/certificate fields still missing for integration.
5. The clinic reviews the active product and service catalogs and fills their
   minimum tax classification. The readiness counters distinguish complete
   records from pending records without claiming accounting approval.
6. From the receipt of a completed sale, the user explicitly prepares one
   document type.
7. VetFlow creates an idempotent draft containing immutable issuer, recipient,
   item, payment, and total snapshots.
8. The detail page displays the snapshot and its append-only preparation event.
9. When the sale mixes products and services or has a sale-level adjustment,
   the operator prepares every required document, reviews the exact-cent split
   and explicitly approves it. VetFlow proportionally divides discount,
   additions and payment methods by the net line values, places delivery and
   change on NFC-e when products exist, reconciles the document totals to the
   sale and freezes one shared allocation before any payload is allowed.
10. For a Focus/Niterói NFS-e whose fiscal fields and totals are complete, the
   user can explicitly generate an immutable homologation payload. This action
   reserves the next DPS number transactionally and appends an event, but never
   calls the provider.
11. For a Focus/RJ NFC-e, the operator must also complete the SEFAZ-RJ,
   certificate, CSC-reference, independent series/number and Pix-type checks.
   VetFlow validates every product, payment, troco and 2026 IBS/CBS code before
   reserving one model 65 number and storing a local payload. The CSC secret is
   never accepted or persisted.
12. The operational lifecycle can place a reviewed Niterói NFS-e or RJ NFC-e
   in a dedicated queue, submit it with the stable `vf-{ulid}` reference and
   reconcile provider status asynchronously. NFC-e refreshes only its
   short-lived emission timestamp immediately before each attempt. The
   authenticated Focus webhook remains limited to NFS-e recovery.
13. After provider authorization, an operator can queue one definitive
    cancellation with a 15-to-255-character justification. Retried jobs retain
    that exact justification, and VetFlow changes the document to cancelled
    only after a provider confirmation or authenticated webhook.
14. An operator can reconcile provider status after authorization or a failed
    cancellation, request a bounded resend to all registered Focus webhook
    triggers, and download provider-returned XML/DANFSe paths through an
    authenticated VetFlow proxy without exposing provider URLs or credentials.
15. A separately confirmed NFC-e action can queue an already-passed numbering
    gap for synchronous SEFAZ inutilization. Future numbers, ranges containing
    any prepared VetFlow NFC-e and overlaps with active/authorized requests are
    rejected before the queue is touched.
16. The Fiscal center consolidates an evidence gate per clinic. Each enabled
    type must prove complete setup and tax catalog, authorization, controlled
    rejection, cancellation, status reconciliation, XML and auxiliary-document
    download. NFS-e additionally proves authenticated webhook receipt and a
    completed resend; enabling both types also requires a reviewed mixed-sale
    allocation.
17. An authorized user records an append-only approval or follow-up after
    confirming the current password. The decision is bound to a secret-free
    fingerprint and becomes stale when profile or active-catalog evidence
    changes. Approval only qualifies a future technical production review and
    cannot activate production.
18. The clinic can print an actionable homologation dossier or download its
    no-cache JSON equivalent. Both use an explicit safe field list, include a
    report hash and omit credential/certificate references, fiscal payloads,
    document identifiers and internal evidence fingerprints.
19. The daily reconciliation compares completed sales with the latest
    preparation for every applicable document type. It separates unmapped or
    returned sales needing human review, disabled configuration, missing
    preparation, unapproved rateio, missing payload, pending submission,
    provider processing and closed authorization. Filters cover at most 31
    days and can be exported as a tenant-safe, formula-safe CSV. This is an
    operational checklist, not an automatic conclusion about tax obligations.
20. A reconciliation period of up to 31 days can receive an internal closing
    only when every sale is authorized or VetFlow found no internal movement.
    The operator confirms the current password and review; VetFlow stores an
    immutable evidence snapshot and audit event. Duplicate current closures are
    blocked, while later sale, document or profile changes make the previous
    record visibly stale and allow a new confirmation after all pending states
    are resolved. This record is not a tax filing or accounting certification.
    Every historical closure has a printable dossier and private no-cache JSON
    export preserving its frozen status totals, sales and document states,
    current/stale result and integrity hash without provider secrets or payloads.
    A monthly history center filters these records by clinic, competence,
    responsible person and dynamically recalculated current/stale state.
21. A partial refund or fully returned sale stays in the reconciliation and
    opens an assisted fiscal review. The operator inspects returned items,
    values, events and linked documents, identifies the source of the guidance,
    describes the instructed next step and records either follow-up or review
    completion after password confirmation. Every event is append-only and its
    safe fingerprint becomes stale after any evidence change. A current
    completed review can close the operational pending state but never causes
    VetFlow to prescribe, generate, transmit or cancel a fiscal document. Each
    historical review exposes its own printable dossier and private no-cache
    JSON export, preserving the original evidence, current/stale state and a
    report hash without including provider payloads or credentials.
22. The document center groups prepared NFC-e and NFS-e by operational
    competence, defined explicitly as the preparation creation month. It
    combines clinic-safe filters for type, lifecycle status, sale and a bounded
    date range inside that month, keeps pagination on screen and exports the
    complete filtered result as a UTF-8, formula-safe, private no-cache CSV.
    The export allowlist includes only conference identifiers, dates, totals
    and responsible user; raw payloads, provider responses and credentials are
    never returned. Operational competence does not define tax competence.

Repeating the same preparation for the same sale and document type returns the
existing draft instead of duplicating it.

## Routes And Access

The module is protected by `fiscal.manage` and the existing `pdv` subscription
feature.

- `GET /fiscal`: profile and prepared-document list.
- `GET /fiscal/documents`: search the clinic-scoped monthly document center by
  competence, bounded period, NFC-e/NFS-e, status and sale.
- `GET /fiscal/documents.csv`: export the complete filtered conference list as
  a private no-cache, formula-safe CSV without raw provider data.
- `PUT /fiscal/settings`: update the homologation profile.
- `POST /fiscal/focus-credential`: connect or rotate the clinic-owned Focus
  homologation token after password confirmation.
- `DELETE /fiscal/focus-credential`: erase and revoke the clinic credential,
  blocking its external fiscal operations.
- `POST /fiscal/homologation/reviews`: append a password-confirmed approval or
  follow-up bound to the current secret-free homologation evidence.
- `GET /fiscal/homologation/report`: print the current actionable dossier.
- `GET /fiscal/homologation/report.json`: download the same allowlisted report
  with private no-cache headers.
- `GET /fiscal/reconciliation`: compare completed sales with their latest
  applicable NFC-e/NFS-e preparations for one clinic and bounded period.
- `GET /fiscal/reconciliation.csv`: export the same filtered operational view
  as a private no-cache, formula-safe CSV.
- `POST /fiscal/reconciliation/closures`: record a password-confirmed,
  evidence-bound internal closing for a fully reconciled period.
- `GET /fiscal/reconciliation/closures`: search the monthly tenant-safe closing
  history by clinic, responsible person and current/stale state.
- `GET /fiscal/reconciliation/closures/{closure}/dossier`: print the frozen
  evidence of one historical operational closing.
- `GET /fiscal/reconciliation/closures/{closure}/dossier.json`: download the
  same secret-free, integrity-hashed closing report with private no-cache headers.
- `GET /fiscal/returns/{sale}`: inspect the current return/refund evidence and
  its append-only human-review history.
- `POST /fiscal/returns/{sale}/reviews`: record a password-confirmed follow-up
  or completed review bound to the current evidence.
- `GET /fiscal/returns/{sale}/reviews/{review}/dossier`: print the frozen
  evidence and human decision for one historical review.
- `GET /fiscal/returns/{sale}/reviews/{review}/dossier.json`: download the same
  secret-free, integrity-hashed report with private no-cache headers.
- `POST /fiscal/sales/{sale}/prepare`: manually prepare NFC-e or NFS-e.
- `POST /fiscal/sales/{sale}/allocation/approve`: confirm and freeze the
  reviewed allocation shared by every required document for the sale.
- `GET /fiscal/documents/{document}`: inspect the frozen snapshot and events.
- `POST /fiscal/documents/{document}/payload`: validate and generate a local
  Focus NFS-e Nacional or RJ NFC-e homologation payload without transmitting
  it. Both reviewed payload types can later enter their homologation lifecycle.
- `POST /fiscal/documents/{document}/submit`: place a reviewed payload in the
  homologation transmission queue when operational enablement is active.
- `POST /fiscal/documents/{document}/synchronize`: place a manual provider
  status consultation in the same queue while a document is processing.
- `POST /fiscal/documents/{document}/cancel`: queue cancellation of an
  authorized document. The first justification is frozen for every retry.
- `POST /fiscal/nfce/inutilizations`: queue a confirmed, already-passed and
  unused model-65 number range for SEFAZ inutilization in homologation.
- `POST /fiscal/documents/{document}/webhook-resend`: queue a provider request
  to resend the document notification to registered hooks, with a cooldown.
- `GET /fiscal/documents/{document}/artifacts/{xml|danfe|danfse}`: download an
  allowlisted provider artifact through VetFlow with no-cache headers.
- `POST /webhooks/focus-nfe/nfsen`: public Focus callback protected by a
  deployment-defined authorization header, size limit and issuer check. It
  behaves as not found while disabled.
- `GET /fiscal/products`: list product tax-readiness and pending records.
- `GET /fiscal/products/{product}/edit`: edit the product's versioned tax
  classification.
- `GET /fiscal/services`: list service tax-readiness and pending records.
- `GET /fiscal/services/{service}/edit`: edit the service's versioned tax
  classification.

The standard administrator, cashier, and financial role presets receive the
permission. All profile, document, and event records are clinic-scoped.

## Owned Tables

- `fiscal_profiles`: one versioned fiscal configuration per clinic.
- `fiscal_provider_credentials`: clinic-owned provider credentials encrypted at
  rest, hidden from serialization and revocable without altering history.
- `fiscal_documents`: prepared document lifecycle and immutable business
  snapshots.
- `fiscal_sale_allocations`: one approved, versioned and fingerprinted source
  and allocation snapshot per sale, linked to every affected document.
- `fiscal_homologation_reviews`: append-only human decisions with reviewer,
  safe evidence snapshot and fingerprint for current/stale detection.
- `fiscal_period_closures`: append-only operational period confirmations with
  reviewer, totals, safe evidence snapshot and fingerprint for stale detection.
- `fiscal_return_reviews`: append-only follow-up/completion decisions with
  guidance source, resolution summary and a safe return-evidence fingerprint.
- `fiscal_document_events`: attributed append-only lifecycle history.
- `fiscal_dps_sequences`: clinic/provider/environment/series-scoped DPS
  sequence with the next and last reserved numbers.
- `fiscal_nfce_sequences`: independent clinic/provider/environment/series-
  scoped model 65 sequence with the next and last reserved numbers.
- `fiscal_nfce_inutilizations`: immutable request range, issuer/credential
  reference snapshot, allowlisted provider response and final processing state.
- `product_fiscal_profiles`: versioned NCM, CEST, origin, default CFOP,
  CST/CSOSN, PIS/COFINS, fiscal unit, benefit and IBS/CBS references.
- `service_fiscal_profiles`: versioned national/municipal taxation codes,
  service-list item, CNAE, NBS, incidence municipality, ISS and IBS/CBS
  references.

Certificates and their passwords are not stored. In customer-managed mode the
Focus token is stored only in the dedicated encrypted credential record; the
profile and fiscal documents receive an opaque reference. The complete token
is absent from responses, snapshots, events, logs and audit metadata.

`ready` means only that the minimum fields for a homologation preparation are
present. It is not an accounting approval and does not prove that a code or tax
rate is legally correct.

Product preparations accept only catalog-linked product lines with a complete
product fiscal profile. Service preparations apply the equivalent rule to
catalog-linked services. Free-form sale lines are blocked. A mixed sale can be
split into NFC-e and NFS-e preparations. Until all required drafts exist and an
operator approves the review, each snapshot is marked as requiring allocation
and payload generation fails closed.

The allocation calculation uses integer cents throughout. Sale-level discount
and additions are proportional to the net value of each line; delivery belongs
to NFC-e when the sale has products and otherwise to NFS-e; payments are
proportional to each final document total; and all cash change belongs to NFC-e
when present. Largest-remainder rounding makes every cent deterministic. The
approval verifies that the sale, prepared lines, payments and totals still
match, then fingerprints the source, updates only draft snapshots and appends
an event to each document. A repeated approval returns the same record rather
than recalculating it.

Product readiness now requires the explicit three-digit IBS/CBS CST and
six-digit classification in addition to the classic NCM/ICMS/PIS/COFINS
fields. Service readiness for the Focus payload also requires the explicit ISS
taxation code plus the three-digit IBS/CBS CST and six-digit IBS/CBS tax
classification. These values are entered by the clinic/accountant; VetFlow
does not infer them from the service name, CNAE or tax regime.

## Municipality Coverage

Municipality behavior is resolved from a catalog instead of being embedded in
the sale or controller. This allows VetFlow to add reviewed cities without
changing previously prepared snapshots.

Niterói/RJ is the first reviewed profile:

- IBGE code: `3303302`;
- NFS-e route: National NFS-e Emissor using DPS;
- effective date: 2026-01-01;
- test environment: National NFS-e restricted production;
- current layout reference: `1.01`;
- multiple service lines in one preparation must share the same service-list
  subitem and ISS rate. The sale already defines a single recipient, and every
  service remains itemized in the snapshot.

If the clinic address is Niterói/RJ and no profile has been saved yet, the IBGE
code is suggested without persisting a record. Any other municipality remains
available for internal draft preparation, but is explicitly shown as not yet
reviewed for integration.

The readiness checklist covers municipality support, provider selection,
external provider-credential reference, a valid-length CNPJ, municipal
registration, tax regime, CNAE, external certificate reference and certificate
validity, Focus tax-regime selections and the DPS sequence. Completing the
checklist still does not enable traffic.

## RJ NFC-e Coverage

The first NFC-e slice is deliberately narrower than the internal draft model:

- issuer in RJ, model 65 and homologation only;
- CNPJ, state registration, municipality/address, tax regime and a current
  externally referenced certificate;
- explicit confirmation that the establishment is credentialed for NFC-e at
  SEFAZ-RJ;
- public six-digit CSC identifier and only an opaque reference to the CSC
  provisioned in Focus or a secret store;
- a dedicated series (`0` for a single series) and sequence from `1` through
  `999999999`, never shared with NF-e model 55 or the NFS-e DPS;
- product-only documents with NCM, CFOP, origin, CST/CSOSN, PIS, COFINS,
  fiscal unit and IBS/CBS status/classification frozen in each item;
- consumer-final, internal RJ operation in this slice, with delivery address
  required for home delivery;
- explicit payment codes. Pix must be selected as dynamic (`17`) or static
  (`20`); unsupported methods fail closed. Partial payment is represented as
  payment later (`91`) rather than guessed, and cash change must reconcile.

The generator stores `focus-nfce-rj-2026-10`, is idempotent and reserves a
number only after all validations pass. Generation itself never resolves Focus
credentials or sends HTTP. When the independently disabled runtime gates are
explicitly enabled, the lifecycle can send the reviewed payload to `/v2/nfce`,
consult the same reference, map authorized/rejected/denied results, cancel an
authorized note within the configured 30-minute operational window, and proxy
the returned XML/DANFCe. The emission timestamp is refreshed and persisted
immediately before an attempt because Focus accepts at most a five-minute
difference.

Inutilization uses its own queued record and the synchronous
`/v2/nfce/inutilizacao` operation. It never advances or rewinds the sequence.
The requested range must end below `next_number`, contain no number already
attached to a VetFlow fiscal document and not overlap an active or authorized
request. Responses are issuer/model/series/range checked before authorization
is recorded. Because this Focus operation has no client reference, the adapter
consults `/v2/nfce/inutilizacoes` for the exact CNPJ/range before submission
and after an ambiguous conflict, avoiding a blind duplicate request.
Once a range is queued or authorized, sequence configuration and number
reservation also refuse to re-enter it.

## Integration Boundary

`FiscalProvider` defines submit, status, cancellation and NFC-e inutilization
operations. Focus NFe
is the first selected adapter because it documents NFS-e Nacional, a real
homologation base URL, REST/JSON, webhooks and multiple companies. The current
concrete adapter is deliberately narrow: NFS-e Nacional for Niterói and model
65 for an RJ issuer, only in homologation and only for already generated and
reviewed Focus payloads.

`FocusNfePayloadService` generates that local payload only after validating the
frozen issuer and recipient, a single compatible tax classification, closed
gross/discount/net totals, sequence availability and the required 2026
IBS/CBS fields. It reserves a DPS number once and returns the same stored
payload on retries. Payload generation never resolves provider credentials or
makes an HTTP request.

`FocusNfcePayloadService` is a separate model 65 generator. It produces the
document/item/payment field names documented by Focus, keeps CSC material out
of the payload and persistence, and uses `FiscalNfceSequenceService` so NFC-e
numbering cannot collide with a DPS. The guarded lifecycle, rather than the
generator, owns the synchronous `/v2/nfce` operation. Approved global discount,
delivery and additions are represented as item and document discount, freight
and other-expense values. The NFS-e generator consolidates approved service
additions into `valor_servico` and retains the approved discount separately.

`FiscalDocumentLifecycleService` owns the allowed state transitions. A queued
submission changes state exactly once, provider retries reuse the same
reference, and repeated identical polling or webhook responses do not append
duplicate events. Provider response storage is allowlisted. Job failures keep
only a generic message and exception class, never a provider body or token.
Processing documents receive bounded delayed reconciliation checks, and an
authorized document can still advance to cancelled without regressing to an
earlier state.

Cancellation has its own queued and failed states. A repeated click cannot
dispatch a duplicate job, a failed request can be retried only with the frozen
justification, and the provider's synchronous cancellation result is treated
as definitive. An ambiguous or negative result remains visibly failed so an
operator can verify the provider before retrying. The Fiscal center also shows
tenant-scoped alerts for exhausted submissions/cancellations/status checks and
for processing or cancellation queues older than the configurable thresholds.

Manual consultation also works after authorization and for cancellation
recovery, and it can recover a document marked as communication-failed when
the stable provider reference was actually authorized. An `autorizado` result
never regresses a failed cancellation; instead
it explicitly records that cancellation is still unconfirmed, while a
`cancelado` result closes it. Webhook resend is asynchronous, auditable and
rate-limited locally. It stores no provider response body because the Focus
operation only schedules notifications for registered triggers.

Authorized XML and DANFSe/DANFCe remain at the provider. VetFlow proxies downloads
only from relative paths already present in an allowlisted provider response.
It refuses traversal and absolute stored URLs, follows at most one HTTPS
redirect to an exact configured host, omits Focus authorization on that second
request, validates content, limits size, disables browser caching and records
only artifact type/size in the event trail. File contents and provider paths
are never persisted again or rendered in the interface.

The Focus callback accepts only JSON up to the configured limit, compares its
secret in constant time, looks up the exact provider reference, and rejects a
different issuer CNPJ. The authorization token lives only in the deployment
environment. It is not stored in fiscal documents, events, logs, or views.

The application container binds `DisabledFiscalProvider` and an unavailable
credential resolver by default. A deployment can select the Focus adapter and
either the customer-managed `encrypted_database` driver or the operations-
managed `external_file` driver, but outbound HTTP, submission and webhook
switches still default to disabled. Selecting Focus in the UI changes none of
those server controls.

`EncryptedDatabaseFiscalCredentialResolver` matches clinic, opaque reference,
provider and environment before Laravel decrypts a token. Connecting or
rotating requires the operator's current password; disconnecting nulls the
secret, revokes the reference and prevents old frozen documents from resolving
it. Secret-free audit events record all three actions.

`ExternalFileFiscalCredentialResolver` reads a small versioned JSON store at
runtime from an absolute path outside the repository. It resolves the exact
opaque reference frozen in the document snapshot and checks provider plus
environment before returning a token to the adapter. It rejects application-
local, unreadable, malformed or oversized files and malformed tokens. This
allows atomic rotation without database secret storage or config-cached token
values. The Fiscal center exposes a seven-control readiness panel containing
booleans and labels only.

The provider comparison and safety decision are recorded in
[`docs/architecture/fiscal-provider-decision.md`](../architecture/fiscal-provider-decision.md).
The guarded deployment sequence is documented in
[`docs/deployment/fiscal-homologation.md`](../deployment/fiscal-homologation.md).

The homologation gate now makes the remaining clinic work explicit rather than
assuming it happened. Its checks are satisfied only by the enabled type's
current configuration/catalog and recorded provider events for authorization,
controlled rejection, cancellation, reconciliation and protected artifacts.
NFS-e also requires the authenticated webhook and a completed resend. The
human review records the clinic/accountant conclusion, but the evidence still
has to be produced with the clinic's real homologation account.

Explicit production enablement, production credentials and a production
lifecycle remain intentionally absent. They must be designed as a separate
fail-closed change after the homologation gate is current and approved; editing
the profile or recording a review can never cross that boundary.

## Official References

- NFS-e national technical documentation:
  <https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/documentacao-atual>
- NFS-e restricted-production documentation:
  <https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita>
- NFS-e RTC documentation and evolving IBS/CBS domains:
  <https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc>
- Niterói transition to National NFS-e from 2026-01-01:
  <https://www.fazenda.niteroi.rj.gov.br/site/nfse-nacional/>
- Official restricted-production and production API directory:
  <https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/apis-prod-restrita-e-producao>
- NFC-e technical documents and schemas:
  <https://dfe-portal.svrs.rs.gov.br/Nfce/Documentos>
- SEFAZ-RJ NFC-e requirements, CSC, series and numbering:
  <https://portal.fazenda.rj.gov.br/dfe/wp-content/uploads/sites/17/2023/01/DF-e_NFC-e.pdf>
- Focus NFe introduction and supported document types:
  <https://doc.focusnfe.com.br/reference/introducao>
- Focus NFe environments and authentication:
  <https://doc.focusnfe.com.br/reference/ambiente>
- Focus NFe NFC-e synchronous emission contract:
  <https://doc.focusnfe.com.br/reference/emitir_nfce>
- Focus NFe NFC-e consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfce>
- Focus NFe NFC-e cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfce>
- Focus NFe NFC-e numbering inutilization:
  <https://doc.focusnfe.com.br/reference/inutilizar_numeracao_nfce>
- Focus NFe NFC-e inutilization consultation:
  <https://doc.focusnfe.com.br/reference/consultar_inutilizacoes_nfce>
- Focus NFe company/CSC homologation fields:
  <https://doc.focusnfe.com.br/reference/atualizar_empresa>
- Focus NFe item field reference:
  <https://campos.focusnfe.com.br/nfe/ItemNotaFiscalXML.html>
- Focus NFe payment field reference:
  <https://campos.focusnfe.com.br/nfe/FormaPagamentoXML.html>
- Focus NFe NFS-e Nacional submission:
  <https://doc.focusnfe.com.br/reference/emitir_dps_nacional>
- Focus NFe NFS-e Nacional consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfse_nacional>
- Focus NFe NFS-e Nacional cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfse_nacional>
- Focus NFe NFS-e Nacional webhook resend:
  <https://doc.focusnfe.com.br/reference/reenviar_hook_nfsen>
- Focus NFe NFS-e Nacional XML download contract:
  <https://doc.focusnfe.com.br/reference/consultar_nfsen_recebida_individual_xml>
- Focus NFe NFS-e Nacional DANFSe PDF download contract:
  <https://doc.focusnfe.com.br/reference/consultar_nfsen_recebida_individual_pdf>
- Focus NFe webhooks and custom authorization header:
  <https://doc.focusnfe.com.br/reference/webhooks>
- Focus NFe webhook creation:
  <https://doc.focusnfe.com.br/reference/criar_webhook>

Layouts change over time, including the 2026 IBS/CBS transition. Provider
payloads therefore must remain versioned and cannot be inferred from sales
fields without a reviewed tax configuration.

## Tests

`tests/Feature/FiscalFoundationTest.php` covers permission enforcement,
disabled defaults, homologation-only configuration, audit history, manual
preparation, frozen snapshots, absence of submission, idempotency, clinic
isolation, standard role assignment, Niterói route inference, its NFS-e
service grouping rule, and the reviewed mixed-sale allocation workflow.

`tests/Feature/FiscalHomologationReadinessTest.php` covers incomplete-gate
follow-up, guided dossier rendering, safe JSON output, premature-approval
blocking, complete NFC-e evidence, human approval without production
enablement and automatic staleness after tax configuration changes.

`tests/Feature/FiscalReconciliationTest.php` covers all reconciliation states,
closed-total validation, explicit return review, tenant isolation, status
filtering, the 31-day limit and formula-safe no-cache CSV export.

`tests/Feature/FiscalDocumentCenterTest.php` covers operational competence,
combined type/status/sale/date filters, tenant isolation, month boundaries and
the private formula-safe CSV allowlist that excludes raw fiscal payloads.

`tests/Feature/FiscalPeriodClosingTest.php` covers pending-state blocking,
password-confirmed creation, duplicate protection, immutable evidence, audit
history, stale detection, re-closing, bounded dates, tenant isolation,
historical printable dossiers, safe no-cache JSON exports and monthly history
filters by responsible person and current/stale state.

`tests/Feature/FiscalReturnReviewTest.php` covers partial and full returns,
follow-up and completed decisions, closing integration, immutable audit history,
stale detection after another return, password protection, tenant isolation,
historical printable dossiers and safe no-cache JSON exports.

`tests/Unit/FiscalSaleAllocationCalculatorTest.php` covers proportional
discount/addition/payment splits, deterministic cent remainders, change,
inconsistent-sale rejection and overflow-safe large-value arithmetic.

`tests/Feature/FiscalTaxCatalogTest.php` covers readiness counters, product and
service code validation, normalization, version stability, audit history, list
filters, and tenant isolation.

`tests/Unit/FocusNfeFiscalProviderTest.php` proves that network traffic is
blocked by default and verifies the isolated homologation adapter's endpoint,
Basic authentication, idempotent reference, lifecycle verbs and response
allowlist without using a real credential.

`tests/Feature/FocusNfePayloadTest.php` covers fail-closed readiness, one-time
DPS reservation, idempotent payload generation, required 2026 fields,
append-only history and the absence of HTTP traffic.

`tests/Feature/FocusNfcePayloadTest.php` covers external-only CSC setup,
independent series numbering, fail-closed reservation, model 65 fields,
payment/Pix mapping, partial payment, idempotency and zero HTTP traffic.

`tests/Feature/FiscalNfceInutilizationTest.php` covers disabled defaults,
explicit confirmation, asynchronous authorization, audit evidence and the
future/used/overlap range guards.

`tests/Feature/FiscalLifecycleTest.php` covers disabled operational defaults,
one-time queue transitions, simulated submit/consult reconciliation,
idempotent NFS-e/NFC-e provider responses, refreshed NFC-e emission time,
webhook authentication, CNPJ matching, replay
handling, definitive cancellation, immutable retry justification, exhausted
job handling, cancellation reconciliation, bounded webhook recovery, protected
artifact proxying, tenant-safe operational alerts and absence of secret
persistence.

`tests/Unit/ExternalFileFiscalCredentialResolverTest.php` covers exact
reference/provider/environment matching, fail-closed parsing, rejection of an
application-local store and token rotation without persistence.

`tests/Unit/FiscalRuntimeReadinessServiceTest.php` proves that every deployment
gate is required and that readiness output cannot contain a path, reference or
token.
