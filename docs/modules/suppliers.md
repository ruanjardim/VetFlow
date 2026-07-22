# Suppliers Module

Code path: `app/Modules/Suppliers`

## Purpose

Maintains supplier records used by purchase entries, NF-e import, and financial
payables. Suppliers identify who provides products or services to a clinic and
give purchase/financial flows a consistent reference.

## Main Responsibilities

- Create, update, list, and soft-delete suppliers.
- Store supplier identity, document, contact person, email, phone, WhatsApp,
  city, state, notes, and active status.
- Scope supplier records by clinic when a clinic context is active.
- Provide active suppliers to purchase entry, NF-e, and financial screens.
- Support supplier matching and creation during NF-e XML import.

## Key Classes

| Class | Role |
| --- | --- |
| `SupplierController` | Web CRUD controller using shared base CRUD behavior. |
| `SupplierService` | Module service backed by the supplier repository. |
| `SupplierRepository` | Tenant-aware data access through `BaseRepository`. |
| `SupplierRepositoryInterface` | Repository contract registered in the service provider. |
| `StoreSupplierRequest` | Validation rules for create operations. |
| `UpdateSupplierRequest` | Reuses create validation rules for updates. |
| `Supplier` | Tenant-scoped, soft-deletable supplier model. |

## Tables

- `suppliers`
- `purchase_entries`
- `financial_transactions`

## Important Behavior

- Supplier routes are registered through `Route::resource('suppliers', ...)`
  except for `show`.
- `active` is required by the request and cast to boolean on the model.
- `document` is optional and indexed, but is not currently unique.
- Supplier deletion uses soft deletes.
- Purchase entries and financial records can reference suppliers.
- NF-e import can match suppliers by normalized document or create missing
  suppliers inside the selected clinic.

## Tenant Rules

Suppliers use `BelongsToClinicTenant` and `clinic_id` as the tenant column.
`BaseRepository` applies the current `TenantContext` to list, find, create, and
update operations. Purchase and financial flows must use suppliers from the
selected/current clinic.

## Permissions

Protected by `suppliers.manage`.

## Tests

Relevant coverage is present in:

- `tests/Feature/ClinicTenantIsolationTest.php`
- `tests/Feature/OperationalFlowTest.php`
- `tests/Feature/PurchaseAndClinicalFlowTest.php`

These tests cover tenant scoping and reject supplier references from another
clinic in operational flows.
