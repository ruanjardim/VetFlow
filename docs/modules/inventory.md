# Inventory Module

Code path: `app/Modules/Inventory`

## Purpose

Maintains the stock ledger for clinic products. Inventory is represented by
movements, and product stock is derived operationally by applying those
movements to `products.stock_quantity`.

## Main Responsibilities

- Register manual stock entries and exits.
- Apply stock effects when movements are created or updated.
- Reverse stock effects when movements are updated or deleted.
- Keep `balance_before` and `balance_after` for traceability.
- Store lot and expiration data when available.
- Connect stock movements to sales and purchase entries.
- Provide low-stock and stock-alert support.

## Key Classes

| Class | Role |
| --- | --- |
| `InventoryMovementController` | Web CRUD for stock movements. |
| `InventoryMovementService` | Applies and reverses stock effects. |
| `ProductLotService` | Lot allocation support for sales. |
| `StockAlertService` | Low-stock alert support. |
| `InventoryMovement` | Stock ledger model. |

## Tables

- `inventory_movements`
- `products`

## Movement Sources

Known movement sources include:

- `manual`
- `purchase_entry`
- `sale`
- `sale_return`
- `sale_cancellation`

## Important Behavior

- `entry` increases product stock.
- `exit` decreases product stock.
- `lot_assignment` does not change product stock.
- Updating a movement reverses the previous effect before applying the new one.
- Deleting a movement reverses the effect and then soft-deletes the movement.
- Sales and purchase entries should call inventory services instead of changing
  product stock directly.

## Tenant Rules

Inventory movements are tenant-scoped through `clinic_id`. Requests reject
products from another clinic before side effects are applied.

## Permissions

Protected by `inventory.manage`.

## Tests

Relevant coverage is present in `tests/Feature/OperationalFlowTest.php`.
