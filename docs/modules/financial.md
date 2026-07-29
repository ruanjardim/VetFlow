# Financial Module

Code path: `app/Modules/Financial`

## Purpose

Maintains the income/expense ledger used by sales, purchase entries, refunds,
manual financial records, and dashboard/cash-flow summaries.

## Main Responsibilities

- Register income and expense transactions.
- Track due date, payment date, status, amount, payment method, reference, and
  notes.
- Link payables to suppliers and purchase entries.
- Link sale income to completed sales through `sales.financial_transaction_id`.
- Mark transactions as paid.
- Cancel transactions.
- Produce cash-flow summary data.
- Import initial income and expense records through the assisted CSV workflow.

## Key Classes

| Class | Role |
| --- | --- |
| `FinancialTransactionController` | Web CRUD and pay/cancel actions. |
| `FinancialTransactionService` | Filtering, state changes, and cash-flow summary. |
| `FinancialTransactionRepository` | Data access. |
| `FinancialTransaction` | Ledger model. |

## Tables

- `financial_transactions`
- `purchase_entries`
- `suppliers`
- `sales`

## Important Behavior

- `type = income` represents receivables or received money.
- `type = expense` represents payables, costs, or refunds.
- `status = pending` records expected movement.
- `status = paid` records completed movement and should have `paid_at`.
- `status = cancelled` removes the transaction from active operational totals.
- Purchase entries can create multiple installment rows for the same purchase.
- Sale returns can create expense transactions for refunds without cancelling
  the original sale income.
- Financial CSV import accepts Portuguese labels and internal codes, resolves
  optional Suppliers inside the selected clinic, and requires payment dates
  for paid records.
- Imported rows are standalone single-installment transactions and are not
  linked to Purchase Entries.

## Cash Flow Summary

The service calculates:

- paid income and expenses for the current month;
- month balance;
- pending income and expenses;
- overdue income and expenses;
- amounts due in the next seven days;
- upcoming and overdue transaction lists.

## Tenant Rules

Financial transactions are tenant-scoped through `clinic_id`. Supplier and
purchase-entry references must belong to the same selected/current clinic.

## Permissions

Protected by `financial.manage`.

## Tests

Relevant coverage is present in `tests/Feature/OperationalFlowTest.php` and
`tests/Feature/PurchaseAndClinicalFlowTest.php`. The assisted import is covered
by `tests/Feature/ImplementationFinancialCsvTest.php`.
