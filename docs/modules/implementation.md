# Implementation Module

Code path: `app/Modules/Implementation`

## Purpose

Guides assisted clinic onboarding and data migration into VetFlow. The first
functional slice imports Tutors from a standardized CSV file after clinic
selection, validation, mapping review, and explicit confirmation.

## Current Flow

1. Select an active destination clinic.
2. Select CSV as the data source.
3. Download the Tutors template and upload the completed file.
4. Review the automatic column mapping.
5. Correct header or row validation errors.
6. Preview up to 20 valid records.
7. Confirm the transactional import.
8. Review the completion summary.

The wizard state is kept in the authenticated session. Normalized rows are
stored temporarily as a private JSON file on the `local` disk and removed when
the wizard is reset or the import finishes.

## CSV Contract

The Tutors template contains these columns:

```text
nome,telefone,whatsapp,email,cpf_cnpj,endereco,observacoes
```

- All columns must exist in the header.
- `nome` and `telefone` are required in every row.
- `cpf_cnpj`, when filled for a Tutor, is normalized and validated as CPF.
- CPF uniqueness follows the existing Tutors rule.
- Comma and semicolon delimiters are detected automatically.
- A file can contain up to 500 non-empty records and must be no larger than
  2 MB.
- The import is all-or-nothing: any invalid row blocks the operation.

## Key Classes

| Class | Role |
| --- | --- |
| `ImplementationController` | Coordinates wizard pages and redirects. |
| `ImplementationWorkflowService` | Manages session state and private temporary analysis files. |
| `TutorCsvImportService` | Parses, maps, validates, previews, and imports Tutor rows. |
| `SelectClinicRequest` | Restricts the destination clinic to the user's accessible active clinic scope. |
| `SelectSourceRequest` | Enables only the currently supported CSV source. |
| `UploadTutorCsvRequest` | Validates extension and upload size. |

## Tenant And Permission Rules

- Every route is protected by `implementation.manage`.
- A clinic-scoped user can select only their own active clinic.
- A global user must explicitly select an active destination clinic.
- Every imported Tutor receives the selected `clinic_id`.
- Temporary analysis is associated with the authenticated user's session.

## Tables

The module does not own a database table in this first slice. The confirmed
import creates records in `tutors`; transient wizard data is not persisted as a
business record.

## Tests

`tests/Feature/ImplementationTutorCsvTest.php` covers:

- successful import and field mapping;
- invalid row blocking;
- clinic isolation;
- required wizard-step enforcement;
- private temporary-file cleanup after completion.

Authorization and template download remain covered by
`tests/Feature/AuthorizationTest.php`.

## Intentionally Out Of Scope

- Excel parsing.
- Manual column mapping.
- Partial import of valid rows.
- Patient, product, supplier, inventory, and financial imports.
- Durable migration history or background processing.
