# Employee And Access Model

Status: implemented as a separate, clinic-scoped functional record linked
optionally to the login user.

## Current Decision

VetFlow separates employment/operational data from authentication:

- `employees` stores the clinic-owned functional record, including name,
  contact, position, relationship type, admission/termination dates, optional
  reference compensation, payment preference, active status and internal notes;
- `users` remains the identity and login source of truth, including password,
  access status, clinic, CRMV/UF and role links;
- `employees.user_id` is nullable and unique, so a collaborator may exist
  without consuming a login license and one login cannot represent two
  functional records;
- `roles`, `permissions` and `user_roles` continue to control authorization.

Creating a clinic user through Users and Accesses creates its linked functional
record transactionally. Existing non-global users are backfilled when the
employee migration runs. Later access updates synchronize identity/contact
fields, but do not treat a disabled login as an employment termination.

## Sensitive Data

`document` and `payment_details` use Laravel encrypted casts. They are not
written to audit snapshots in clear text. Audit records keep only presence
flags and masked payment information.

When a sales commission is settled, the settlement freezes:

- the optional `employee_id` represented at closure time;
- payment method and a masked key/account snapshot.

Changing the employee's payment data later does not rewrite an existing
commission settlement. The clear payment details are never copied into the
commission ledger.

## Schema

| Field | Purpose |
| --- | --- |
| `id` / `ulid` | Internal and public-safe identifiers. |
| `clinic_id` | Clinic that owns the record. |
| `user_id` | Optional unique login user. |
| `name` | Collaborator name. |
| `document` | Encrypted CPF or other document. |
| `phone` / `email` | Contact data. |
| `position` | Operational title or function. |
| `employment_type` | Employee, contractor, partner, intern or other. |
| `hire_date` | Admission or start date. |
| `termination_date` | Termination date, when applicable. |
| `base_compensation` | Informational reference amount. |
| `payment_method` | Pix, bank transfer, cash or other. |
| `payment_details` | Encrypted payment fields. |
| `active` | Functional status, independent from login status. |
| `notes` | Internal notes. |
| timestamps / `deleted_at` | History and soft deletion foundation. |

## Explicit Boundary

This structure is not a payroll engine. It does not calculate salary, benefits,
hours, vacation, 13th salary, payroll taxes, labor charges, payslips or bank
transfers. `base_compensation` is informational. Commission closure creates the
existing pending payable, and an authorized operator still confirms its payment
in Finance.

Veterinary CRMV/UF remains on `users` while clinical document responsibility
still requires an authenticated professional in the current workflow. Finalized
clinical snapshots are not rewritten by this migration.
