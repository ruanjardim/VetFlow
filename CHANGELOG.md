# Changelog

All notable project changes should be recorded here.

This project follows the spirit of Keep a Changelog, with one practical adjustment: unreleased internal work can be summarized by documentation or sprint slices before formal version tags exist.

## [Unreleased]

### Added

- Root README with product overview, stack, setup, validation, and documentation links.
- Root project status file.
- Root agent guidance file.
- Documentation index.
- Stable project context file.
- Module index.
- GitHub VetFlow comparison audit.
- Database notes for clinics, users, and the current employee/access model.
- Module documentation for Products, Product Intelligence, Inventory, Purchase Entries, Sales, Financial, and Clinical Core.
- Continuous integration guide.
- Deployment guide.
- GitHub Actions CI workflow for Laravel tests and frontend build.
- Contribution guide and security policy.
- GitHub issue templates for bug reports, feature requests, and documentation tasks.
- GitHub pull request template with validation and tenant-safety checks.
- Public roadmap with product differentiators and milestones.
- Optional walkthrough demo seeder with fictitious clinic, product, stock, sales, and financial data.
- Visual walkthrough with real application screenshots.
- Standalone module documentation for Dashboard, Suppliers, and Validation.
- Assisted Tutor CSV import with clinic isolation, validation, preview, and transactional confirmation.
- Patient-to-Tutor relationship with clinic-safe validation for manual records.
- Assisted Patient CSV import with Tutor lookup by CPF, preview, validation,
  clinic isolation, and transactional confirmation.
- Assisted Supplier CSV import with CPF/CNPJ normalization and validation.
- Assisted Product CSV import with Supplier trace metadata, identifier
  collision checks, and audited opening Stock.
- Assisted initial Stock CSV import by GTIN or SKU with lot, expiration, cost,
  and Inventory balance traceability.
- Assisted Financial CSV import with Portuguese label normalization, optional
  Supplier resolution, payment-date consistency, and clinic isolation.
- Shared CSV analyzer and value normalizer for catalog, Stock, and Financial
  data blocks.
- Standalone module documentation for Implementation.
- Durable, clinic-scoped summaries for successfully completed assisted imports,
  without retaining imported row contents.
- Assisted Excel `.xlsx` import and templates for Tutors, Patients, Suppliers,
  Products, initial Stock, and Financial records using the existing validation,
  transaction, tenant-isolation, and history rules.
- Actionable dashboard priorities for overdue finance, low stock, service order
  pickup, pending sales, drafts, and same-day appointments.
- Operational user access management with clinic isolation, six standard role
  presets, auditable role-link synchronization, and self-lockout protection.
- Standalone operational documentation for users, roles, and permissions.
- Controlled local walkthrough cleanup/reseed command that preserves unrelated
  clinic records and is blocked outside local/testing environments.
- Release-readiness command covering application configuration, database,
  migrations, logging, queue, storage, and production backup confirmation.
- Operational release checklist with rollback gates and smoke tests.
- Explainable stock replenishment suggestions that combine current balance,
  minimum stock, and the last 180 days of received purchase history, with
  priority, confidence, supplier/cost context, and purchase-entry prefill.
- Standalone operational documentation for the Clinics tenant registry.

### Changed

- Filled the previously empty engineering process document with the current development workflow.
- Replaced the database overview with a migration-aligned current model summary.
- Updated Guzzle and PSR-7 to patched compatible releases after dependency
  auditing identified redirect, cookie, and proxy-header advisories.
- Hardened NF-e XML and access-key intake with bounded parsing and local
  searches, DOCTYPE/entity rejection, key-to-XML verification, safer
  diagnostics, and configurable connection limits.
- Hardened external product lookup with provider availability outcomes, real
  negative caching, bounded retries, and restricted image downloads.
- Split dashboard alert summaries between inventory/catalog and financial
  domains, and aligned low-stock counters with active configured products.
- Restricted clinic-registry routes and navigation to global administrators,
  even when a clinic-scoped role contains `clinics.manage`.

### Pending

- Validate the release checklist against the selected staging/production
  target and record a database restore drill.
