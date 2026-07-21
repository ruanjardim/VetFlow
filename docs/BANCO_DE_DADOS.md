# VetFlow Database

This document summarizes the current database model. Detailed table notes live in
`docs/banco/`.

## Design Goals

- Keep operational data separated by clinic through `clinic_id`.
- Keep business identifiers stable with generated codes and ULIDs where useful.
- Prefer soft deletes for operational records that can affect history.
- Preserve audit context through source fields, snapshots, metadata, and event
  records instead of recalculating old business facts from mutable records.
- Keep local development simple with SQLite while keeping the model compatible
  with a MySQL/MariaDB production path.

## Base Tables

| Table | Purpose |
| --- | --- |
| `clinics` | Clinics, units, or clinic networks using VetFlow. |
| `users` | Login identities and user profile/access state. |
| `roles` | Access profiles, global or clinic-specific. |
| `permissions` | Functional permissions used by middleware, Gates, and menus. |
| `role_permission` | Pivot between roles and permissions. |
| `user_roles` | Pivot between users and roles. |

## Clinical Core

| Table | Purpose |
| --- | --- |
| `tutors` | Pet tutors/customers. |
| `patients` | Pets/patients linked to tutors and clinics. |
| `schedules` | Scheduling records. |
| `appointments` | Appointment records. |
| `petshop_services` | Pet shop service catalog. |
| `service_orders` | Operational service orders. |
| `service_order_items` | Products and services attached to service orders. |

## Product, Stock, And Purchase Flow

| Table | Purpose |
| --- | --- |
| `products` | Clinic-local product catalog, price, stock, and GTIN lookup data. |
| `inventory_movements` | Stock ledger for entries, exits, reversals, and adjustments. |
| `suppliers` | Supplier records scoped to clinics. |
| `purchase_entries` | Purchase receipts and NF-e imported entries. |
| `purchase_entry_items` | Purchase-entry items with lot, cost, price, and intelligence snapshots. |
| `product_lookup_catalogs` | Legacy/local lookup cache for GTIN enrichment. |
| `global_products` | Shared product intelligence catalog. |
| `global_product_sources` | Source-level evidence for global product records. |
| `global_product_images` | Product images collected from external or manual sources. |
| `global_product_regulatory_data` | Regulatory or pharmaceutical metadata. |
| `global_product_suggestions` | Review queue for missing/conflicting product data. |
| `clinic_products` | Clinic-specific association with global products. |

## Sales And Finance

| Table | Purpose |
| --- | --- |
| `sales` | Sale header, totals, status, margin, and side-effect flags. |
| `sale_items` | Product, service, and custom sale lines with price/cost snapshots. |
| `sale_payments` | Payment records by method, installments, and references. |
| `sale_events` | Operational event trail for completion, stock exits, returns, refunds, and cancellation. |
| `cash_register_closures` | Cashier closure summaries and differences. |
| `financial_transactions` | Income/expense ledger, including sales income and purchase payables. |

## Tenant Boundary

The current convention is:

- Operational records include `clinic_id` whenever the data belongs to a clinic.
- Eloquent models that use tenant scoping implement `tenantColumn()` returning
  `clinic_id`.
- Users with `clinic_id = null` are global users and must explicitly select or
  operate inside a clinic context for tenant-sensitive flows.
- Tests cover cross-clinic rejection for sales, purchase entries, inventory,
  financial records, schedules, appointments, and NF-e import.

## Documentation Map

- [Clinics](banco/01-clinics.md)
- [Users](banco/02-users.md)
- [Roles](banco/03-roles.md)
- [Permissions](banco/04-permissions.md)
- [Employee/access model](banco/05-employees.md)
