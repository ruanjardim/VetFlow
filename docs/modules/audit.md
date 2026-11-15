# Administrative Audit Trail

Code path: `app/Modules/Audit`

## Purpose

The Audit module keeps an append-only, clinic-scoped history of sensitive
administrative and operational changes. Integrations include collaborator
access, clinic branding, printer and SaaS configuration, Banho & Tosa
availability, and the cash-session lifecycle. It is not a general application
log and does not replace the source records.

## Recorded Events

| Event | Meaning |
| --- | --- |
| `access.user.created` | A collaborator account and its initial roles were created. |
| `access.user.updated` | Profile, status, roles, or password state changed. |
| `clinic.branding.updated` | The clinic changed its sidebar animal-icon settings. |
| `printer.created` / `printer.updated` | A printer configuration was created or changed. |
| `saas.plan.created` / `saas.plan.updated` | A SaaS plan was created or changed. |
| `saas.subscription.updated` / `saas.tenant.onboarded` | Subscription or tenant onboarding changed. |
| `grooming.schedule.*` | Clinic/professional availability or dated blocks changed. |
| `cash.session.opened` | An operator cash session was opened. |
| `cash.session.closed` | A session was closed, including automatic-close metadata. |
| `cash.session.reviewed` | A manager reviewed and ended a closed session. |
| `cash.session.reopened` | A manager reopened a session for a new closing. |

Each event records the clinic, actor, subject type/id and label, changed fields,
request IP/user agent, and occurrence time. Password values are never included;
the log stores only `password_changed: true` when applicable.

## Authorization And Tenant Rules

- `audit.manage` protects `GET /audit` and belongs to the Administrator preset.
- Clinic users only see events with their own `clinic_id` through the shared
  tenant scope.
- Global administrators can review events across clinics.
- The module exposes no update or delete routes. Audit events are append-only.
- Integrated domain changes and their audit record are committed in the same
  database transaction when the originating workflow is transactional.

## Data Model

`audit_events` stores:

- nullable `clinic_id` and `actor_user_id` foreign keys;
- event name and subject type/id/label;
- JSON `changes` containing only before/after values that differ;
- optional JSON metadata;
- request IP and a bounded user-agent snapshot;
- `occurred_at` and Laravel timestamps.

Clinic deletion or actor deletion sets the corresponding foreign key to null
without removing the historical event.

## Interface

The read-only screen supports event type, date interval, actor, user, and clinic
search. Change details stay collapsed until the operator opens them.

## Tests

`tests/Feature/AdministrativeAuditTrailTest.php` covers creation, safe access
snapshots, password redaction, clinic isolation, permission enforcement, and
the read-only history screen.

## Intentionally Out Of Scope

- Logging every CRUD operation automatically.
- Storing passwords, tokens, or session data.
- Editing or deleting audit events from the web interface.
- Treating this table as a legal or cryptographically signed ledger.
