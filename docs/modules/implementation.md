# Implementation Module

Code path: `app/Modules/Implementation`

## Purpose

Guides assisted clinic onboarding and data migration into VetFlow. The current
flow imports Tutors and Patients from standardized CSV files after clinic
selection, validation, mapping review, and explicit confirmation.

## Current Flow

1. Select an active destination clinic.
2. Select CSV as the data source.
3. Choose Tutors or Patients, download the corresponding template, and upload
   the completed file.
4. Review the automatic column mapping.
5. Correct header or row validation errors.
6. Preview up to 20 valid records.
7. Confirm the transactional import.
8. Review the completion summary.

The wizard state is kept in the authenticated session. Normalized rows are
stored temporarily as a private JSON file on the `local` disk, separated by
entity type, and removed when the wizard is reset or the import finishes.

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

The Patients template contains these columns:

```text
tutor_documento,nome_pet,especie,raca,sexo,nascimento,peso,observacoes
```

- `tutor_documento` and `nome_pet` are required in every row.
- `tutor_documento` is normalized as CPF and must identify an existing Tutor
  in the selected clinic.
- The Tutor relationship is resolved again during confirmation so a changed or
  removed Tutor cannot be imported from stale analysis data.
- `nascimento` accepts `DD/MM/YYYY` or `YYYY-MM-DD` and cannot be a future date.
- `peso`, when filled, accepts Brazilian decimal commas and must be greater
  than zero.
- The common delimiter, size, row-limit, UTF-8, and all-or-nothing rules also
  apply to Patients.

## Key Classes

| Class | Role |
| --- | --- |
| `ImplementationController` | Coordinates wizard pages and redirects. |
| `ImplementationWorkflowService` | Manages session state and private temporary analysis files. |
| `TutorCsvImportService` | Parses, maps, validates, previews, and imports Tutor rows. |
| `PatientCsvImportService` | Parses, maps, validates, resolves Tutors, previews, and imports Patient rows. |
| `SelectClinicRequest` | Restricts the destination clinic to the user's accessible active clinic scope. |
| `SelectSourceRequest` | Enables only the currently supported CSV source. |
| `UploadTutorCsvRequest` | Validates extension and upload size. |
| `UploadPatientCsvRequest` | Validates the Patient CSV extension and upload size. |

## Tenant And Permission Rules

- Every route is protected by `implementation.manage`.
- A clinic-scoped user can select only their own active clinic.
- A global user must explicitly select an active destination clinic.
- Every imported Tutor receives the selected `clinic_id`.
- Every imported Patient receives the selected `clinic_id` and a `tutor_id`
  belonging to that same clinic.
- Temporary analysis is associated with the authenticated user's session.

## Tables

The module does not own a database table. Confirmed imports create records in
`tutors` or `patients`; transient wizard data is not persisted as a business
record.

## Tests

`tests/Feature/ImplementationTutorCsvTest.php` covers:

- successful import and field mapping;
- invalid row blocking;
- clinic isolation;
- required wizard-step enforcement;
- private temporary-file cleanup after completion.

Authorization and template download remain covered by
`tests/Feature/AuthorizationTest.php`.

`tests/Feature/ImplementationPatientCsvTest.php` covers successful Patient
import, Tutor linkage, row validation, and cross-clinic isolation.

## Intentionally Out Of Scope

- Excel parsing.
- Manual column mapping.
- Partial import of valid rows.
- Product, supplier, inventory, and financial imports.
- Durable migration history or background processing.
