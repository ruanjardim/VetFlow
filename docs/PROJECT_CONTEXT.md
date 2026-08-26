# VetFlow Project Context

Updated: 2026-08-26

## Product

VetFlow is an ERP for veterinary clinics, pet shops, and integrated operations.

The project is intended to support clinic management, clinical workflows, stock and purchase control, pet shop services, sales, financial operations, dashboards, and operational intelligence.

## Business Direction

VetFlow should evolve as a multi-clinic platform. The main operational records must belong to a clinic so that multiple clinics can use the system while keeping their data separated.

Important business areas:

- Clinic administration.
- Users, roles, permissions, and access control.
- Tutors and patients.
- Schedules and appointments.
- Products and product intelligence.
- Inventory and purchase entries.
- Pet shop services and service orders.
- Sales, payments, returns, and cash register closure.
- Financial transactions.
- Dashboards and reports.

## Technical Direction

VetFlow is built with Laravel 12 and PHP 8.2+, using a modular structure under `app/Modules`.

Core principles:

- Business logic belongs in modules.
- Shared infrastructure belongs in `app/Core` or dedicated shared/support areas.
- Controllers should stay thin.
- Services coordinate business workflows.
- Repositories handle meaningful persistence queries.
- Requests validate input.
- Middleware and policies protect access.
- Tests should cover high-risk business flows.

## Local Development

The default `.env.example` is configured for SQLite:

```text
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Run setup with Composer, npm, migrations, and the Laravel server as described in the root README.

## Current Delivery Focus

The repository now includes public project documentation, CI, a visual
walkthrough, assisted onboarding imports, resilient NF-e/product lookup,
actionable dashboard priorities, clinic-scoped collaborator management, and
explainable stock replenishment suggestions based on current stock and recent
received purchases. Replenishment now also displays a clinic-scoped 90-day net
demand signal from completed product sales after returns. This read-only signal
is preserved in suggestion metadata but does not automatically change the
proposed purchase quantity. The same replenishment view now groups received
batches by supplier/product and shows delivery counts, weighted observed cost,
and lead-time samples when purchase and receipt dates are available. These are
historical facts, not an automatic supplier recommendation or delivery promise.
Current stock can now be compared with those signals through an explainable
coverage projection. A rupture warning appears only when demand-derived coverage
does not exceed the observed average lead time; absent demand or lead-time data
remains explicitly inconclusive, and the projection never changes a purchase.
Operators can now append a reviewed or on-hold decision to that exact
replenishment evidence. The protected history preserves authorship and the
calculation snapshot, and labels a decision as superseded when any source
signal changes. Reviews never create a purchase or mutate inventory.
The same canonical snapshot now accompanies a purchase prefill inside a
versioned, HMAC-signed envelope. This creates a tamper-detectable foundation
for comparing future saved purchases with what VetFlow originally suggested;
saved replenishment items now record whether quantity, cost, or the reference
supplier was kept or adjusted, including transparent deltas. Invalid or
scope-mismatched envelopes produce no comparison. These observations remain
descriptive and never tune a rule or change a purchase automatically.
The protected purchase-decision history now makes those observations visible
through the interface, with filters for decision and entry state, suggested
versus actual values, and strict clinic isolation. Only the safe read model is
rendered; signed envelopes, fingerprints, and raw intelligence metadata remain
server-side.
Patient care now includes a permission-aware longitudinal
profile that connects appointments, medical records, prescriptions,
vaccinations, and hospitalizations while
keeping the source records and their access boundaries intact. Structured
prescriptions now extend that history with a reviewable draft, immutable
finalization, and explicit cancellation trail. Structured exam requests can
also receive protected result documents while VetFlow remains neutral about
their clinical interpretation. Active admissions now keep append-only
evolutions whose history remains readable after discharge or cancellation.
Manually recorded patient clinical alerts are now visible across the highest
risk clinical screens and use an auditable active/resolved lifecycle. VetFlow
does not infer their severity or generate them from other clinical data.
The patient profile also assembles permitted source records into a single
reverse-chronological timeline. This is a read model only: the sequence does
not duplicate data or imply a clinical relationship between events.
Sensitive administrative changes to collaborator access and clinic branding
now produce tenant-scoped, append-only audit events. Password values are never
stored in that history.
Backup readiness now includes a read-only snapshot and isolated-restore
verification workflow. It records only control totals and produces a recent
evidence file for the release gate; the hosting provider still performs the
actual export, import, and temporary-database cleanup.
Runtime readiness now includes a synthetic two-phase probe. Preparation writes
a private storage sentinel and dispatches one real asynchronous job; verification
requires that job to read the same sentinel and write a matching result before
producing evidence for the release gate. Successful verification removes the
temporary probe artifacts and never creates clinic or clinical records.
Release traceability now exposes only the normalized full Git SHA at
`/ops/release`. Staging and production readiness require this identity, so a
healthy process cannot be mistaken for proof that the intended commit is live.
The same operational controls now have a protected interface. The Operations
Center reuses the command-line release gates, locates safe summaries of the
latest private backup and runtime-probe evidence, records clinic-scoped smoke
test events, and binds the final human release decision to a hashed evidence
snapshot. Reports never expose evidence paths or secret runtime material.
Administrators can also prepare and verify the synthetic runtime probe from
that interface. Its execution history is append-only and bound to the current
clinic, environment, and release SHA; the reusable runtime service still owns
evidence generation and cleanup, so the web and CLI paths cannot drift.
Restore evidence generated outside the live database can now be imported by an
operator with the separate execution permission. The intake validates age and
shape, removes every field outside a strict allowlist, stores the sanitized
evidence privately, and appends only safe metadata to the tenant/release audit
history. The interface never performs the restore itself.
Operators can review those records together with runtime probes, smoke checks,
and release decisions in a single reverse-chronological history. The read model
stays within the current clinic and environment, defaults to the published
release, and never exposes free-form notes, evidence hashes, private paths, or
runtime sentinels.
The Operations Center also turns the current gates into an ordered release
plan. It explains the next safe action, suggests whether infrastructure,
technical operations, validation, or the release owner should act, and links
to the corresponding interface section. This guidance is permission-aware but
advisory: it never changes the environment or records approval automatically.
Evidence summaries now make their temporal validity explicit. Operators can see
the configured deadline, a near-expiry warning, expiration, failure, absence,
or an invalid future date without gaining access to paths, fingerprints,
hashes, sentinels, or file contents. Temporal validity remains only one part of
the complete technical gate.
The Implementation assistant now derives onboarding coverage per clinic from
its append-only successful import summaries. Completed blocks also receive a
read-only quality review based on six documented, tenant-scoped checks. Blocks
without a successful import remain awaiting evaluation, and neither panel
infers pilot approval or changes business data.
Pilot preparation now also has a five-item human checklist per clinic. Every
completion or reopening is stored as a new attributed event, so the current
state is convenient to read without sacrificing the decision history.
The pilot plan follows the same principle: every change to its operational and
support owners, planned date, functional scope, or release notes creates a new
attributed revision instead of overwriting earlier preparation evidence.
The final guided-onboarding slice consolidates coverage, quality, checklist,
and release-plan evidence without converting them into an automatic business
approval. A human approval or hold decision is stored with a hashed evidence
snapshot and becomes stale whenever one of those source signals changes.
Operators can now inspect the complete pilot history, correct quality issues
through tenant-safe queues, emit the current preparation report, and triage a
multi-clinic portfolio by readiness status. A reproducible fictitious scenario
exercises the blocked path before any rule is tuned from pilot evidence.

The first staging candidate is KingHost shared Linux hosting, using only
fictitious data and a bounded authenticated cron bridge because shared hosting
does not supervise a permanent queue worker. External provisioning remains
pending. The next delivery focus is to pass the contract gate, prove backup
restoration, validate persistent storage and queue processing, execute the
release checklist in staging, and define release/support ownership.

The global Clinics registry now has a standalone operational guide and an
explicit global-administrator authorization boundary. Thin or overlapping
modules should receive dedicated documentation as their business rules
stabilize.
