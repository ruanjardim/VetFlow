# VetFlow Roadmap

Updated: 2026-08-23

This roadmap describes the product direction that should stay visible while VetFlow evolves. It is intentionally high-level and should be revised as implementation priorities change.

## Product Direction

VetFlow should become a polished ERP for veterinary clinics and pet shops, with strong multi-clinic isolation, operational traceability, financial visibility, and practical product intelligence.

The product should feel different from a generic CRUD system by connecting the clinic journey end to end:

- Tutor and patient history.
- Appointments and schedules.
- Service orders.
- Product and stock movement.
- Purchase entries and NF-e intake.
- Sales, returns, payments, cashier closure, and financial records.
- Dashboard signals that help the business act faster.

## Differentiators To Protect

- Multi-clinic data isolation as a first-class rule.
- Product intelligence based on GTIN, catalog enrichment, and purchase/sales behavior.
- NF-e XML and access-key import paths for faster stock entry.
- Cashier and financial traceability from operational events.
- Modular Laravel structure that keeps future domains easier to extend.
- Clear documentation and CI so the repository remains credible and maintainable.

## Completed Near-Term Priorities

- CI covers backend tests and the frontend build.
- The repository includes a visual walkthrough with fictitious data.
- Dashboard, Suppliers, Validation, Implementation, and Access have standalone
  documentation.
- Product lookup and NF-e intake degrade safely during provider outages.
- The dashboard presents ordered operational priorities.
- Six clinic-safe role presets are available through the Access module.
- Demo fixtures have a controlled local reset/reseed path.
- A release checklist and automated runtime diagnostic are available.
- Clinics have a standalone operational guide and a global-administrator
  authorization boundary.
- Replenishment suggestions combine current stock, minimum stock, and recent
  received-purchase history with an explicit confidence level and reason.
- The selected KingHost staging candidate has a provider-specific runbook and
  a bounded, token-protected queue cron bridge that remains disabled by default.
- Sales expose realized gross profitability by item type, category, and item,
  adjusted for discounts and returns while preserving historical cost
  snapshots.
- Appointments have an assisted reminder queue with prepared WhatsApp contact,
  explicit operator-recorded outcomes, status synchronization, and a
  clinic-scoped audit history.
- Navigation is grouped into permission-aware expandable modules, and patient
  registration uses an extensible species and breed catalog that supports
  exotic, wildlife, aquatic, and large-animal practices.
- Patient care has a permission-aware longitudinal profile that connects
  appointments, medical records, prescriptions, vaccinations, and
  hospitalizations without duplicating source records or exposing restricted
  clinical details.
- Clinical records can produce structured prescriptions whose finalized
  content is immutable, whose cancellations remain auditable, and whose item
  text remains independent from mutable commercial catalogs.
- Structured exam requests can receive tenant-safe result documents whose
  finalized content is immutable and whose cancellation history remains
  auditable without automatic clinical interpretation.
- Active hospitalizations have an append-only evolution diary with authorship,
  observation timestamps, optional vital-sign snapshots, and a read-only
  history after discharge or cancellation.
- Patient care exposes manually recorded clinical alerts at the patient,
  medical-record, prescription, and hospitalization touchpoints, with
  tenant-safe authorship and auditable resolution but no automatic clinical
  classification.
- The patient profile provides a permission-aware longitudinal timeline across
  clinical source modules while preserving each source record and access
  boundary.
- Sensitive collaborator-access and clinic-branding changes produce a
  tenant-scoped, read-only administrative audit trail without storing passwords.
- Backup operations have a read-only snapshot and isolated-restore verification
  workflow that generates privacy-safe evidence for the release gate.
- Runtime operations have a synthetic end-to-end probe that carries a
  persistent storage sentinel through the real asynchronous queue and produces
  recent environment-bound evidence for the release gate.
- Deployments have a public release identity endpoint and a production gate
  that validate the complete Git SHA independently from process health.
- The Implementation assistant shows tenant-safe guided onboarding coverage
  across the six supported import blocks, based on their latest successful
  executions.
- Completed onboarding blocks expose explicit, read-only data-quality pending
  counts while incomplete blocks remain awaiting evaluation.

## Current Near-Term Priorities

1. Pass the KingHost contract gate, provision staging, and validate the
   selected hosting target without real clinic data.
2. Execute and record the database restore drill against the provisioned
   staging environment before the first pilot.
3. Run the release checklist and smoke tests in staging.
4. Define the first pilot scope, release notes, and support owner.
5. Validate replenishment suggestions with pilot data and tune the history
   window or quantity rule only when real evidence supports the change.

## Product Milestones

### Milestone 1: Public Repository Maturity

- README, status, changelog, architecture docs, module docs, and CI are present.
- GitHub issue and pull request templates guide future work.
- Security and contribution expectations are documented.
- A visual walkthrough with fictitious demo data shows the main product experience.

### Milestone 2: Operational Reliability

- Tenant isolation remains covered by automated tests.
- Sales, purchase entries, inventory, and financial side effects remain covered by regression tests.
- External product lookup and NF-e import failures degrade gracefully.
- Deployment documentation is validated against the chosen production target.

### Milestone 3: Professional Product Experience

- Main flows have screenshots or a guided walkthrough.
- Dashboard presents actionable business signals.
- Role/permission flows are clear enough for implementation and support.
- Product intelligence helps operators reduce manual typing and stock mistakes.

### Milestone 4: Production Readiness

- Environment, backup, logging, queue, and storage practices are validated.
- A release checklist exists for migrations, seeders, assets, and smoke tests.
- Security reporting and support ownership are clear.
- Demo data or onboarding fixtures are available without exposing real clinic data.

## Ideas Worth Exploring

- Extend guided onboarding from coverage and data-quality checks to explicit
  pilot-readiness evidence.
- Supplier lead-time and demand signals for future replenishment tuning.
