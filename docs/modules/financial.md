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
- Provide a clinic-scoped operational center for accounts payable and
  receivable by due-date period, type and computed situation.
- Produce a monthly managerial result by category, with cash and operational
  competence views and a formula-safe CSV export.
- Maintain monthly income and expense budgets by category and compare them with
  cash or operational-competence actuals.
- Import initial income and expense records through the assisted CSV workflow.

## Key Classes

| Class | Role |
| --- | --- |
| `FinancialTransactionController` | Web CRUD and pay/cancel actions. |
| `FinancialTransactionService` | Filtering, state changes, and cash-flow summary. |
| `FinancialObligationController` | Due-date center and global-clinic selection. |
| `FinancialObligationService` | Read-only accounts payable/receivable totals and filtered rows. |
| `FinancialTransactionRepository` | Data access. |
| `FinancialTransaction` | Ledger model. |
| `FinancialCategoryController` | Category listing, creation, and editing. |
| `FinancialCategoryService` | Default categories and stable automatic classification. |
| `FinancialCategory` | Clinic-scoped chart-of-accounts category. |
| `FinancialManagerialResultController` | Monthly result screen and safe CSV export. |
| `FinancialManagerialResultService` | Cash/competence aggregation by category using exact cents. |
| `FinancialBudgetController` | Monthly budget screen, safe CSV export, actual-composition detail and update/copy actions. |
| `FinancialBudgetService` | Clinic-scoped comparison, 12-month history, transaction drilldown and audit history. |
| `FinancialBudget` | One planned amount per clinic, category and monthly competence. |

## Tables

- `financial_transactions`
- `financial_categories`
- `financial_budgets`
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

## Accounts Payable And Receivable

The protected `Contas a pagar e receber` screen is a read-only operational
view over the existing financial ledger.

- The default period runs from the first day of the month one year ago through
  the end of the following month, keeping older arrears visible without an
  unbounded initial query.
- Filters cover establishment for global users, due-date period, income or
  expense type, operational situation and overdue-age band.
- Open, overdue, due-today and upcoming classifications are derived from the
  stored status together with the due date, so an old `pending` row remains
  visibly overdue without requiring a background mutation.
- Summary cards always reconcile the selected period and type; the situation
  filter only narrows the detailed rows.
- Open overdue amounts are divided into five explicit bands: up to 5 days,
  6-15, 16-30, 31-60 and more than 60 days. Each band links to the exact
  transaction composition and displays the number of overdue days.
- The filtered composition can be exported as a private, no-cache,
  formula-safe UTF-8 CSV without exposing transactions from another clinic.
- An operator can append a reviewed or follow-up-required decision with a
  bounded note. The record stores a safe filter and summary snapshot with an
  integrity hash; the visible history is append-only and clinic-scoped.
- Active transactions without a due date are counted explicitly as pending
  classification work instead of being silently assigned to a period.
- Every query is constrained to one clinic, including the explicit clinic
  selector used by global administrators.

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

## Monthly Budget

The protected `Orçamento mensal` screen stores one planned amount for each
financial category, clinic and month, then compares that plan with the same
realized views used by the managerial result.

- Budget values are independent from transactions and never create, pay,
  cancel or change a ledger entry.
- `Caixa` actuals include only paid transactions by payment date.
- `Competência operacional` actuals include non-cancelled transactions by due
  date and remain an operational view rather than an accounting definition.
- Income categories report whether the realized amount reached the planned
  target. Expense categories report whether the realized amount stayed within
  the planned limit.
- Legacy movements without a category remain visible as non-editable,
  unplanned rows so the comparison still reconciles with the managerial result.
- Inactive categories remain visible to preserve and revise historical plans.
- The previous month's positive values can be copied into empty categories of
  the selected month. Existing destination values are always preserved.
- An automatic alert summary identifies expense categories above their monthly
  limit and movements that have no planned amount.
- The complete comparison can be exported as a private, no-cache and
  formula-safe UTF-8 CSV using exactly the selected clinic, month and basis.
- Every realized value links to the exact clinic-scoped ledger entries that
  compose it. The detail preserves the selected month, basis and category,
  including the explicit legacy `Sem categoria` group.
- A rolling 12-month table compares planned and realized income, expenses and
  balance without changing the selected month's editable budget.
- The screen exposes the latest budget updates and copies for the selected
  competence, including responsible user, time, changed value and source month
  when applicable. The audit list never crosses clinic boundaries.
- Submitted categories must belong to the selected clinic. Budget changes are
  recorded in the audit trail without exposing unrelated clinic data; copied
  values keep the source and destination competencies in their audit metadata.

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
`tests/Feature/FinancialManagerialResultTest.php`. Monthly budget comparison,
Brazilian currency normalization, copy safety, alerts, private CSV export,
exact transaction composition, 12-month history, audit display and tenant
isolation are covered by `tests/Feature/FinancialBudgetsTest.php`.
The payable/receivable center, computed due-date states, aging composition,
private CSV, append-only reviews, validation and global clinic selection are
covered by `tests/Feature/FinancialObligationCenterTest.php`.
