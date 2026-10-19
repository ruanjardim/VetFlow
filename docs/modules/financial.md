# Financial Module

Code path: `app/Modules/Financial`

## Purpose

Maintains the income/expense ledger used by sales, purchase entries, refunds,
manual financial records, and dashboard/cash-flow summaries.

## Main Responsibilities

- Register income and expense transactions.
- Track due date, payment date, status, amount, payment method, reference, and
  notes.
- Maintain a clinic-scoped chart of financial categories for income and
  expenses.
- Automatically classify sales, purchase entries, commissions, refunds, card
  fees, cash expenses, and assisted financial imports.
- Link payables to suppliers and purchase entries.
- Link sale income to completed sales through `sales.financial_transaction_id`.
- Keep sale-generated income read-only in Finance; receipts, cancellations, and
  returns must be registered from the originating sale so cash, commission
  previews, stock, and financial status remain synchronized.
- Mark transactions as paid.
- Cancel transactions.
- Produce cash-flow summary data.
- Produce a monthly managerial result by category, with cash and operational
  competence views and a formula-safe CSV export.
- Import initial income and expense records through the assisted CSV workflow.

## Key Classes

| Class | Role |
| --- | --- |
| `FinancialTransactionController` | Web CRUD and pay/cancel actions. |
| `FinancialTransactionService` | Filtering, state changes, and cash-flow summary. |
| `FinancialTransactionRepository` | Data access. |
| `FinancialTransaction` | Ledger model. |
| `FinancialCategoryController` | Category listing, creation, and editing. |
| `FinancialCategoryService` | Default categories and stable automatic classification. |
| `FinancialCategory` | Clinic-scoped chart-of-accounts category. |
| `FinancialManagerialResultController` | Monthly result screen and safe CSV export. |
| `FinancialManagerialResultService` | Cash/competence aggregation by category using exact cents. |

## Tables

- `financial_transactions`
- `financial_categories`
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
- Each clinic receives a small default chart of categories lazily on first
  financial use. Stable internal codes classify system-generated transactions;
  display names remain editable.
- A category type is immutable after creation so historical income/expense
  reporting cannot be silently inverted. Categories are deactivated instead
  of deleted; inactive categories remain visible on old records and may still
  receive system-generated entries through their stable internal code.
- A manual transaction without an explicit category is assigned to `Outras
  entradas` or `Outras despesas` according to its type.
- Category filters are available in the financial ledger. A submitted category
  must belong to the current clinic and match the transaction type.
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

## Monthly Managerial Result

The protected `Resultado gerencial` screen compares income, expenses and the
resulting balance for one month, grouped by financial category.

- `Caixa` includes only paid transactions and uses `paid_at`.
- `Competência operacional` includes non-cancelled transactions and uses the
  due date. It is an operational forecast/read model and does not replace the
  accounting competence defined by the clinic's accountant.
- Transactions without the date required by the selected view are excluded
  and counted visibly as pending classification work.
- Category totals and shares use exact cents, remain clinic-scoped and include
  a visible `Sem categoria` group for legacy records.
- The CSV export uses the same filters, carries private/no-cache headers and
  neutralizes spreadsheet-formula prefixes in textual fields.

## Tenant Rules

Financial transactions are tenant-scoped through `clinic_id`. Supplier and
purchase-entry and financial-category references must belong to the same
selected/current clinic.

## Permissions

Protected by `financial.manage`.

## Tests

Relevant coverage is present in `tests/Feature/OperationalFlowTest.php` and
`tests/Feature/PurchaseAndClinicalFlowTest.php`. The assisted import is covered
by `tests/Feature/ImplementationFinancialCsvTest.php`. Category defaults,
immutability, tenant isolation, type validation and filtering are covered by
`tests/Feature/FinancialCategoriesTest.php`. The monthly cash/competence
result, category grouping, tenant isolation and safe CSV are covered by
`tests/Feature/FinancialManagerialResultTest.php`.
