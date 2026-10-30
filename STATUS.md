# VetFlow Status

Updated: 2026-10-30

## Current State

VetFlow is an active Laravel 12 ERP project for veterinary clinics and pet shops. The current source of truth is this repository:

```text
https://github.com/ruanjardim/VetFlow
```

The local working tree was clean before this documentation pass.

## What Exists

- A dedicated counter-mode PDV that hides administrative navigation during a
  sale, keeps operator/unit/time and cash context visible, supports fullscreen,
  presents large payment shortcuts and preserves keyboard operation. USB
  barcode wedges can enter EAN/GTIN into the focused search or through rapid
  numeric capture when no editable field/modal is active; every lookup exposes
  a visual state, optional tone, latest-item highlight and automatic readiness
  for the next scan. PDV entry points open a dedicated tab, and a four-minute
  heartbeat keeps an active counter session alive while visible connectivity
  states distinguish network and authentication loss. A complete, clinic-
  scoped catalog snapshot and queued checkouts are encrypted in IndexedDB;
  supported regular sales can continue after the connection drops and are
  synchronized idempotently when it returns. Server-side stock, cash, finance,
  tenant and audit rules remain authoritative, and conflicts remain queued for
  review (see `docs/modules/sales.md`).
- Commercial scale labels are supported through the existing USB keyboard-
  wedge flow. A clinic can configure a variable-weight EAN-13 layout (prefix,
  product-code width and weight precision), and each kilogram product can keep
  a leading-zero-safe scale code. The PDV validates the EAN checksum, decodes
  the weight to three-decimal quantity and applies the normal stock/checkout
  rules online or from the encrypted offline catalog. Direct USB/serial scale
  communication remains model-specific and is not claimed as compatible
  without a selected manufacturer protocol (see `docs/modules/sales.md`).
- Banho e tosa operation: 24-hour internal booking with clinic/professional
  blocks, cross-midnight conflicts, configurable grid interval, recurrence and
  check-in; operational board; size-based prices; packages; PDV checkout; and
  commission reconciliation for partial returns (see
  `docs/modules/petshop-operations.md`).
- PDV quotes (orçamentos) with validity, print/WhatsApp, editing, cancellation
  and conversion into a sale, plus a sale type on sales and quotes with
  delivery address and fee (see `docs/modules/sales.md`).
- Payment methods per clinic, one per card machine and type, with upfront and
  installment fees, settlement days, maximum installments and a required NSU
  option; each payment keeps its fee, net amount and expected settlement
  date, shown in the card receivables report (see `docs/modules/sales.md`).
- Cash sessions per operator: opening in the PDV with a change float,
  supplies, withdrawals and expenses, closing with counted values per method
  (card fees posted as one expense per machine), mandatory explanation for
  differences, 58/80 mm thermal closing report, append-only lifecycle audit,
  automatic closing of sessions left open overnight, and manager review.
  Receiving requires an open session (see `docs/modules/sales.md`).
- Customer balance: sales to be paid later (fiado) for identified customers,
  customer credit (advance, change kept as credit, returns and cancellations
  as credit) used as a payment method, receipt of several open sales at once,
  credit given back, and a balance list and page (see `docs/modules/sales.md`).
- Finance now has a clinic-scoped chart of income and expense categories. Each
  clinic receives safe defaults; custom categories can be added, renamed,
  ordered or deactivated without deleting history; and category/type/tenant
  validation protects manual entries. Sales, purchases, grooming commissions,
  refunds, card fees, cash expenses and assisted imports are classified
  automatically, while the ledger can be filtered by category (see
  `docs/modules/financial.md`).
- Finance also has a monthly managerial result that compares income, expenses
  and balance by category. It supports realized cash by payment date or an
  explicitly operational competence view by due date, exposes records missing
  the required date and exports the same tenant-safe result to a formula-safe
  private CSV (see `docs/modules/financial.md`).
- Finance now also has clinic-scoped monthly budgets by category. Managers set
  expected income and expense values and compare them with cash or operational-
  competence actuals, including execution percentages, variances, explicit
  unclassified movement, automatic expense-overrun alerts, safe copying of the
  previous month into empty categories, a private formula-safe CSV, exact
  drilldown into composing ledger entries, a rolling 12-month comparison and a
  visible tenant-safe audit history (see `docs/modules/financial.md`).
- Finance now has assisted bank reconciliation. Each clinic registers its own
  financial accounts, imports bounded CSV or OFX statements with file- and
  row-level duplicate protection, reviews exact-value/date-window suggestions,
  and manually confirms or reverses a one-to-one match with an attributed
  audit trail. Manual matches outside the seven-day window require an
  explanation; ambiguous suggestions require an explicit choice; and the
  dashboard now exposes pending-age bands plus count/value coverage. The latest
  reversal stays visible on screen and in the private formula-safe export. The
  workflow now also accepts exact-cent, explicitly justified groups from one
  bank row to several paid ledger rows, several bank rows to one paid ledger
  row, and net settlement against existing income plus expense rows. Group
  reversal is atomic, coverage counts each bank row once, the source ledger is
  never changed automatically, and no uploaded statement file is retained (see
  `docs/modules/financial.md`).
- Fiscal foundation: clinic-scoped NFC-e/NFS-e settings, manual and idempotent
  preparation from completed sales, immutable business snapshots and
  attributed lifecycle events. Homologation has a guarded opt-in path;
  production is implemented only for the reviewed Niterói/RJ NFS-e and RJ
  NFC-e scopes and remains disabled server-wide by default (see
  `docs/modules/fiscal.md`).
- The fiscal commercial model is now bring-your-own-Focus-account. Each clinic
  pays the provider directly and can connect, rotate or disconnect its own
  homologation token through password-confirmed actions. The value is encrypted
  at rest, never rendered again and isolated by clinic; disconnecting it does
  not affect sales, cash operation or the visibly non-fiscal receipt.
- Fiscal product/service readiness: versioned tax classifications, explicit
  minimum-field diagnostics and tenant-safe pending/ready queues. Preparation
  blocks free-form or unclassified lines. Mixed or globally adjusted sales now
  receive a human-reviewed, immutable fiscal allocation that proportionally
  divides discounts, additions and payments in exact cents, assigns delivery
  and change explicitly, and reconciles the shared NFC-e/NFS-e total before a
  payload can be generated.
- Fiscal homologation now has a per-clinic evidence gate. It requires the
  enabled document types to prove configuration, complete active catalogs,
  authorization, controlled rejection, cancellation, delayed-status
  reconciliation and protected XML/document downloads; NFS-e also requires
  authenticated webhook receipt and resend. Human approval or follow-up is
  password-confirmed, append-only and fingerprinted, becoming stale when the
  profile or catalog changes. A printable dossier and no-cache JSON export
  provide an actionable, allowlisted report for the clinic/accountant without
  secret references or raw fiscal payloads. The result never enables production
  by itself.
- Fiscal production now has a separate fail-closed gate. It requires the global
  production switch, a current approved homologation fingerprint, an isolated
  encrypted production token, production-only sequences, confirmation of
  SEFAZ-RJ/CSC data where applicable and the operator's current password. The
  activation freezes its evidence and profile version, locks profile editing,
  is fully audited and can be revoked to restore homologation without erasing
  valid document history. A revoked activation cannot transmit a prepared
  production document.
- Daily fiscal reconciliation now compares each clinic's completed sales with
  its latest NFC-e/NFS-e preparations and classifies configuration, allocation,
  payload, submission, processing, authorization and human-review work. The
  tenant-safe screen and formula-safe CSV are operational controls only:
  absence of a preparation is not presented as proof of a tax obligation.
- The clinic-scoped fiscal document center now groups prepared NFC-e and NFS-e
  by operational competence, filters a bounded monthly period by type, status
  and sale, and exports an allowlisted, formula-safe private CSV for conference.
  Raw provider payloads, responses and credentials never enter that export.
- Reconciled periods of up to 31 days now accept a password-confirmed internal
  fiscal closing. The append-only record freezes a safe evidence snapshot,
  reviewer, totals and audit event, prevents duplicate current confirmation and
  becomes visibly stale after a sale, document or profile change. This remains
  an operational control and is not a tax filing or accounting certification.
  Every historical closure can be printed or exported as a private no-cache
  JSON dossier containing only its frozen safe evidence, current/stale result
  and integrity hash. A monthly tenant-safe history filters closures by clinic,
  competence, responsible person and recalculated current/stale state.
- Partial refunds and fully returned sales now enter an assisted fiscal review.
  The tenant-safe, password-confirmed workflow freezes item, amount, event and
  linked-document evidence; records either follow-up or a completed human
  review with its guidance source; and becomes stale after any later change.
  A current completed review reconciles the operational queue but never creates,
  prescribes, transmits or cancels a fiscal document automatically. Every
  historical decision has a printable, integrity-hashed dossier and a private
  no-cache JSON equivalent built from its frozen secret-free evidence.
- Municipality-aware NFS-e readiness now starts with Niterói/RJ (`3303302`):
  the center maps its 2026 National NFS-e/DPS route, infers the municipality
  from a Niterói clinic address, exposes missing issuer/certificate setup, and
  enforces the city's same-service-subitem and same-ISS-rate grouping rule.
  External submission remains disabled by default.
- Focus NFe is the selected first provider boundary for Niterói NFS-e. The
  Fiscal center stores only an external credential reference, and the isolated
  adapter defines homologation submit/status/cancel requests with sanitized
  responses. The default application still resolves the disabled provider and
  keeps fiscal HTTP off, so no external request can originate without explicit
  deployment activation.
- A Focus NFS-e payload can now be generated locally after explicit fiscal
  configuration. The workflow validates the frozen tomador, single service-tax
  grouping, closed totals and mandatory 2026 IBS/CBS codes, then reserves the
  next DPS number once and records the action. This remains a preparation
  artifact until the independent server-side homologation gates are enabled.
- NFC-e now has a separate RJ/model-65 preparation gate. The clinic confirms
  SEFAZ-RJ credentialing and stores only the public six-digit CSC identifier
  plus an opaque external reference, never the CSC value. A dedicated
  per-series sequence, product IBS/CBS status/classification, deterministic
  payment mapping and total/change reconciliation produce an idempotent local
  Focus payload. The guarded adapter can submit, consult and cancel it through
  the same idempotent queue in the document's approved environment; the
  short-lived emission time is refreshed at the attempt and the NFS-e webhook
  remains outside the NFC-e flow.
- NFC-e numbering gaps now have a separate confirmed inutilization queue. It
  accepts only already-passed ranges with no prepared VetFlow document,
  rejects overlaps, validates the Focus response against issuer/model/series/
  range, and records the result and audit trail without storing a credential.
- The Focus homologation lifecycle now has a dedicated idempotent submission
  queue, bounded retries, non-regressing state transitions, delayed status
  reconciliation and an authenticated replay-safe webhook with issuer
  verification. All operational switches remain off, the concrete provider is
  still unbound and tests use simulated responses, so this adds no live fiscal
  traffic.
- Guarded credential resolvers now support both customer-managed encrypted
  database records and operations-managed external files. Both match the
  document's frozen clinic/reference/provider/environment, while independent
  outbound/submission/webhook switches and runtime diagnostics keep defaults
  fully disabled.
- Authorized NFS-e documents now have a guarded cancellation queue with a
  frozen justification, bounded retries, provider-confirmed final state and
  append-only events. The Fiscal center surfaces clinic-scoped alerts for
  exhausted transmission/status/cancellation work and configurable stale
  processing or cancellation queues in homologation or an explicitly activated
  production window.
- Fiscal contingency now supports manual reconciliation after authorization or
  an ambiguous cancellation, queued webhook resend with a cooldown, and
  protected XML/DANFSe downloads. Artifact paths stay server-side, redirects
  require an exact HTTPS host allowlist, and provider authentication is never
  forwarded to the artifact host.

- Laravel application structure.
- Modular backend under `app/Modules`.
- Authentication and password reset flows.
- Active-user and permission middleware.
- Clinics, access management, tutors, patients, schedules, appointments, inventory, products, product intelligence, purchase entries, suppliers, pet shop services, service orders, sales, financial, dashboard, and validation modules.
- Database migrations for the current operational model.
- A commercial SaaS foundation with editable plans and features, one subscription per clinic, per-subscription overrides, central entitlement checks, active-user limits, a global `/admin/saas` console, and transactional onboarding. Existing clinics retain full access through an internal compatibility plan.
- A clinic-scoped printer configuration area for fiscal, non-fiscal, label, document, and other equipment, with connection and paper preferences, a single active default, browser print testing, audit history, and access for all six standard clinic roles.
- Thermal printer testing and completed-sale receipts now use dedicated 58/80 mm print layouts that remove the application shell and browser page margins. Network receipt printers default to raw port 9100 when omitted; the browser still requires the workstation's installed print queue and keeps the receipt explicitly non-fiscal.
- Feature tests for authentication, authorization, clinic tenant isolation, initial admin setup, and operational purchase/clinical flows.
- Existing architecture and audit documentation under `docs/`.
- Assisted CSV implementation workflow for Tutors, Patients, Suppliers,
  Products, initial Stock, and Financial records, with an equivalent Excel
  `.xlsx` source and a permanent
  clinic-scoped summary of successful imports.
- Bounded NF-e XML/key intake and resilient external product lookup that keep
  manual operational fallbacks available during provider outages.
- Clinic-scoped collaborator management with six standard role presets,
  auditable role changes, and administrator self-lockout protection.
- Clinic-scoped professional identification with CRMV/UF on veterinarian
  profiles and an immutable responsible-professional snapshot when a
  prescription is finalized.
- A local/testing-only walkthrough reset that removes fixed demo fixtures
  selectively and can recreate them without touching unrelated clinic data.
- A release-readiness diagnostic for application configuration, database and
  migrations, logging, queues, storage, and explicit production backup
  confirmation.
- A clinic-scoped Stock Radar that translates current balance, configured
  minimum, cost value, product age, and 90-day net demand into explicit,
  mutually exclusive review signals. The first rules label replenishment,
  no-recent-demand, high-coverage, new, adequate, and unparameterized products
  without moving stock or creating purchases automatically.
- A clinic-scoped product ABC analysis that ranks return-adjusted net revenue
  over explicit 30-, 90-, or 180-day periods, preserves the original classes
  while filtering, and places current stock value beside each product without
  changing price, purchase, supplier, or inventory state.
- A clinic-scoped Pricing Radar that makes missing cost, missing price,
  below-cost, break-even, low-margin, and adequate active products explicit,
  with current stock cost, potential revenue, and known gross-margin exposure
  kept observational rather than changing catalog prices automatically.
- A clinic-scoped cycle-count workflow that snapshots active product balances,
  saves physical quantities progressively, blocks stale finalization after any
  intervening movement, and turns only confirmed variances into protected,
  auditable inventory entries or exits. Finalization is single-use and
  cancellation never changes stock.
- A tenant-safe inventory-variance report that measures line-level count
  accuracy, separates surplus and shortage values using snapshotted costs,
  ranks repeated product divergences, retains soft-deleted history, and exports
  the filtered result as a no-cache, formula-safe CSV.
- A standalone operational guide for the global Clinics registry, with an
  explicit global-administrator boundary on clinic management.
- A provider-specific KingHost staging runbook and a disabled-by-default,
  token-protected queue cron bridge for low-volume shared-hosting validation.
- A Hostinger production runbook and a CLI Cron Job transport that drains the
  bounded database queue without exposing an operational HTTP endpoint or
  token in the scheduled command.
- Explainable replenishment suggestions that prioritize low-stock products,
  use recent received-purchase batches when the history is sufficient, and
  prefill a reviewable purchase entry without creating an automatic order.
- Replenishment cards include a tenant-safe 90-day net demand signal from
  completed product sales, deducting returns and preserving the calculation in
  the purchase prefill without automatically changing its quantity.
- Supplier/product observations now summarize recent received batches, weighted
  cost, and valid purchase-to-receipt lead-time samples without assigning a
  supplier score, delivery promise, or automatic selection.
- Low-stock products now expose demand-derived coverage days, the projected
  balance at observed receipt time, and an explicit rupture/insufficient-data
  state without changing the purchase recommendation automatically.
- Replenishment suggestions now accept append-only reviewed/on-hold decisions,
  retain the attributed evidence snapshot, expose a clinic-scoped history, and
  become visibly superseded after their source calculation changes.
- Replenishment review and purchase prefill now share a canonical, versioned
  evidence snapshot; purchase metadata includes an HMAC signature so future
  validation can detect tampering before measuring operator changes.
- Saved replenishment purchases now retain a backend-computed comparison of
  suggested versus actual quantity, unit cost, and supplier. Invalid or
  mismatched evidence is explicitly unavailable and contributes no metrics.
- A clinic-scoped decision-history interface now presents those purchase
  comparisons with filters, transparent deltas, supplier context, and source
  entry links while keeping signatures and raw metadata private.
- Purchase-decision validation now consolidates comparable, kept, adjusted,
  and unavailable totals, adherence, field-level adjustment counts, and mean
  absolute deviations for the current clinic scope without automatic tuning.
- Purchase-decision history and metrics now share an explicit purchase-date
  cohort (30, 90, 180 days, or all), defaulting to the latest 90 days so pilot
  indicators cannot be silently distorted by old purchases.
- The validation screen ranks up to ten products by adjusted purchase decisions
  and exposes their adherence, adjustment rate, changed fields, unavailable
  evidence, and mean quantity/cost deviations within the selected cohort.
- Signed replenishment purchases now require a controlled reason when quantity,
  cost, or supplier is adjusted. The reason and bounded optional note are saved
  in decision schema version 2, restored on editing, and shown safely in history;
  legacy adjusted records without a reason remain explicit.
- An advisory pilot-maturity panel evaluates the selected cohort against visible
  initial references (20 comparable decisions, 5 products, 90% valid evidence,
  and complete adjustment reasons), identifies the next missing action, and
  explicitly avoids statistical or automatic-rule claims.
- Replenishment pilot validation can now export the selected cohort as a
  versioned, no-cache JSON report whose explicit allowlist excludes signed
  evidence internals, raw item metadata, and free-form decision notes.
- Each validation period now accepts append-only human review or hold events
  bound to the allowlisted report hash. The latest decision is clinic-scoped
  and becomes visibly stale whenever its underlying validation facts change.
- A read-only pilot-review history lists those events in reverse chronological
  order, filters them by period or decision, and recalculates current/stale
  evidence status without returning stored hashes or snapshots to the view.
- The pilot-review history now opens with a four-period portfolio covering 30,
  90, and 180 days plus complete history. It consolidates sample size, evidence
  coverage, maturity, and latest-review status while warning that windows overlap.
- An assisted appointment reminder queue that prepares WhatsApp contact and
  records operator-confirmed outcomes, status changes, and an auditable contact
  history without claiming automatic delivery.
- A permission-aware expandable sidebar and a structured species, breed, and
  coat/pattern catalog for companion, exotic, wildlife, aquatic, and
  large-animal patients, with reusable clinic-specific entries when the
  standard catalog is insufficient.
- Searchable, species-aware pathology and exam catalogs with shared standard
  terms, clinic-owned additions, and structured medical-record links that
  preserve the existing free-text diagnosis.
- A vaccine catalog with shared standard options, clinic-owned entries,
  species compatibility, and optional clinic-configured dose/interval fields
  that only suggest a next date instead of imposing a clinical protocol.
- A permission-aware patient clinical profile that consolidates the existing
  registration, consultation, medical-record, and vaccination history without
  changing the underlying operational records.
- A clinic-scoped hospitalization workflow for patient admissions, operational
  follow-up, discharge registration, and patient-profile history without
  duplicating or changing medical records.
- Structured patient prescriptions with multi-item directions, draft review,
  protected finalization, cancellation history, print-ready presentation, and
  a dedicated veterinarian-facing permission.
- Exam-result records attached to structured requests, with tenant isolation,
  draft review, immutable finalization, auditable cancellation, and protection
  against removing a request that already owns clinical result history.
- Append-only hospitalization evolutions with optional vital-sign snapshots,
  author and observation timestamps, tenant isolation, and automatic write
  protection once the admission is discharged or cancelled.
- Patient clinical alerts with immutable original content, auditable
  resolution, clinic isolation, and active banners in the patient profile,
  medical record, prescription, and hospitalization screens. VetFlow does not
  calculate severity or create these alerts automatically.
- A reverse-chronological patient clinical timeline assembled from permitted
  source modules, with direct source links, clinic isolation, and no duplicated
  clinical state or automatic interpretation.
- Clinic-scoped sidebar identity with optional automatic, manually selected,
  or hidden animal icons. Automatic mode uses the clinic users' configured
  species of practice and falls back to a generic paw for mixed practices.
- An append-only administrative audit trail for collaborator access, clinic
  branding, printers, SaaS configuration, Banho & Tosa availability, and cash
  lifecycle changes, with tenant isolation, actor and before/after snapshots,
  password redaction, filtering, and a dedicated read permission.
- A read-only backup/restore drill that captures privacy-safe database control
  totals, rejects the live source as a restore target, and produces evidence
  consumable by the release-readiness gate.
- A synthetic end-to-end runtime probe that verifies a persistent storage
  sentinel through the real asynchronous queue, removes its temporary
  artifacts after approval, and supplies environment-bound evidence to the
  release-readiness gate.
- A public, no-cache release identity endpoint that exposes only the deployed
  Git SHA and lets operators prove that health checks target the intended
  commit; Composer now writes a Git-derived marker on every repository deploy,
  the runtime prefers it over a stale environment value, CI verifies it against
  the checked-out commit, and the production gate rejects a missing or malformed
  SHA.
- Production Composer activation clears caches from the previous release,
  rebuilds the current route table, and only then writes the release marker, so
  a new commit cannot be reported while old routes are still being served.
- Guided onboarding coverage in the Implementation assistant, calculated from
  the latest successful import of each supported block without duplicating
  imported data or crossing clinic boundaries.
- The Implementation assistant summarizes active access and professional
  identification per clinic and hands authorized operators to collaborator
  creation with the clinic preselected, while human access validation remains
  the pilot authority.
- Completed onboarding blocks receive a read-only, tenant-safe data-quality
  review with explicit pending-record counts; blocks not yet imported remain
  marked as awaiting evaluation.
- Quality pendencies have paginated clinic-scoped drilldowns with explicit
  correction reasons and permission-aware links to their source records.
- Pilot preparation has a five-item checklist whose completion and reopening
  decisions are append-only, clinic-scoped, attributed, timestamped, and
  optionally documented by the operator.
- Each clinic can keep a versioned pilot-release plan with operational and
  support owners, planned date, functional scope, release notes, and the
  attributed history of every revision.
- A tenant-safe pilot-history page consolidates the append-only import,
  checklist, release-plan, and evidence-bound decision trails for each clinic.
- The current pilot-preparation state is available as a print-friendly report
  and a no-cache JSON download generated from the same readiness evidence.
- Multi-clinic implementation operators receive a prioritized readiness
  portfolio with status totals, stale-decision counts, and status filtering.
- A consolidated readiness panel requires coverage, zero detected quality
  pendencies, a complete checklist, and a release-plan revision before an
  explicit human approval; each decision is bound to a hashed evidence
  snapshot and becomes stale when its source evidence changes.
- The fictitious walkthrough includes a reproducible, intentionally blocked
  pilot-preparation scenario that exercises all six coverage blocks, quality
  gates, checklist progress, and a versioned release plan without real data.
- Authorized administrators have a protected Operations Center that reuses the
  CLI release diagnostics, discovers only safe summaries of private backup and
  runtime evidence, and never exposes paths, hashes, credentials, or sentinels.
- Operational smoke checks are append-only and scoped by clinic, environment,
  and release SHA; consolidated approval is evidence-bound, becomes stale when
  a source gate changes, and is available as print-friendly and no-cache JSON
  reports.
- Banho & Tosa has a separate eight-item, release-scoped operational checklist
  in the same center. It records the real module pilot without weakening or
  replacing the 12 mandatory release smoke tests.
- Administrators can prepare and verify the synthetic runtime probe from the
  Operations Center. Every transition is append-only and scoped by clinic,
  environment, and release SHA, while evidence paths and sentinel hashes remain
  private.
- Restore evidence produced against an isolated database can be imported from
  the Operations Center through a bounded, sanitized JSON intake. Consultation
  and execution now use separate permissions, and imports keep an append-only
  tenant/release audit record without exposing infrastructure fingerprints.
- The Operations Center has a unified read-only timeline over probes, restore
  evidence, smoke checks, and release decisions. It is filtered by clinic,
  environment, release, and category without exposing private operational data.
- Current release gates are translated into a six-step, permission-aware
  operational plan with safe guidance and direct interface navigation. The plan
  remains advisory and never executes infrastructure work or release approval.
- Backup and runtime evidence cards expose their configured validity deadline
  and distinguish missing, current, near-expiry, expired, failed, and invalid
  dates without exposing files, hashes, or evidence contents.

## GitHub Scan Summary

The public GitHub scan found another repository with the exact same display name:

- `AbdelrahmanMU/VetFlow`

That repository is not ahead in product implementation. Its strongest area is documentation governance: README, status tracking, changelog, project context, module documentation taxonomy, templates, and AI-assistant playbooks.

The scan also found related VetFlow-style repositories:

- `Julio-73/vetflow-enterprise`
- `JorgeIvanPuyo/vetflow-platform`
- `brunorizzieri86-svg/vetflowcare`

Those repositories reinforced the same conclusion: the public-facing gap in this VetFlow was documentation, not core product scope.

## Documentation Added In This Pass

- Root `README.md`.
- Root `STATUS.md`.
- Root `CHANGELOG.md`.
- Root `AGENTS.md`.
- `docs/README.md`.
- `docs/PROJECT_CONTEXT.md`.
- `docs/modules/_INDEX.md`.
- Filled `docs/engineering-process.md`.
- Added the GitHub comparison audit under `docs/audits/`.
- Completed database notes for clinics, users, and the current employee/access model.
- Added module documentation for Products, Product Intelligence, Inventory, Purchase Entries, Sales, Financial, and Clinical Core.
- Added standalone module documentation for Dashboard, Suppliers, and Validation.
- Added CI and deployment documentation.
- Added GitHub Actions workflow for Laravel tests and Vite build.
- Added contribution and security guidance.
- Added GitHub issue and pull request templates.
- Added a public roadmap with product differentiators and milestones.
- Added an optional walkthrough demo seeder.
- Added a visual walkthrough with real application screenshots and fictitious data.

## Open Documentation Gaps

- Add module docs for thin or overlapping areas as their rules stabilize.

## Next Recommended Pilot Slice

1. Confirm the KingHost contract gate and provision the isolated staging
   environment without real clinic data.
2. Perform and record a database backup/restore drill in an isolated
   environment.
3. Validate persistent storage, the bounded queue cron, and the complete
   release checklist against staging.
4. Publish release notes when the first pilot scope and support owner are
   defined.
