# Employee And Access Model

Status: planned as a separate table; currently represented by `users`,
`roles`, `permissions`, and `users.position`.

## Current Decision

VetFlow does not currently have an `employees` table. Staff/operator behavior is
handled by:

- `users`: identity, login, profile, clinic link, active status, and `position`.
- `roles`: access profiles.
- `permissions`: feature-level capabilities.
- `user_roles`: relationship between users and roles.

This is enough for the current operational scope because a staff member who uses
the ERP also needs a login account.

## Why This Is Acceptable For Now

- The system already needs authentication for operators.
- Roles and permissions already express what each operator can do.
- `users.position` covers the visible job/title need without introducing a
  second staff identity.
- Feature tests already validate permission and tenant isolation behavior.

## When To Create `employees`

Create a dedicated `employees` table only when VetFlow needs staff records that
are not the same as login users, for example:

- non-login professionals;
- payroll or HR records;
- commission rules;
- professional schedules independent from a system account;
- veterinarian CRMV data per person;
- employment status history;
- links to multiple clinics/units with different roles.

## Suggested Future Schema

| Field | Purpose |
| --- | --- |
| `id` | Primary key. |
| `clinic_id` | Clinic that owns the staff record. |
| `user_id` | Optional linked login user. |
| `name` | Staff member name. |
| `document` | CPF or internal document. |
| `phone` / `email` | Contact data. |
| `position` | Operational title. |
| `crmv` | Veterinary council registration, when applicable. |
| `hire_date` | Hiring date. |
| `termination_date` | Termination date, if inactive. |
| `active` | Operational status. |
| `metadata` | Extensible staff details. |
| `created_at` / `updated_at` / `deleted_at` | History and soft deletion. |

## Migration Guideline

If `employees` is introduced later, keep `users` as the login source of truth and
make `employees.user_id` nullable. Do not move authentication fields into
`employees`.
