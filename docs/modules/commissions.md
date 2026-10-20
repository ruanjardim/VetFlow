# Commissions Module

## Scope

The Commissions module configures clinic-scoped percentage rules for existing
system users acting as salespeople and produces an operational preview by
period, an immutable settlement history, and linked financial payables.

## Current Behavior

- Each active rule belongs to one clinic and one active collaborator.
- An active rule cannot overlap another active rule for the same collaborator.
- The commission base can be net sold value or gross profit.
- Recognition can occur on the sale date or on the receipt date.
- Rules can require a sale to be fully paid before it is eligible.
- When receipt-date recognition allows partial settlements, only the portion
  received in the filtered period is used as the commission base.
- The detailed composition identifies the sale or receipt that generated each
  amount and subtracts values already included in prior settlements.
- An authorized operator can close one seller's positive balance for a bounded
  period after explicitly confirming the review and selecting a due date.
- Closing creates one pending expense in the `Commissions` financial category
  and freezes the source rows, rules, values and evidence hash without marking
  the payable as paid.
- Repeating or overlapping a period does not pay the same source twice. A later
  receipt remains available, while a return or cancellation creates a negative
  adjustment against the next positive settlement.
- Settlement rows are clinic-scoped, revisioned per source and append-only.
  The linked financial entry can be paid normally, but its origin amount cannot
  be edited, cancelled or deleted outside the commission flow.
- Every settlement records the responsible user and a tenant-safe audit event.

## Operational Boundary

The module is an operational commission ledger and does not calculate payroll
taxes or replace the clinic's labor/accounting policy. The clinic remains
responsible for rule approval, due dates and the final payment decision.

## Persistence

- `commission_rules`: percentage, basis, recognition and validity.
- `sales_commission_settlements`: one reviewed seller/period closure linked to
  its financial transaction.
- `sales_commission_settlement_items`: immutable source revisions that prevent
  duplicate closure and preserve later adjustments.

## Tests

`tests/Feature/SalesCommissionSettlementTest.php` covers source composition,
idempotency, partial receipts, post-settlement returns, tenant isolation,
financial-origin protection and audit evidence. Existing preview compatibility
remains covered by `tests/Feature/OperationalFlowTest.php`.
