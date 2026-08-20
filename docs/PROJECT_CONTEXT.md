# VetFlow Project Context

Updated: 2026-08-20

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
received purchases. Patient care now includes a permission-aware longitudinal
profile that connects appointments, medical records, prescriptions,
vaccinations, and hospitalizations while
keeping the source records and their access boundaries intact. Structured
prescriptions now extend that history with a reviewable draft, immutable
finalization, and explicit cancellation trail. Structured exam requests can
also receive protected result documents while VetFlow remains neutral about
their clinical interpretation.

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
