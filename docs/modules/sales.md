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
- Present realized gross profitability by period, item type, category, and
  catalog item.
- Present a product ABC analysis from return-adjusted net revenue with current
  stock value as read-only context.
- Save PDV carts as quotes (orçamentos) and convert them into sales.
- Classify sales and quotes by sale type, with delivery address and fee for
  delivery and shipping types.
- Keep payment methods per clinic (one per card machine and type) and snapshot
  the card fee, net amount, and expected settlement date on each payment.
- Present the expected card settlements (recebíveis) per installment.

## Key Classes

| Class | Role |
| --- | --- |
| `SaleController` | Web sales, cancellation, returns, cashier, and closure flows. |
| `SaleService` | Sale orchestration and side effects. |
| `SaleQuoteController` | Quote list, save/update from the PDV, printable page, and cancellation. |
| `SaleQuoteService` | Quote codes, items and totals, conversion hooks, and the WhatsApp summary. |
| `SaleType` | Sale type catalog and delivery normalization. |
| `PaymentMethodController` | Payment method admin and the card receivables report. |
| `PaymentMethodService` | Default methods, payment snapshots (fee, net, settlement), and installment schedules. |
| `SaleProfitabilityService` | Return-adjusted gross profitability reporting. |
| `ProductAbcAnalysisService` | Product revenue ranking, cumulative ABC bands, filters, and pagination. |
| `SaleRepository` | Data access. |
| `Sale`, `SaleItem`, `SalePayment`, `SaleEvent` | Sale domain models. |
| `SaleQuote`, `SaleQuoteItem` | Quote domain models. |
| `PaymentMethod` | Clinic payment method (card machine, fees, settlement, installments). |
| `CashRegisterClosure` | Cashier closure model. |

## Tables

- `sales`
- `sale_items`
- `sale_quotes`
- `sale_quote_items`
- `sale_payments`
- `payment_methods`
- `sale_events`
- `cash_register_closures`
- `inventory_movements`
- `financial_transactions`

## Important Behavior

- Sale codes are generated as `VEN-000001`, `VEN-000002`, and so on.
- Completed sales apply stock and financial effects once using
  `stock_applied` and `financial_applied`.
- Draft sales can be updated before effects are applied.
- The new-sale screen is a counter-oriented PDV with a dynamic cart, clinic-scoped
  name/SKU/barcode/GTIN search, optional customer and pet, keyboard shortcuts,
  and a multi-method receipt dialog. The previous full form remains available
  as the advanced/comanda path and continues to serve sale editing.
- PDV completion requires full payment. Any change must be covered by cash
  received; suspended sales stay as editable drafts. Completion continues
  through the existing SaleService stock, financial, and audit flow.
- A completed PDV sale opens its receipt with a direct new-sale action.
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
- The profitability report uses the price and cost snapshots stored on sale
  items. Sale-level discounts and additions are allocated proportionally among
  the items, and returned quantities remove both their revenue and product
  cost from the realized result.
- Profitability is gross and operational: taxes, general expenses, and
  commissions are not deducted. Services and custom lines do not currently
  carry a cost snapshot, while products with a zero cost are highlighted for
  review.
- Historical periods reflect returns registered later because the report
  presents the current realized outcome of the sales that originated in the
  selected period.
- Product ABC analysis supports explicit 30-, 90-, and 180-day windows. It
  orders product snapshots by realized net revenue after refunds, then assigns
  each item according to the cumulative share before that item: class A starts
  below 80%, B from 80% to below 95%, and C from 95% onward. The item crossing
  a threshold closes the band it started in, and zero-revenue items are C.
- ABC filters never recalculate the original curve. Current product stock and
  cost value are context only; the analysis does not change prices, suppliers,
  purchases, product status, or inventory movements.

## Quotes (Orçamentos)

- The PDV has a **Venda | Orçamento** switch. In quote mode the cart is saved
  with `sales.quotes.store` instead of being received; payment and suspension
  are hidden. `sales.create?mode=quote` opens the PDV directly in quote mode.
- A quote gets a global code `ORC-000001`, the logged user as seller, the
  customer and pet (both optional), the sale type and delivery data, notes,
  and a validity date. The default validity is
  `config('sales.quote_validity_days', 7)` days and can be changed per quote.
- A quote never moves stock, never creates financial records or commissions,
  and does not validate stock. Packages are not quoted (as in SimplesVet).
- Status: `open`, `converted`, or `cancelled`. An open quote past its validity
  is displayed as **Vencido** (expired); it can still be converted with a
  warning, keeping the quoted prices.
- **Converter em venda** opens `sales.create?quote_id=` with the quote's
  customer, pet, items, prices, discounts, additions, sale type, and delivery
  data, plus a hidden `sale_quote_id`. Completing that sale marks the quote as
  `converted` (with `converted_sale_id` and `converted_at`); cancelling the
  sale reopens it.
- A quote backs at most one non-cancelled sale. While a suspended (draft) sale
  from the quote exists, the quote cannot be edited, cancelled, or converted
  again.
- Open quotes can be edited in the PDV (`sales.create?quote_id=&mode=quote`)
  and cancelled with an optional reason; cancelled and converted quotes stay
  in the history.
- The quote page is print-friendly (actions and alerts hidden when printing)
  and offers a `wa.me` link with a plain-text summary, using the customer's
  secondary phone (WhatsApp) or main phone.

## Sale Type and Delivery

- `sale_type` mirrors the SimplesVet options, which are also the NF-e buyer
  presence indicator: `in_store` (presencial, consumidor final — default),
  `in_store_resale`, `delivery` (delivery ou atendimento domiciliar),
  `delivery_resale`, `online_shipping`, and `phone_shipping`.
- Only the delivery and shipping types keep `delivery_address` and
  `delivery_fee`; the other types store an empty address and a zero fee.
- The PDV prefills the address from the selected customer's registration and
  keeps manual edits. The fee is added to the total:
  `total = items + additions + delivery_fee - discount`, and the PDV full
  payment rule includes it.
- Receipts show the type, the address, and the fee. The sales history shows a
  short type label.
- The delivery fee is not item revenue: item profitability and the ABC
  analysis exclude it from the proportional allocation of sale-level
  adjustments.
- After stock/financial effects are applied, the type and delivery data are
  frozen with the other totals.

## Payment Methods (Formas de Pagamento)

- Each clinic keeps its own payment methods, one per card machine and type, as
  in SimplesVet ("Rede Visa Crédito", "PagSeguro Débito"). A method has a kind
  (`cash`, `pix`, `debit_card`, `credit_card`, `transfer`, `other`), the
  machine/acquirer, the card brand (card kinds only), the upfront fee, the
  installment fee (credit, from 2 installments on; empty means the upfront
  fee), the settlement days, the maximum installments (credit only, up to
  24), whether the NSU/reference is required, the order in the PDV, and an
  active flag. Methods are deactivated, never deleted.
- The first time a clinic opens the PDV (or the admin page), it gets the six
  previous methods as defaults: Dinheiro, Pix, Cartão de débito (1 day),
  Cartão de crédito (30 days, up to 12x), Transferência, and Outro, all with a
  zero fee, so nothing changes until the clinic registers its machines.
- The kind is still stored in `sale_payments.method`, so the cash, change, and
  closure rules keep working by kind. A payment that only informs a kind
  (legacy forms and API callers) uses the first active method of that kind.
- Each payment snapshots `payment_method_id`, the acquirer and brand, the
  installments (clamped to the method), `fee_amount` (amount × fee of the
  installments), `net_amount`, and `expected_settlement_date` (paid date +
  settlement days, for the first installment). Changing a method later does
  not change old payments. Payments recorded before this feature have no
  method, fee, or settlement date.
- Validation (PDV, advanced form, and later receipt): the method belongs to
  the sale clinic and is active, installments do not exceed the method, and a
  method that requires the NSU needs it to finish a sale or register a later
  receipt. Suspending a sale does not require the NSU.
- The PDV shows one shortcut per active method of the selected clinic; card
  rows show the installments (1x to the method maximum), the brand when the
  method has none, and the NSU field.
- **Recebíveis de cartão** (`sales.receivables`) lists card payments of
  non-cancelled sales, one row per installment: the first installment on the
  expected settlement date and the others every 30 days, or all of them on
  that date when the method is set to anticipation (`metadata.installment_settlement
  = upfront`). Cents that do not divide evenly go to the last installment.
  The default period is the next 60 days.
- The cashier summary groups receipts by method name with fees and net
  amount, and shows the card fees of the period. The cashier closure still
  reconciles by kind; posting the fees as one expense per machine at closing
  comes with the cash sessions (plan item 4).

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

Protected by `sales.manage`. The payment method admin and the card
receivables report use `payment-methods.manage` (administrator and financial
roles), mapped to the `pdv` plan feature.

## Tests

Relevant coverage is present in:

- `tests/Feature/OperationalFlowTest.php`
- `tests/Feature/ProductAbcAnalysisTest.php`
- `tests/Feature/SaleQuotesAndSaleTypeTest.php`
- `tests/Feature/PaymentMethodsTest.php`
