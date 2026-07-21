# VetFlow Project Context

Updated: 2026-07-21

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

## Current Documentation Gap

The repository has useful architecture and audit notes, but public-facing documentation was thin before the GitHub comparison pass. Root README/status/changelog and documentation indexes have now been added.

Remaining documentation work should focus on database docs, module docs, deployment, CI, and short user-facing walkthroughs.
