# PetShop Operations

Code paths: `app/Modules/ServiceOrders`, `app/Modules/PetShopServices`, and the
quick flow in `app/Modules/Sales`.

## Purpose

Supports a small PetShop or grooming operation from customer arrival through
service execution, pickup, payment, and receipt without requiring the clinical
modules.

## Daily Flow

1. Configure active services and their base or size-specific prices.
2. Open a service order for the responsible person and pet.
3. Use **Operation Bath & Grooming** to move the order through `open`,
   `in_service`, `waiting_pickup`, and `finished`.
4. From `waiting_pickup`, open **Receive at POS**. The selected order hydrates
   its responsible person, pet, items, prices, and discount into the quick POS.
5. Select the payment method and complete the sale. Completion closes the
   linked service order and creates the existing sale, financial, and inventory
   effects.

The quick POS can create a responsible person and pet inline only when the
operator also has `tutors.manage` and `patients.manage`. Global operators must
explicitly select an active clinic.

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

- `tests/Feature/ServiceOrderBoardTest.php`
- `tests/Feature/SalesQuickPdvTest.php`
- `tests/Feature/OperationalFlowTest.php`
- `tests/Feature/PurchaseAndClinicalFlowTest.php`
