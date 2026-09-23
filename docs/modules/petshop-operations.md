# PetShop Operations

Code paths: `app/Modules/ServiceOrders`, `app/Modules/PetShopServices`, and the
quick flow in `app/Modules/Sales`.

## Purpose

Supports a small PetShop or grooming operation from customer arrival through
service execution, pickup, payment, and receipt without requiring the clinical
modules.

## Daily Flow

1. Configure active services, their base or size-specific prices, and the
   estimated duration.
2. Mark the groomers and bathers as **Atende banho e tosa** in
   Administration > Users. They become the columns of the agenda. Without any
   flagged user, every active clinic user is shown.
3. Book from **Banho e tosa > Agenda**: click a free slot (professional and
   time are prefilled) or use the form, which lists the professional's free
   slots for the booking duration. A booking is a service order with status
   `scheduled` (or `confirmed`).
4. On arrival, use **Chegou** (check-in, `open`, label "Em espera"), then
   `in_service`, then `waiting_pickup` (label "Animal pronto"). `no_show`
   records a missed booking and frees the slot.
5. From `waiting_pickup`, open **Receber no PDV**. The quick PDV opens with the
   order's responsible person, pet, items, prices, and discount.
6. Select the payment method and complete the sale. Completion closes the
   linked service order and creates the existing sale, financial, and inventory
   effects.

The quick POS can create a responsible person and pet inline only when the
operator also has `tutors.manage` and `patients.manage`. Global operators must
explicitly select an active clinic.

## Scheduling Rules

- The service order is the booking. There is no separate grooming schedule
  record, so the agenda, the board, the order and the sale stay in sync.
- Blocking statuses: `scheduled`, `confirmed`, `open`, `in_service`,
  `waiting_pickup`. A new or edited booking cannot overlap another blocking
  booking of the same professional unless **Encaixe** (`allow_overlap`) is
  checked. Bookings without a professional never conflict.
- Duration: the informed `duration_minutes`, otherwise the sum of the services'
  `duration_minutes`, otherwise 60 minutes.
- Business hours for the grid and slot suggestions come from
  `config/petshop.php` (`PETSHOP_GROOMING_OPENS_AT`, `..._CLOSES_AT`,
  `..._SLOT_MINUTES`; defaults 08:00-18:00 every 30 minutes). This is an
  operational assumption until a per-clinic setting exists.
- Recurrence (create only): weekly, every 2, 3 or 4 weeks, 2 to 12 total
  occurrences, sharing a `recurrence_group`. Every occurrence is conflict
  checked; the whole request fails and lists the conflicting dates.
- A booking is "late" when it is still `scheduled`/`confirmed` after its time.

## Size-Based Prices

Patients have an optional `size` (`small`, `medium`, `large`, `giant`). When
blank, the size is suggested from the weight: up to 10 kg small, up to 25 kg
medium, up to 45 kg large, above that giant (editable assumption in
`App\Modules\Patients\Support\PatientSize`). A service item without an
informed unit price uses the service's price for that size, falling back to
the base price. The form preselects the same price.

## Integrity Rules

- The board and all referenced records are clinic-scoped.
- Cancelled orders are not offered for checkout.
- An order cannot be linked to more than one non-cancelled sale.
- An order with completed/returned sale history cannot be reopened.
- Only open orders with no sale history can be deleted.
- Completed sales keep their clinic, customer, order, date, totals, and source
  immutable; receipts and cancellations use their dedicated flows.

## Permissions

- `petshop-services.manage`: service catalog.
- `service-orders.manage`: orders and operational board.
- `sales.manage`: POS, receipt, cashier, and sale lifecycle.
- `tutors.manage` plus `patients.manage`: inline customer/pet creation.

The standard `atendimento` and `caixa` roles already contain the permissions
needed for the operational and checkout flow.

## Tests

- `tests/Feature/GroomingAgendaTest.php`
- `tests/Feature/ServiceOrderBoardTest.php`
- `tests/Feature/SalesQuickPdvTest.php`
- `tests/Feature/OperationalFlowTest.php`
- `tests/Feature/PurchaseAndClinicalFlowTest.php`
