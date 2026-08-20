# VetFlow Status

Updated: 2026-08-20

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
