# Dashboard Module

Code path: `app/Modules/Dashboard`

## Purpose

Presents the operational home screen for the clinic. The dashboard gathers
signals from clinical, stock, product intelligence, sales, service order, and
financial modules so users can see what needs attention without opening each
area first.

## Main Responsibilities

- Render the authenticated dashboard view.
- Aggregate top-level counts for patients, tutors, clinics, appointments,
  products, stock, pet shop services, service orders, sales, and financial
  records.
- List upcoming and same-day appointments.
- List recent patients and tutors.
- Show stock, product, and financial alerts.
- Summarize product intelligence coverage, quality, and recommended actions.
- Register dashboard widgets available to the view.

## Key Classes

| Class | Role |
| --- | --- |
| `DashboardController` | Thin web controller that renders `dashboard.index`. |
| `DashboardDataService` | Composes the full data payload for the view. |
| `DashboardStatsService` | Builds high-level operational counters. |
| `DashboardAlertService` | Combines stock/product alerts with financial alerts. |
| `DashboardProductIntelligenceService` | Summarizes global catalog and local product quality. |
| `DashboardWidgetRegistry` | Lists dashboard widgets enabled in the view. |

## Tables Read

The dashboard does not own tables. It reads from operational modules, including:

- `appointments`
- `patients`
- `tutors`
- `clinics`
- `products`
- `inventory_movements`
- `financial_transactions`
- `sales`
- `petshop_services`
- `service_orders`
- `global_products`
- `global_product_sources`
- `global_product_suggestions`

## Important Behavior

- The dashboard route is `/` and is named `dashboard`.
- The controller delegates data building to `DashboardDataService`.
- Financial stats distinguish paid income, pending income, overdue income,
  pending expenses, overdue expenses, and paid expenses for the current month.
- Stock alerts are based on the Inventory `StockAlertService`.
- Product intelligence actions link users to product diagnostics, local product
  lists, global catalog filters, and pending suggestions.
- `DashboardActivityService` currently returns static activity examples rather
  than an audited activity log.

## Tenant Rules

Dashboard queries should respect each model's tenant behavior. Tenant-sensitive
models are expected to apply their own clinic scoping through their model traits
or repositories. Cross-module dashboard additions should preserve that pattern.

## Permissions

Protected by `dashboard.view`.

## Tests

Relevant access coverage is present in:

- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AuthorizationTest.php`

Operational correctness is indirectly covered by the feature tests for the
modules that feed dashboard totals and alerts.
