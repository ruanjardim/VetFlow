# Validation Module

Code paths:

- `app/Modules/Validation`
- `app/Core/Validation`
- `app/Core/Support/DocumentNormalizer.php`

## Purpose

Centralizes Brazilian document validation helpers and lookup-style validation
responses for CPF/CNPJ flows. The current module focuses on document validity
and detecting whether a tutor or clinic already exists for a valid document.

## Main Responsibilities

- Normalize CPF/CNPJ values to digits only.
- Validate CPF check digits.
- Validate CNPJ check digits.
- Return JSON-friendly responses for CPF and CNPJ checks.
- Report whether a valid CPF already belongs to a tutor.
- Report whether a valid CNPJ already belongs to a clinic.
- Provide reusable `ValidCpf` and `ValidCnpj` validation rules in Core.

## Key Classes

| Class | Role |
| --- | --- |
| `ValidationController` | JSON controller for CPF/CNPJ validation methods. |
| `ValidationService` | Normalizes documents, validates them, and looks up duplicates. |
| `BrazilianDocumentValidator` | Core CPF/CNPJ check-digit validator. |
| `DocumentNormalizer` | Core helper for extracting numeric document values. |
| `ValidCpf` | Laravel validation rule for CPF fields. |
| `ValidCnpj` | Laravel validation rule for CNPJ fields. |

## Tables Read

- `tutors`
- `clinics`

## Response Shape

CPF and CNPJ validation responses include:

- `valid`: whether the document is structurally valid.
- `exists`: whether an existing record was found.
- `message`: user-facing validation message.
- `data`: present only when an existing tutor or clinic is found.

## Important Behavior

- Invalid CPF/CNPJ values return `valid = false` and `exists = false`.
- Valid CPF values are checked against `tutors.cpf`.
- Valid CNPJ values are checked against `clinics.cnpj`.
- Duplicate CPF responses include tutor id, name, phone, and email.
- Duplicate CNPJ responses include clinic id, corporate name, and trade name.
- `ValidCpf` is used by tutor request validation.
- The `app/Modules/Validation` controller is present, but no validation route is
  currently registered in `routes/web.php`.

## Tenant Rules

The current module-level duplicate lookups use direct model queries. Any future
web/API route for these checks should decide explicitly whether the lookup is
global, current-clinic only, or role-dependent before exposing it broadly.

## Permissions

No standalone permission is currently wired for `app/Modules/Validation`
because its routes are not registered. Core form request rules run inside the
permission and route context of the form that uses them.

## Tests

There is no standalone validation feature test yet. Existing tutor and clinic
tests exercise parts of document validation through request validation and
unique-field behavior.
