# Access

## Objective

The Access module manages collaborator login records and assigns the standard
VetFlow role presets. It provides an operational screen for administrators
without exposing free-form permission editing.

## Permission

All routes are protected by `users.manage`.

Only the `administrador` system preset receives this permission. A user editing
their own record cannot deactivate themselves, move their own clinic, or remove
the last selected role that grants `users.manage`.

## Routes And Components

| Route | Purpose |
| --- | --- |
| `GET /access/users` | Lists collaborators visible to the administrator. |
| `GET /access/users/create` | Opens the collaborator form. |
| `POST /access/users` | Creates a user and role links transactionally. |
| `GET /access/users/{user}/edit` | Opens a tenant-scoped user record. |
| `PUT /access/users/{user}` | Updates profile, status, optional password, and roles. |

The module is implemented by:

- `AccessUserController`;
- `AccessUserService`;
- `AccessUserRepository`;
- store and update form requests;
- Blade views under `resources/views/access/users`.

## Tenant Rules

- A clinic administrator only lists and edits users with the same `clinic_id`.
- A clinic administrator cannot choose or submit another target clinic; new
  users are stamped with the administrator's clinic.
- A global administrator can list all users and choose a clinic or global
  access when creating or editing another user.
- Access to a user from another clinic returns `404` rather than revealing that
  the record exists.
- Only active global system roles can be assigned by this module.

## Standard Role Presets

`AuthorizationSeeder` creates and updates six global presets:

| Preset | Operational focus |
| --- | --- |
| `administrador` | Complete system and access administration. |
| `veterinario` | Clinical registrations, schedule, appointments, and service orders. |
| `atendimento` | Reception, schedule, registrations, service orders, and sales. |
| `estoque-compras` | Products, inventory, purchases, suppliers, and global catalog. |
| `caixa` | Counter service, service orders, and sales. |
| `financeiro` | Sales, purchases, suppliers, and financial transactions. |

More than one preset can be selected. Effective permissions are the union of
the active selected roles.

## Role Link History

`user_roles` has a required ULID and soft deletion. Role changes therefore use
an explicit synchronization flow:

- removed links receive `deleted_at` and `updated_by`;
- a previously removed link is restored instead of duplicated;
- new links receive an ULID plus `created_by` and `updated_by`;
- user data and role changes commit in the same database transaction.

There is no destructive delete action in this module. Administrators deactivate
a collaborator when access must be blocked.

Creation and updates also write an `audit_events` entry in the same transaction.
The snapshot includes profile, status, clinic, and role slugs. A password change
is acknowledged without retaining the password or its hash. Reading those
events requires the separate `audit.manage` permission.

## Deployment

Run `AuthorizationSeeder` after migrations in every environment so that new
permissions and preset changes are synchronized:

```bash
php artisan db:seed --class=AuthorizationSeeder --force
```

## Tests

`tests/Feature/AccessManagementTest.php` covers:

- the six preset catalog;
- representative permission boundaries;
- clinic-scoped list and edit behavior;
- clinic stamping during creation;
- global administrator clinic selection;
- rejection of custom or inactive roles;
- soft-deleted role link restoration;
- protection against administrator self-lockout.

`tests/Feature/AdministrativeAuditTrailTest.php` additionally covers the safe,
tenant-scoped audit snapshots produced by this module.
