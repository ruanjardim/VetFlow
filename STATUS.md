# VetFlow Status

Updated: 2026-08-26

## Current State

VetFlow is an active Laravel 12 ERP project for veterinary clinics and pet shops. The current source of truth is this repository:

```text
https://github.com/ruanjardim/VetFlow
```

The local working tree was clean before this documentation pass.

## What Exists

- Laravel application structure.
- Modular backend under `app/Modules`.
- Authentication and password reset flows.
- Active-user and permission middleware.
- Clinics, access management, tutors, patients, schedules, appointments, inventory, products, product intelligence, purchase entries, suppliers, pet shop services, service orders, sales, financial, dashboard, and validation modules.
- Database migrations for the current operational model.
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
- A local/testing-only walkthrough reset that removes fixed demo fixtures
  selectively and can recreate them without touching unrelated clinic data.
- A release-readiness diagnostic for application configuration, database and
  migrations, logging, queues, storage, and explicit production backup
  confirmation.
- A standalone operational guide for the global Clinics registry, with an
  explicit global-administrator boundary on clinic management.
- A provider-specific KingHost staging runbook and a disabled-by-default,
  token-protected queue cron bridge for low-volume shared-hosting validation.
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
- An append-only administrative audit trail for collaborator access and clinic
  branding changes, with tenant isolation, actor and before/after snapshots,
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
  commit; the production release gate rejects a missing or malformed SHA.
- Guided onboarding coverage in the Implementation assistant, calculated from
  the latest successful import of each supported block without duplicating
  imported data or crossing clinic boundaries.
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
