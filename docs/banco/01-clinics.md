# Clinics

Table: `clinics`

## Objective

Stores the clinics, units, or clinic networks that use VetFlow. This table is
the tenant foundation for the ERP: most operational records point back to a
clinic through `clinic_id`.

## Current Fields

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | bigint | Yes | Primary key. |
| `ulid` | ulid/string | Yes | Public stable identifier generated on create. |
| `parent_clinic_id` | foreign id | No | Optional parent clinic for network/unit structures. |
| `corporate_name` | string | Yes | Legal name. |
| `trade_name` | string | Yes | Commercial name shown to users. |
| `cnpj` | string | Yes | Unique CNPJ document. |
| `crmv` | string | No | Veterinary council registration, when applicable. |
| `technical_manager` | string | No | Technical manager name. |
| `email` | string | No | Main contact email. |
| `phone` | string | No | Phone number. |
| `whatsapp` | string | No | WhatsApp contact. |
| `website` | string | No | Public website. |
| `zip_code` | string | No | Address ZIP code. |
| `state` | string | No | Address state. |
| `city` | string | No | Address city. |
| `district` | string | No | Address district. |
| `street` | string | No | Address street. |
| `number` | string | No | Address number. |
| `complement` | string | No | Address complement. |
| `logo` | string | No | Stored logo path. |
| `brand_icon_mode` | string | Yes | `automatic`, `manual`, or `none`; defaults to `automatic`. |
| `brand_icon_key` | string | Yes | Selected animal icon; defaults to `generic`. |
| `timezone` | string | Yes | Defaults to `America/Sao_Paulo`. |
| `currency` | string(3) | Yes | Defaults to `BRL`. |
| `language` | string | Yes | Defaults to `pt_BR`. |
| `active` | boolean | Yes | Enables or disables operational access. |
| `created_at` / `updated_at` | timestamps | Yes | Laravel-managed timestamps. |
| `deleted_at` | timestamp | No | Soft delete marker. |

## Indexes And Constraints

- `ulid` is unique.
- `cnpj` is unique.
- `parent_clinic_id` references `clinics.id` and is set to null when the parent
  clinic is deleted.
- `active`, `city`, and `state` are indexed for administrative filtering.

## Relationships

- A clinic can have one parent clinic.
- A clinic can have many child clinics.
- A clinic owns operational records such as users, tutors, patients, products,
  inventory movements, purchase entries, sales, and financial transactions.

## Business Rules

- Clinics should be soft-deleted instead of physically removed.
- Inactive clinics should not be allowed to operate.
- Future branch/network features should build on `parent_clinic_id` instead of
  creating a separate tenant concept.
- New operational tables should include `clinic_id` unless they are explicitly
  global/shared catalogs.
