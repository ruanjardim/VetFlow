# Sales Module

Code path: `app/Modules/Sales`

## Purpose

Handles product/service sales, payments, cashier summaries, stock exits,
financial income, returns, refunds, cancellations, and sale event history.

## Main Responsibilities

- Create sales from direct items or service-order items.
- Calculate subtotal, discounts, additions, total, paid amount, change, cost,
  gross profit, and margin.
- Snapshot product/service fields into sale items.
- Register payment methods and card/acquirer references.
- Register later receipts for completed sales that remain pending or partial.
- Apply stock exits when a sale is completed.
- Create financial income when a sale is completed.
- Finish linked service orders after completed sales.
- Cancel sales and reverse stock/financial effects.
- Process partial/full returns and refunds.
- Generate cashier summaries and closure records.
- Group sales, receipts, balances, and gross margin by the operator responsible
  for each sale.

## Key Classes

| Class | Role |
| --- | --- |
| `SaleController` | Web sales, cancellation, returns, cashier, and closure flows. |
| `SaleService` | Sale orchestration and side effects. |
| `SaleRepository` | Data access. |
| `Sale`, `SaleItem`, `SalePayment`, `SaleEvent` | Sale domain models. |
| `CashRegisterClosure` | Cashier closure model. |

## Tables

- `sales`
- `sale_items`
- `sale_payments`
- `sale_events`
- `cash_register_closures`
- `inventory_movements`
- `financial_transactions`

## Important Behavior

- Sale codes are generated as `VEN-000001`, `VEN-000002`, and so on.
- Completed sales apply stock and financial effects once using
  `stock_applied` and `financial_applied`.
- Draft sales can be updated before effects are applied.
- Sale item snapshots protect historical margin/reporting data from later
  product edits.
- Product exits use lot allocation when available.
- Cancellation is idempotent and restores remaining stock.
- Partial returns create stock entries and financial refund expenses without
  cancelling the original income record.
- Cashier summary uses completed sales, paid payments, refunds, change, and
  pending totals for the selected period.
- Cashier closure reconciles each supported payment method separately. The
  expected value deducts refunds recorded in that method and, for cash, also
  deducts change. Expected, counted, and difference values are preserved in
  closure metadata so existing closure columns and records remain compatible.
- Pending, cancelled, and refunded payment rows never compose the amount paid
  on a sale or the cash received by the cashier.
- The cashier report exposes operator performance for operational review. It is
  not a commission calculation: commission rates, eligibility, and settlement
  rules must be configured in a future dedicated step.
- Later receipts are recorded as separate paid payment rows and keep an event
  in the sale history. The linked financial income becomes paid only when the
  sale balance is fully settled.

## Status Concepts

Sale statuses include values such as:

- `draft`
- `completed`
- `cancelled`
- `returned`

Payment statuses include values such as:

- `pending`
- `partial`
- `paid`
- `cancelled`
- `refunded`

## Tenant Rules

Sales are tenant-scoped through `clinic_id`. Requests reject products, tutors,
patients, and service-order references from another clinic before side effects
are applied.

## Permissions

Protected by `sales.manage`.

## Tests

Relevant coverage is present in `tests/Feature/OperationalFlowTest.php`.
