# Purchase Entries Module

Code path: `app/Modules/PurchaseEntries`

## Purpose

Controls purchase receipts for products, including supplier links, item costs,
lot/expiration data, NF-e import support, inventory entry side effects, and
payables in the financial ledger.

## Main Responsibilities

- Create and update purchase entries.
- Normalize item cost, sale price, margin, minimum stock, and intelligence data.
- Apply inventory entries when the purchase entry status is `received`.
- Update product cost, optional sale price, and optional minimum stock.
- Generate financial payables from received purchase entries.
- Split payables into installments.
- Import NF-e XML and use cached XML by access key.
- Keep XML upload available as the operational fallback when the optional
  access-key integration is unavailable.
- Prioritize low-stock products with explainable replenishment suggestions.
- Prefill a purchase entry from a suggestion while keeping the final quantity,
  supplier, cost, and save decision under operator control.

## Key Classes

| Class | Role |
| --- | --- |
| `PurchaseEntryController` | Web and import endpoints. |
| `PurchaseEntryService` | Purchase orchestration, stock entry, and payables. |
| `PurchaseEntryInsightService` | Purchase/product insight support. |
| `ReplenishmentSuggestionService` | Reorder priority, history, confidence, and quantity calculation. |
| `ProductDemandSignalService` | Read-only net demand from completed sales and returns. |
| `SupplierProductSignalService` | Observed delivery, quantity, cost, and lead-time profile per supplier/product. |
| `NfeXmlImportService` | Parses NF-e XML payloads. |
| `NfeAccessKeyImportService` | Reuses cached XML by access key. |
| `PurchaseEntry` / `PurchaseEntryItem` | Purchase data models. |

## Tables

- `purchase_entries`
- `purchase_entry_items`
- `inventory_movements`
- `financial_transactions`
- `suppliers`
- `products`

## Important Behavior

- Entry codes are generated as `ENT-000001`, `ENT-000002`, and so on.
- `received` entries apply stock movements.
- Non-received entries do not apply inventory or payable side effects.
- Updating a purchase entry releases previous inventory/payable side effects and
  then reapplies the current state.
- Deleting a purchase entry releases inventory and payables before soft deletion.
- Installment amounts are split by cents to keep totals balanced.

## Replenishment Suggestions

The replenishment screen includes active products whose configured stock is at
or below their minimum. The first explainable rule set is:

- the baseline quantity raises projected stock to twice the configured minimum;
- only `received` purchase entries from the last 180 days count as history;
- drafts, cancelled entries, soft-deleted entries, and older purchases do not
  influence the result;
- at least two received batches are required before the average historical
  batch can increase the baseline quantity;
- the most recent received cost and supplier are shown as review context;
- confidence is low with zero or one batch, medium with two, and high with three
  or more batches;
- out-of-stock products are shown before the remaining low-stock products.
- completed and returned product sales from the last 90 days provide a separate
  demand signal, with returned quantities deducted;
- draft, cancelled, soft-deleted, and older sales do not contribute to demand;
- net quantity, contributing sale count, monthly average, and returns are shown
  explicitly, but this first signal does not change the suggested quantity.
- received batches are also grouped by supplier and product inside the same
  180-day window, exposing delivery count, received quantity, weighted average
  cost, and latest cost;
- lead time is calculated only when both purchase and receipt dates are valid,
  with sample count, average, and observed range shown explicitly;
- the most recent supplier remains a reference rather than an automatic choice,
  and historical lead time is never presented as a promised delivery date or
  supplier quality score.

The result is a suggestion, not a purchase order. Opening the purchase entry
prefills the product, suggested quantity, reference cost, supplier, purchase
history, and demand-signal metadata, but the operator must review and explicitly
save it.

## Tenant Rules

- Purchase entries are scoped by `clinic_id`.
- Supplier and product references must belong to the selected/current clinic.
- Global users must operate through an explicit clinic selection.
- NF-e import creates or matches supplier/product records inside the selected
  clinic only.

## NF-e Resilience And Safety

- Uploaded and externally resolved XML is limited to 5 MB by default.
- XML containing `DOCTYPE` or entity declarations is rejected before parsing.
- A single NF-e can contain up to 500 items by default.
- Access-key lookup checks the VetFlow cache, configured local archives, and
  then the optional fiscal API.
- Local archive scans are streamed and stop after 1,000 XML files by default.
- The requested 44-digit key must match the key parsed from the resolved XML
  before any Supplier or Product is created.
- Connection timeout, total timeout, and retry count for the fiscal API are
  bounded and configurable.
- Browser diagnostics omit server filesystem paths, tokens, and raw exception
  details.
- Fiscal API outages return a retryable response and direct the operator to
  upload the XML instead of blocking the purchase workflow.

The defaults can be adjusted through the `NFE_*` variables documented in
`.env.example`.

## Permissions

Protected by `purchase-entries.manage`.

## Tests

Relevant coverage is present in `tests/Feature/PurchaseAndClinicalFlowTest.php`
and `tests/Feature/ReplenishmentSuggestionTest.php`.
