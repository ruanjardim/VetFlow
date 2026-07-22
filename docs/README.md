# VetFlow Documentation

This directory contains the working documentation for VetFlow.

## Core Documents

- [Project context](PROJECT_CONTEXT.md)
- [Visual walkthrough](WALKTHROUGH.md)
- [System architecture](ARQUITETURA.md)
- [Backend architecture](backend-architecture.md)
- [Frontend architecture](frontend-architecture.md)
- [Engineering process](engineering-process.md)
- [Database overview](BANCO_DE_DADOS.md)
- [Continuous integration](ci.md)
- [Deployment guide](deployment.md)
- [Roadmap](ROADMAP.md)

## Database Notes

- [Clinics](banco/01-clinics.md)
- [Users](banco/02-users.md)
- [Roles](banco/03-roles.md)
- [Permissions](banco/04-permissions.md)
- [Employees](banco/05-employees.md)

The employee/access note documents the current decision to use `users`,
`roles`, and `permissions` instead of a separate `employees` table.

## Modules

- [Module index](modules/_INDEX.md)
- [Products](modules/products.md)
- [Product Intelligence](modules/product-intelligence.md)
- [Inventory](modules/inventory.md)
- [Purchase Entries](modules/purchase-entries.md)
- [Sales](modules/sales.md)
- [Financial](modules/financial.md)
- [Clinical Core](modules/clinical-core.md)

## Audits

- [Repository baseline](audits/repository-baseline.md)
- [Authentication hardening](audits/authentication-hardening-sprint-1.md)
- [Authorization sprint](audits/authorization-sprint-2.md)
- [Reconciliation sprint](audits/reconciliation-sprint-0-1.md)
- [GitHub VetFlow comparison](audits/github-vetflow-comparison-2026-07-21.md)

## Repository Governance

- [Contributing guide](../CONTRIBUTING.md)
- [Security policy](../SECURITY.md)
- GitHub issue and pull request templates live under `../.github/`.

## Documentation Rules

- Stable project facts belong in `docs/PROJECT_CONTEXT.md`.
- Current mutable status belongs in root `STATUS.md`.
- Architecture rules belong in the architecture documents.
- Module behavior belongs in `docs/modules/`.
- Audit findings belong in `docs/audits/`.
