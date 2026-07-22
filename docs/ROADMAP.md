# VetFlow Roadmap

Updated: 2026-07-21

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

## Near-Term Priorities

1. Keep CI green with backend tests and frontend build.
2. Add screenshots or a short visual walkthrough for the main screens.
3. Document Dashboard, Suppliers, and Validation as standalone modules.
4. Harden product lookup and NF-e flows around unavailable external services.
5. Improve dashboard insight copy for stock, financial, schedule, and sales signals.
6. Add release notes when a stable demo or first production pilot is defined.

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

- Guided onboarding for a new clinic.
- Smart reorder suggestions from stock and purchase history.
- Margin and profitability insights by service/product category.
- Appointment reminders and follow-up workflows.
- Audit trail for sensitive operational changes.
- Role presets for administrator, veterinarian, attendant, stock, cashier, and finance users.
