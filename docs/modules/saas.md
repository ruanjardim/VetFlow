# SaaS Foundation

The SaaS module turns the existing clinic tenancy into an explicit commercial entitlement model. It does not change the ownership of operational records: `clinic_id` remains the tenant boundary and roles/permissions remain the user authorization boundary.

## Data model

- `saas_plans` stores editable commercial plans, prices used for display, and user/unit limits.
- `saas_features` is the stable catalog of modules and numeric limits.
- `saas_plan_features` stores the boolean feature composition for each plan.
- `saas_subscriptions` links one current subscription to each clinic and stores lifecycle dates/status.
- `saas_subscription_overrides` stores an exceptional feature or limit value for one subscription.

The final value is resolved in this order:

1. a subscription override, when present;
2. the subscribed plan value;
3. the feature safe default.

An active plan plus an `active` or valid `trial` subscription is required. Suspended, cancelled, not-yet-started, ended, and expired-trial subscriptions resolve to the safe default. Unknown or missing boolean features resolve to disabled; unknown or missing numeric limits resolve to zero. Global platform users are outside tenant plans.

## Authorization

Tenant module access requires both conditions:

```text
role permission AND enabled subscription feature
```

Laravel gates apply the same rule to navigation and view actions. Route groups also use `EnsureTenantHasFeature`, so a direct URL cannot bypass the subscription check. Administrative capabilities such as users, audit, implementation, and branding remain permission-controlled without being sold as operational features.

The `/admin/saas` routes require `saas.manage` and a global user (`clinic_id = null`). Tenant administrators cannot read or change plans, subscriptions, overrides, or another tenant.

## User licenses

`max_users` counts active, non-deleted users in the clinic. Inactive users remain stored and do not consume a license. Creating an inactive user is allowed when the limit is full. Activating a user or moving an active user into a clinic checks the destination limit in the backend before saving.

`max_units` is available to later protect unit creation. This phase exposes and resolves the limit but does not introduce a second unit-management workflow.

## Existing clinics

The foundation migration creates an internal `legacy-internal` plan with all current operational modules enabled and unlimited users/units. Every existing non-deleted clinic receives an active subscription to it. The `Clinic` creation hook also provisions this compatibility subscription for clinics created by older application flows.

The internal plan is visible to platform operators for diagnosis but cannot be edited in the UI. Commercial onboarding must select a commercial plan.

## Commercial seed data

`SaasPlanSeeder` creates three examples only when their slugs do not already exist, so later operator edits are retained:

| Plan | Users | Units | Intended composition |
| --- | ---: | ---: | --- |
| Essencial | 2 | 1 | Cadastros, PDV, products, inventory |
| Profissional | 5 | 1 | Essencial plus agenda, services, purchases, suppliers, finance |
| Completo | 15 | 3 | All current commercial modules |

The example prices and compositions are editable records rather than product rules in code.

## Client onboarding

“Implantar novo cliente” runs one database transaction that creates:

1. the clinic/tenant;
2. its active subscription and optional overrides;
3. the initial active user;
4. the standard Administrator role link;
5. the audit event.

If the role, user, subscription, or any other write fails, the clinic is rolled back as well. Passwords are handled by the existing hashed model cast and never enter audit snapshots.

The establishment can identify itself with either CPF or CNPJ. `clinics.document_type` records which document was selected, while the existing `clinics.cnpj` column stores digits only for backward compatibility. The onboarding and clinic forms apply the corresponding Brazilian display mask and also normalize telephone and WhatsApp numbers before validation.

## Operational rollout

Production rollout requires a database backup, the two SaaS migrations, `AuthorizationSeeder`, and `SaasPlanSeeder`. Existing clinics stay on the compatibility plan until a platform operator deliberately assigns a commercial plan.

## Deliberately deferred

Phase 2 can add billing-provider integration, payment methods, invoices, automated renewals, plan-change workflows, trials and grace periods driven by billing events, unit-limit enforcement at unit creation, customer self-service, and commercial analytics. No payment gateway, PIX, card, boleto, automatic charge, or automatic upgrade exists in this phase.

## Verification

`tests/Feature/SaasFoundationTest.php` covers plan resolution, override precedence, active-user limits, tenant isolation, global administration, route gating, permission plus feature composition, transactional onboarding, rollback, and legacy compatibility.
