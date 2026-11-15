# Employees Module

## Objective

The Employees module keeps a clinic-scoped functional record for collaborators,
including people who do not need a VetFlow login. It separates professional and
payment information from authentication, licenses and permissions.

## Permission And Routes

All routes reuse `users.manage` and are tenant protected:

| Route | Purpose |
| --- | --- |
| `GET /employees` | List functional records. |
| `GET /employees/create` | Open the collaborator form. |
| `POST /employees` | Create a record transactionally. |
| `GET /employees/{employee}/edit` | Open a tenant-scoped record. |
| `PUT /employees/{employee}` | Update functional and payment data. |

There is no destructive delete route. Functional records can be marked
inactive without revoking or changing the optional login account.

## Behavior

- A record belongs to exactly one clinic.
- A linked user must belong to the same clinic.
- A record may have no linked user and therefore consumes no user license.
- One user can be linked to at most one functional record.
- A collaborator registered without login can later receive credentials from
  its own edit/list action. The Access form is prefilled and links the new user
  to the existing record under a transaction lock instead of creating a
  duplicate.
- Users created through Access automatically receive a linked record.
- Access updates synchronize name, email, phone, position and clinic, while
  functional active status remains independent from login active status.
- Document and payment details are encrypted at rest.
- Create/update operations produce safe audit events without clear documents or
  clear bank/Pix credentials.
- Commission closure stores the linked employee and masked payment snapshot;
  the linked Finance payable remains the payment workflow.

## Operational Boundary

The module is an employee directory and payment-reference foundation, not Human
Resources or payroll. It does not calculate salary, benefits, time tracking,
taxes, labor obligations, payslips, accounting files or electronic transfers.

## Tests

`tests/Feature/EmployeeManagementTest.php` covers records without login,
Brazilian decimal normalization, encryption at rest, authorized display, audit
evidence, tenant isolation and later login assignment without duplication.
`AccessManagementTest` covers automatic user link creation, while
`SalesCommissionSettlementTest` covers immutable masked payment snapshots.
