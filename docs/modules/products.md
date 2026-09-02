# Products Module

Code path: `app/Modules/Products`

## Purpose

Manages the clinic-local product catalog used by inventory, purchase entries,
sales, and product intelligence. A product is the clinic's sellable or stockable
record, even when it is linked to a shared global product.

## Main Responsibilities

- Create and update local products.
- Store price, cost, stock, minimum stock, unit, category, brand, manufacturer,
  image, GTIN/barcode, and lookup metadata.
- Normalize GTIN/barcode values.
- Enrich products from lookup providers and from the global product catalog.
- Link local products to `global_products` when a valid GTIN is available.
- Store uploaded product images under the public disk.
- Import clinic Products from CSV with optional Supplier trace metadata and
  audited initial Stock entries.
- Present a read-only Pricing Radar with explicit catalog-margin signals and
  current stock exposure.

## Key Classes

| Class | Role |
| --- | --- |
| `ProductController` | Web CRUD and product actions. |
| `ProductLookupController` | Lookup/enrichment endpoints. |
| `ProductService` | Product creation, update, enrichment, and global linking. |
| `ProductLookupService` | Product lookup orchestration. |
| `ProductIntelligenceAuditService` | Diagnostics/audit support. |
| `ProductPricingRadarService` | Clinic-scoped pricing classification, stock exposure, filters, and pagination. |
| `ProductRepository` | Product data access. |
| `Gtin` | GTIN normalization and validation helper. |

## Tables

- `products`
- `product_lookup_catalogs`
- `global_products` through optional `products.global_product_id`

## Important Behavior

- Product records are tenant-scoped through `clinic_id`.
- `gtin` and `barcode` are normalized together when possible.
- `stock_quantity` is changed by inventory movements, not directly by sales or
  purchases.
- Product enrichment should not overwrite commercial decisions blindly. Price,
  stock, and minimum stock remain local clinic data.
- `lookup_metadata` stores trace context for enrichment decisions.
- CSV Supplier references are stored in `lookup_metadata` because Products do
  not own a direct Supplier foreign key.
- A positive CSV `estoque_atual` is applied through
  `InventoryMovementService`, preserving the Inventory ledger.
- The Pricing Radar classifies every active product once, in this priority
  order: missing cost, missing sale price, below cost, break-even, positive
  gross margin below the initial 20% reference, and adequate margin.
- Cadastral gross margin is `(sale price - current cost) / sale price`; markup
  is `(sale price - current cost) / current cost`. Missing cost or price keeps
  both unavailable instead of presenting an invented percentage.
- Current stock value, potential revenue, and known potential gross margin use
  non-negative current balances. They are review context only and exclude
  discounts, taxes, commissions, expenses, and historical sale-item costs.
- Pricing filters never change the consolidated cards and the radar never
  updates price, cost, stock, purchase, supplier, or financial records.

## Integrations

- Purchase entries can update product cost, sale price, minimum stock, and stock.
- Sales consume products and snapshot product fields into sale items.
- Inventory movements maintain the stock ledger.
- Product Intelligence maintains shared product evidence and reusable product
  metadata.

## Permissions

Protected by `products.manage`. Global catalog screens use
`global-products.manage`.

## Tests

Relevant coverage is present in:

- `tests/Feature/OperationalFlowTest.php`
- `tests/Feature/PurchaseAndClinicalFlowTest.php`
- `tests/Feature/ProductPricingRadarTest.php`
- tenant and authorization tests for protected routes.
