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
- Record append-only human review decisions against the exact replenishment
  evidence visible at the time.
- Prefill a purchase entry from a suggestion while keeping the final quantity,
  supplier, cost, and save decision under operator control.
- Expose a clinic-scoped, read-only history of the resulting purchase decisions
  without returning signed envelopes or internal evidence metadata to the view.
- Summarize valid purchase decisions into transparent clinic validation metrics
  without automatically changing replenishment rules.
- Export an allowlisted, no-cache JSON report of the selected validation cohort
  for human pilot review without exposing signed evidence internals.
- Record append-only, clinic-scoped human reviews of that cohort and mark them
  stale when the allowlisted report facts change.
- Present the period-review trail with safe filters and recalculated evidence
  freshness while keeping hashes and snapshots server-side.

## Key Classes

| Class | Role |
| --- | --- |
| `PurchaseEntryController` | Web and import endpoints. |
| `PurchaseEntryService` | Purchase orchestration, stock entry, and payables. |
| `PurchaseEntryInsightService` | Purchase/product insight support. |
| `ReplenishmentSuggestionService` | Reorder priority, history, confidence, and quantity calculation. |
| `ProductDemandSignalService` | Read-only net demand from completed sales and returns. |
| `SupplierProductSignalService` | Observed delivery, quantity, cost, and lead-time profile per supplier/product. |
| `InventoryCoverageSignalService` | Explainable stock-coverage and observed lead-time comparison. |
| `ReplenishmentEvidenceService` | Canonical evidence snapshots, hashes, and HMAC-signed purchase envelopes. |
| `ReplenishmentPurchaseDecisionService` | Validates signed evidence and measures saved operator adjustments. |
| `ReplenishmentPurchaseHistoryService` | Filters and presents safe purchase-decision comparisons. |
| `ReplenishmentPilotReviewService` | Binds period reviews to allowlisted report evidence, detects staleness, and exposes safe history. |
| `ReplenishmentReviewService` | Append-only human decisions, evidence snapshots, and stale-review detection. |
| `NfeXmlImportService` | Parses NF-e XML payloads. |
| `NfeAccessKeyImportService` | Reuses cached XML by access key. |
| `PurchaseEntry` / `PurchaseEntryItem` | Purchase data models. |

## Tables

- `purchase_entries`
- `purchase_entry_items`
- `replenishment_review_events`
- `replenishment_pilot_review_events`
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
- daily demand is derived from the 90-day net quantity, and current stock is
  divided by that rate to expose estimated coverage days;
- a rupture risk is shown only when projected coverage is less than or equal to
  the reference supplier's observed average lead time, while missing demand or
  lead-time evidence is labeled as insufficient instead of inferred;
- projected stock at receipt and the positive margin or negative gap are shown
  as calculation context, but coverage does not alter quantity, supplier, or
  purchase state automatically.
- an operator can mark the current suggestion as reviewed or keep it on hold;
  hold decisions require an observation;
- every decision appends a new attributed event and preserves a normalized
  snapshot plus its hash instead of overwriting the previous review;
- a review becomes visibly superseded when stock, quantity, cost, demand,
  supplier, lead-time, or coverage evidence changes;
- the protected history remains scoped to the current clinic and has no
  automatic purchase, stock, supplier, or financial side effect.
- review events and purchase prefills now use the same versioned evidence
  snapshot and SHA-256 fingerprint;
- purchase-form metadata carries an HMAC-signed evidence envelope so later
  validation can reject client-side changes before comparing the original
  suggestion with the saved operator decision.
- when a purchase item originating from replenishment is saved, the backend
  compares actual quantity, unit cost, and supplier with the signed snapshot;
- the stored decision metadata classifies the result as kept or adjusted and
  preserves absolute and percentage deltas without changing the purchase;
- invalid or product/clinic-mismatched evidence is marked unavailable and is
  excluded from comparison instead of being trusted.
- a protected decision-history screen shows kept, adjusted, and unavailable
  comparisons with quantity/cost deltas, supplier context, purchase status,
  product/entry search, and links back to the source entry;
- history queries scope through the parent purchase entry because item rows do
  not carry `clinic_id`, and the read model omits signatures, hashes, and raw
  intelligence metadata.
- the decision-history summary counts comparable, kept, adjusted, and
  unavailable records for the current clinic scope;
- history and summary share a validated purchase-date window: the interface
  defaults to the latest 90 days and also offers 30, 180, or the complete
  history, making the cohort behind every metric explicit;
- the same cohort produces a product breakdown, ordered by adjusted decisions,
  with comparable/unavailable totals, adherence, adjustment rate, changed
  fields, and mean absolute quantity/cost deviations for up to ten products;
- a valid replenishment purchase that changes quantity, cost, or the suggested
  supplier requires a controlled adjustment reason; `other` additionally
  requires a note of up to 500 characters;
- the backend derives the reason label from the controlled code, stores the
  reason with decision schema version 2, restores it on purchase editing, and
  exposes only the safe label/note in decision history. Kept or unverifiable
  decisions do not require a reason, and legacy adjusted records remain visibly
  marked when they have no reason;
- an advisory pilot-maturity panel evaluates the selected clinic/date cohort
  against four explicit initial references: 20 comparable decisions, 5
  comparable products, at least 90% valid evidence, and a reason on 100% of
  adjusted decisions. A cohort with no adjustments satisfies the reason gate
  only after reaching the comparable-decision reference;
- the panel reports the next missing action and labels the cohort as empty,
  forming, or operationally ready. It is not a statistical-significance claim,
  supplier approval, or permission to tune rules automatically;
- adherence is `kept / comparable`, while unavailable evidence is excluded
  from that denominator;
- quantity, unit-cost, and supplier adjustment counts use only valid evidence;
- mean quantity and cost deviations are absolute percentages, include valid
  zero-delta decisions, and remain unavailable when there is no valid sample;
- metrics are descriptive validation evidence and do not train, tune, approve,
  or execute a replenishment rule automatically.
- the selected period can be downloaded as a versioned JSON validation report;
  the export is generated from the same summary service as the interface, uses
  an explicit allowlist, and omits signatures, hashes, raw metadata, entry
  details, and free-form adjustment notes.
- a human review or follow-up-required decision for the selected period is
  append-only and bound to a stable subset of that allowlisted report; a change
  to its scope, metrics, maturity, or product breakdown makes the latest review
  visibly stale without changing any replenishment rule.
- the pilot-review history is reverse chronological, clinic-scoped, and
  filterable by period or decision; evidence freshness is recalculated from the
  current report, while stored hashes and snapshots are never rendered.

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
