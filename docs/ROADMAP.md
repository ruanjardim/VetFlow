# VetFlow Roadmap

Updated: 2026-08-29

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
- Low-stock replenishment exposes completed-sales net demand and returns over a
  fixed 90-day window as review context without turning it into an automatic
  purchasing rule.
- Replenishment exposes supplier/product delivery counts, observed weighted
  cost, and purchase-to-receipt lead-time samples without creating a supplier
  score or automatic selection rule.
- Current stock, net daily demand, and observed lead time now produce an
  explainable coverage margin or rupture warning while incomplete evidence
  remains explicitly inconclusive.
- Human replenishment reviews are append-only and evidence-bound, with an
  explicit on-hold path, tenant-safe history, and stale-state detection after
  the underlying suggestion changes.
- Replenishment purchase prefills preserve the same canonical evidence used by
  human review in a signed envelope, establishing a trustworthy base for
  measuring later operator decisions without changing rules automatically.
- Saved purchase items now measure whether signed replenishment quantity, cost,
  and supplier context were kept or adjusted; unverifiable evidence is excluded
  and the comparison remains observational.
- Purchase decision history is now visible through a tenant-safe read-only
  interface with classification/status filters and no exposure of signed
  evidence internals.
- Clinic-scoped validation metrics now quantify suggestion adherence,
  field-level adjustments, unavailable evidence, and mean absolute deviations
  while remaining observational rather than self-tuning.
- Replenishment validation now supports explicit 30-, 90-, and 180-day or
  complete purchase cohorts, with the selected window shared by history and
  summary and visible to the operator.
- Product-level validation now highlights the ten products with the most
  adjusted decisions and breaks down adherence, changed fields, unavailable
  evidence, and mean deviations within the selected cohort.
- Adjusted signed replenishment purchases now require a controlled operational
  reason, with a bounded explanation for `other`, backend-derived labels, and a
  safe reason trail in the purchase-decision interface.
- Pilot validation now exposes an advisory maturity state and next action based
  on explicit decision-volume, product-coverage, evidence-quality, and reason-
  completeness references, without implying statistical proof or self-tuning.
- The selected replenishment validation cohort can be exported as a versioned,
  no-cache, allowlisted JSON report for human pilot review without exposing
  signed evidence internals or changing any recommendation rule.
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
- Pilot preparation records append-only, attributed decisions for data review,
  quality, access, backup, and training checks in each clinic.
- Pilot-release plans keep versioned owners, date, scope, and release notes per
  clinic without overwriting earlier preparation evidence.
- Pilot readiness consolidates four evidence gates and requires an explicit,
  evidence-bound human approval that becomes stale after source changes.
- Release readiness has a protected Operations Center with shared CLI/UI
  diagnostics, private-evidence summaries, an append-only smoke checklist,
  stale-decision detection, and printable/JSON reports.
- Operational events from probes, restore evidence, smoke checks, and release
  decisions are reviewable in one tenant-safe, release-filtered timeline.
- Pending release gates provide an interface-guided next-action plan that
  distinguishes human, infrastructure, and authorized operational work.
- Operational evidence exposes safe validity and expiration guidance derived
  from the same age limits enforced by the release gate.
- Runtime probe preparation and verification are available through the
  Operations Center with tenant-safe, append-only execution history and the
  same private evidence format consumed by the CLI gate.
- Restore evidence can be registered through a bounded and sanitized JSON
  intake, while operational execution is separated from read-only readiness
  access and the actual restore remains confined to an isolated database.

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

- Run the guided-onboarding readiness rehearsal in staging with the fictitious
  scenario, exported report, and history before changing any gate or quality rule.
- Validate demand and supplier lead-time observations with pilot data before
  allowing either signal to tune quantities or supplier choices.
