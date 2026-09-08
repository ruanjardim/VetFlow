# Clinics Module

Code path: `app/Modules/Clinics`

## Purpose

Maintains the global registry of clinics, units, and future clinic networks that
use VetFlow. A clinic is the root tenant record referenced by users and by most
operational data through `clinic_id`.

## Main Responsibilities

- Create, list, update, and soft-delete clinic records.
- Store the legal identity, trade name, CNPJ, contact, location, locale, and
  active status of each clinic.
- Store the clinic's sidebar animal-icon preference independently from each
  user's account.
- Generate a stable ULID when a clinic is created.
- Provide clinic choices to global workflows that require an explicit tenant,
  including implementation, purchases, sales, and financial operations.
- Represent an optional parent/child clinic structure for future network or
  branch behavior.
- Act as the tenant boundary used by clinic-scoped models and services.

## Operational Workflow

1. A global administrator with `clinics.manage` opens `/clinics`.
2. The administrator creates the clinic with legal name, trade name, unique
   CNPJ, contact/location data, and active status.
3. Users can then be assigned to the clinic through the Access module.
4. Global operators select the clinic explicitly in cross-clinic operational
   forms; clinic users receive their own `clinic_id` automatically in
   tenant-aware modules.
5. Deactivation marks the clinic as inactive without removing its history.
   Deletion is a soft delete and hides the record from normal registry queries
   while preserving its operational references.

Clinic-scoped administrators with `clinic-branding.manage` can also open
`/settings/branding`. They may let VetFlow choose the icon from the clinic
users' species-of-practice preferences, select a fixed icon, or hide it. The
automatic mode shows a species-specific icon only when the configured practice
maps to one icon family; mixed or empty selections use the generic paw.

Creating at least one clinic is a prerequisite for global users to start
purchase-entry and sales workflows. Those screens link back to clinic creation
when the registry is empty.

## Key Classes

| Class | Role |
| --- | --- |
| `ClinicController` | Thin CRUD controller using the shared base controller. |
| `ClinicService` | Coordinates CRUD behavior through the repository contract. |
| `ClinicBrandingController` | Updates only the authenticated tenant's visual identity. |
| `ClinicBrandingService` | Resolves automatic and manual sidebar icons without duplicating the species catalog. |
| `ClinicRepository` | Data access for the global clinic registry. |
| `ClinicRepositoryInterface` | Repository contract registered by `ClinicServiceProvider`. |
| `StoreClinicRequest` | Validates clinic creation. |
| `UpdateClinicRequest` | Validates clinic updates and ignores the current record in the CNPJ uniqueness rule. |
| `Clinic` | Soft-deletable clinic model with ULID generation and parent/child relations. |
| `EnsureUserIsGlobal` | Blocks clinic-scoped users from the global clinic registry. |

## Routes

All routes require authentication, an active user, `clinics.manage`, and a
global user (`users.clinic_id` must be null).

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/clinics` | Paginated clinic list. |
| `GET` | `/clinics/create` | Creation form. |
| `POST` | `/clinics` | Create a clinic. |
| `GET` | `/clinics/{clinic}/edit` | Edit form. |
| `PUT` | `/clinics/{clinic}` | Update a clinic. |
| `DELETE` | `/clinics/{clinic}` | Soft-delete a clinic. |
| `GET` | `/settings/branding` | Open the current clinic's identity settings. |
| `PUT` | `/settings/branding` | Update the current clinic's identity settings. |

There is no public detail/show route.

## Data And Validation

The `clinics` table owns the registry. The complete field reference is in
[`docs/banco/01-clinics.md`](../banco/01-clinics.md).

Important validation rules:

- legal name, trade name, CNPJ, and active status are required;
- CNPJ is unique across the table, including soft-deleted records;
- email and website must use valid formats when present;
- state accepts at most two characters;
- currency must contain exactly three characters;
- the database defaults are `America/Sao_Paulo`, `BRL`, and `pt_BR` when the
  corresponding optional values are omitted.

The current web form exposes legal name, trade name, CNPJ, email, phone, city,
state, active status, and animal-icon branding. The model and request also support CRMV, technical
manager, WhatsApp, website, full address, timezone, currency, and language,
but those fields are not yet exposed by the form. Parent clinic and logo are
model/database capabilities without a current UI workflow.

## Tenant And Authorization Rules

`Clinic` is the tenant registry itself, so it intentionally does not use
`BelongsToClinicTenant`. The shared `TenantContext` therefore does not filter
clinic queries.

Because this registry can reveal or change every tenant, permission alone is
not sufficient. `EnsureUserIsGlobal` requires the authenticated user's
`clinic_id` to be null for every clinic-management route. The navigation also
hides clinic-management links from clinic-scoped users, even when a role
accidentally contains `clinics.manage`.

Operational models that belong to a clinic must continue to use their own
tenant scope and must never infer access from the clinic registry.

## Lifecycle Notes

- Clinic deletion uses `SoftDeletes`; operational records are not removed.
- The `active` flag is stored and displayed, but it does not currently log out
  or block users assigned to an inactive clinic. User activity is enforced
  separately by `EnsureUserIsActive`.
- `parent_clinic_id` supports hierarchy at the data layer, but there is no
  current roll-up, cross-unit permission, or consolidated reporting behavior.
- A soft-deleted clinic keeps its CNPJ reservation under the current uniqueness
  rule.

## Dependencies

- Access assigns users to clinics.
- `TenantContext` and tenant-aware models use `users.clinic_id` to scope data.
- Implementation, purchase entries, sales, financial, and other global flows
  load the clinic registry for explicit tenant selection.
- Most operational tables reference `clinics.id` through `clinic_id`.

## Permissions

Protected by `clinics.manage` plus the global-user middleware. The permission is
part of the Administrator preset, but clinic-scoped administrators still cannot
enter the global registry.

The tenant branding page is separately protected by `clinic-branding.manage`.
It never accepts a clinic identifier from the request and always updates the
clinic attached to the authenticated user. Global administrators configure the
same fields through the protected clinic registry form.

## Tests

Relevant coverage is present in:

- `tests/Feature/ClinicTenantIsolationTest.php`
- `tests/Feature/ClinicBrandingTest.php`
- `tests/Feature/AuthorizationTest.php`
- `tests/Feature/AccessManagementTest.php`
- `tests/Feature/PurchaseAndClinicalFlowTest.php`

The tests cover required clinic fields, valid global creation, permission
enforcement, rejection of clinic-scoped users from the global registry, tenant
stamping in operational modules, and clinic selection in global workflows.

## Intentionally Out Of Scope

- Consolidated reporting across parent and child clinics.
- Automatic inheritance of configuration or permissions between units.
- Clinic logo upload and storage workflow.
- Automatic access blocking based on clinic active status.
- Physical deletion of clinic data and its operational history.
