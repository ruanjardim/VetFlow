# Product Intelligence Module

Code path: `app/Modules/ProductIntelligence`

## Purpose

Builds a shared product knowledge layer for GTIN/EAN lookup, product enrichment,
source evidence, images, regulatory metadata, and review suggestions.

The module is intentionally separated from clinic-local products. Local products
remain operational records; global products are reusable intelligence records.

## Main Responsibilities

- Look up products by GTIN variants.
- Prefer existing `global_products` before external providers.
- Reuse clinic-local product data when useful.
- Query configured provider tiers in order: `free`, `commercial`, `official`.
- Consolidate source results and confidence metadata.
- Store found products, source payloads, images, and regulatory data.
- Record not-found suggestions for later review.
- Distinguish a confirmed miss from temporary provider unavailability.

## Key Classes

| Class | Role |
| --- | --- |
| `ProductIntelligenceService` | Lookup, consolidation, and persistence orchestration. |
| `GlobalProductCatalogService` | Global catalog operations. |
| `GlobalProductController` | Catalog UI flow. |
| `ProductIntelligenceApiController` | API-style support for intelligence actions. |
| `GlobalProduct` and related models | Shared intelligence records and evidence. |

## Tables

- `global_products`
- `global_product_sources`
- `global_product_images`
- `global_product_regulatory_data`
- `global_product_suggestions`
- `clinic_products`
- `products.global_product_id`

## Status Values

The current global product lifecycle uses status values such as:

- `PENDING`
- `VERIFIED`
- `CONFLICT`

Conflicts are detected when relevant identity fields, such as name, brand, or
manufacturer, disagree with an existing global product.

## Provider Model

Providers are configured in `config/product_lookup.php`. The service sorts
providers by priority and groups them by tier. The current code supports:

- `open_food_facts_family`
- `commercial_gtin_json`

## Important Behavior

- The module normalizes GTIN values before lookup.
- A global catalog hit is returned before external network calls.
- Confirmed external misses are cached for seven days by default, avoiding
  repeated calls for the same GTIN.
- A provider outage is not stored as a product miss and returns a retryable
  unavailable outcome while manual product registration remains enabled.
- Provider calls use bounded connection/response timeouts and at most three
  attempts.
- Source confidence is carried into stored metadata.
- Images are downloaded only from configured host allowlists, without
  redirects, with accepted image MIME types and a 5 MB default limit.
- Not-found lookups can create suggestions instead of failing silently.

Operational limits and image hosts are configurable through the
`PRODUCT_LOOKUP_*` variables in `.env.example`.

## Permissions

Protected by `global-products.manage`.

## Out Of Scope

- Automatic approval of every external result.
- Production-grade regulatory validation workflow.
- Paid provider account management.
