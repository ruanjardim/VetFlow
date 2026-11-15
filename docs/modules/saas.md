# SaaS Foundation

The SaaS module turns the existing clinic tenancy into an explicit commercial entitlement model. It does not change the ownership of operational records: `clinic_id` remains the tenant boundary and roles/permissions remain the user authorization boundary.

## Data model

- `saas_plans` stores editable commercial plans, prices used for display, and user/unit limits.
- `saas_features` is the stable catalog of modules and numeric limits.
- `saas_plan_features` stores the boolean feature composition for each plan.
- `saas_subscriptions` links one current subscription to each clinic and stores lifecycle dates/status.
- `saas_subscription_overrides` stores an exceptional feature or limit value for one subscription.
- `saas_coupons` stores administrative promotional rules restricted to 10%, 20%, 30% or 40%.
- `saas_subscription_promotions` freezes the plan, coupon, percentage, price in cents and promotion period used by one subscription.
- `saas_legal_documents` stores immutable-versioned drafts and published legal/operational documents with a SHA-256 content hash.
- `saas_legal_acceptances` stores an append-only evidence row per document in an accepted package, sharing a package UUID and freezing signer, tenant, plan, price, coupon, IP, user agent, version and hash.

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

`SaasPlanSeeder` creates three commercial plans only when their slugs do not already exist. The migration dated 05/11/2026 aligns existing records with the approved launch price and user limits; later operator edits remain available through the platform administration.

| Plan | Monthly | Annual | Users | Intended composition |
| --- | ---: | ---: | ---: | --- |
| Essencial | R$ 97,00 | R$ 970,00 | 3 | Cadastros, PDV, produtos e estoque |
| Profissional | R$ 197,00 | R$ 1.970,00 | 6 | Essencial mais agenda, serviços, compras, fornecedores e financeiro |
| Completo | R$ 347,00 | R$ 3.470,00 | 15 | Todos os módulos comerciais atuais, incluindo o módulo veterinário |

The annual price is equivalent to ten monthly payments. Public prices are launch values per establishment for an assisted pilot. Additional users, multiple units, advanced migration, paid messages, hardware-specific integrations, and fiscal activation require a separate commercial proposal. The plan price does not promise a compatible direct scale protocol or automatic fiscal issuance: those capabilities keep their own technical and homologation gates.

The public landing page presents the same price, user limit, and module boundaries. The price and composition records remain editable rather than becoming immutable product rules in code.

## Client onboarding

“Implantar novo cliente” runs one database transaction that creates:

1. the clinic/tenant;
2. its active subscription and optional overrides;
3. the initial active user;
4. the standard Administrator role link;
5. the audit event.

An optional coupon can be entered during onboarding. It is validated inside the same transaction against its active dates, permitted plans, redemption limit and non-overlap rule. A failed coupon therefore rolls back the clinic, subscription and initial user instead of leaving a partially onboarded tenant.

## Promotional coupons and price evidence

Platform operators can create codes with only the approved percentages (10%, 20%, 30% or 40%), an availability window, one-to-24-month customer duration, optional plan scope and optional redemption limit. Coupons are not cumulative. An active promotion prevents a silent plan change; the operator must cancel it with a recorded reason first.

Application freezes the monthly price as integer cents using deterministic rounding. Establishment administration shows the base price, percentage, discounted price, promotion period and the base price that resumes afterwards. Used coupon codes, percentage, start, duration and plan scope cannot be rewritten; cancellation preserves the original row and adds attributed cancellation evidence.

This is a commercial-record module, not a payment gateway. It does not charge a card, generate PIX/boleto, debit an account or prove payment. Billing and collection remain administrative until a separately approved provider integration is delivered.

## Legal documents and electronic acceptance

`LegalDocumentSeeder` creates five explicit **drafts without effect**: SaaS subscription agreement, terms of use, privacy notice, data-processing annex and support/backup/availability policy. Drafts may be viewed publicly for transparency but cannot be accepted. A platform operator must explicitly confirm external legal review and identify the reviewer before a version can be published. Publishing retires the previous published version without deleting it.

Tenant administrators can accept only when all five document types have a currently effective published version. One acceptance action creates five immutable evidence rows under one UUID. Repeating the same acceptance is idempotent; publishing a later document version allows a new complete package to be accepted while preserving every prior package. The application blocks model updates and deletes on acceptance records.

The seeded texts are technical drafting material, not legal advice and not a completed legal release. Provider identity/CNPJ/address, support channel and hours, service targets, backup retention, RPO/RTO, subprocessors, cancellation, readjustment and forum must be decided and reviewed by Brazilian counsel before publication. See `docs/legal-review-checklist.md`.

If the role, user, subscription, or any other write fails, the clinic is rolled back as well. Passwords are handled by the existing hashed model cast and never enter audit snapshots.

The establishment can identify itself with either CPF or CNPJ. `clinics.document_type` records which document was selected, while the existing `clinics.cnpj` column stores digits only for backward compatibility. The onboarding and clinic forms apply the corresponding Brazilian display mask and also normalize telephone and WhatsApp numbers before validation.

## Operational rollout

Production rollout requires a database backup, the SaaS migrations, `AuthorizationSeeder`, `SaasPlanSeeder`, and `LegalDocumentSeeder`. Existing clinics stay on the compatibility plan until a platform operator deliberately assigns a commercial plan. Legal documents remain seeded as drafts and therefore do not unlock acceptance by deployment alone.

## Deliberately deferred

Phase 2 can add billing-provider integration, payment methods, invoices, automated renewals, plan-change workflows, trials and grace periods driven by billing events, unit-limit enforcement at unit creation, customer self-service, and commercial analytics. No payment gateway, PIX, card, boleto, automatic charge, or automatic upgrade exists in this phase. Legal review and operational target selection also remain human gates outside software delivery.

## Verification

`tests/Feature/SaasFoundationTest.php` covers plan resolution, override precedence, active-user limits, tenant isolation, global administration, route gating, permission plus feature composition, transactional onboarding, rollback, and legacy compatibility.

`tests/Feature/SaasCommercialGovernanceTest.php` covers approved coupon percentages, exact-cent prices, plan/date/duration rules, overlap prevention, onboarding, plan-change protection, draft visibility, publication gate, immutable acceptance evidence and public disclosures.
