# Users

Table: `users`

## Objective

Stores login identities and basic operator profile data. Access is controlled
through roles and permissions, while clinic separation is controlled by
`clinic_id`.

## Current Fields

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | bigint | Yes | Primary key. |
| `ulid` | ulid/string | Yes | Public stable identifier. |
| `clinic_id` | foreign id | No | Linked clinic. `null` means global user. |
| `name` | string | Yes | User display name. |
| `email` | string | Yes | Unique login email. |
| `phone` | string | No | Contact phone. |
| `photo` | string | No | Stored profile image path. |
| `position` | string | No | Operational role/title shown in the UI. |
| `email_verified_at` | timestamp | No | Laravel email verification timestamp. |
| `password` | string | Yes | Hashed password. |
| `active` | boolean | Yes | Blocks or allows login/use. |
| `last_login_at` | timestamp | No | Last successful login timestamp. |
| `remember_token` | string | No | Laravel remember-me token. |
| `created_at` / `updated_at` | timestamps | Yes | Laravel-managed timestamps. |
| `deleted_at` | timestamp | No | Soft delete marker. |

## Indexes And Constraints

- `email` is unique.
- `ulid` is unique.
- `clinic_id` references `clinics.id` and is set to null if the clinic is
  deleted.

## Relationships

- A user belongs to one clinic or can be global when `clinic_id` is null.
- A user can have many roles through `user_roles`.
- Permissions are resolved through active roles and active permissions.

## Access Behavior

- `active = false` users should not operate in the system.
- Users without roles are given `administrador` by `AuthorizationSeeder` during
  seeding, which prevents accidental lockout in existing local databases.
- Role links in `user_roles` support soft removal through `deleted_at`.
- The app checks permissions through middleware, Gates, and menu visibility.

## Global Users

Global users are not tied to one clinic. They are useful for administration and
support, but tenant-sensitive actions must still be scoped to a chosen clinic.
Tests currently cover selected-clinic behavior for purchase entries, sales, and
NF-e import.
