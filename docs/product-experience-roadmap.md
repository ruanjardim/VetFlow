# Product Experience Roadmap

This roadmap records the product-experience gaps identified during the Control
Center V2 review. It is intentionally separate from the visual redesign: a
polished menu cannot imply that a workflow exists before its domain rules,
permissions, tenant isolation, and tests are implemented.

## Cycle 1: Operational control center

- Establish shared visual tokens, spacing, typography, surfaces, and focus
  states.
- Modernize the authenticated shell while preserving named routes and
  permission gates.
- Reorganize navigation around clinical, agenda, grooming, sales, inventory,
  finance, records, and administration workflows.
- Replace the flat dashboard with an action-oriented control center.
- Validate responsive behavior, accessibility, build output, and module docs.

## Cycle 2: Clinical productivity

- Configurable appointment types and clinical workflow templates.
- Exam attributes, reference ranges, and structured result capture.
- Reusable prescription and document templates with safe clinic scoping.
- Stronger patient timeline, pending-care signals, and vaccine follow-up.
- In-context shortcuts that start from tutor or patient and preserve context.

## Cycle 3: Commerce and inventory depth

- Product groups, manufacturers, and brand management.
- Inventory analysis by turnover, margin, expiry, and replenishment risk.
- Price-list management and customer-specific commercial rules.
- Sales ranking, client balances, receivables, and package visibility in the
  same operating language as the PDV.
- A counter-first PDV mode optimized for barcode scanners and keyboard use.

## Cycle 4: Relationship and self-service

- Customer-origin and campaign attribution.
- Clinic-branded client portal for appointments, documents, vaccines, and
  reminders.
- Guided onboarding, contextual help, and role-based training paths.
- Configurable notification preferences with auditable delivery status.
- Product feedback and support entry points tied to the active screen.

## Experience rules

- Build VetFlow's own workflow and visual identity; competitor screens are
  research references, not implementation specifications.
- Every menu destination must represent a real, permission-protected workflow.
- Prefer actionable summaries over decorative metrics.
- Keep the active clinic, operator, status, and next action understandable at a
  glance.
- Support desktop counters, tablets, and mobile operational use without hiding
  critical actions.
- Use real tenant-scoped data in production and explicitly marked fictitious
  fixtures only in demonstrations and visual QA.
