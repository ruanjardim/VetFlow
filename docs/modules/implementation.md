# Implementation Module

Code path: `app/Modules/Implementation`

## Purpose

Guides assisted clinic onboarding and data migration into VetFlow. The current
flow imports Tutors, Patients, Suppliers, Products, initial Stock, and
Financial records from standardized CSV or Excel (`.xlsx`) files after clinic selection,
validation, mapping review, and explicit confirmation.

## Current Flow

1. Select an active destination clinic.
2. Select CSV or Excel as the data source.
3. Choose a supported data block, download its template, and upload the
   completed file.
4. Review the automatic column mapping.
5. Correct header or row validation errors.
6. Preview up to 20 valid records.
7. Confirm the transactional import.
8. Review the completion summary.
9. Consult the permanent summary in the recent import history.

The wizard state is kept in the authenticated session. Normalized rows are
stored temporarily as a private JSON file on the `local` disk, separated by
entity type, and removed when the wizard is reset or the import finishes.
After a successful confirmation, VetFlow permanently records only audit
metadata: destination clinic, responsible user, data block, source, file name,
row counts, and completion time. Imported row contents and validation details
are not copied into the history.

## CSV Contract

CSV keeps automatic comma/semicolon delimiter detection and Windows-1252 to
UTF-8 conversion. All validation and all-or-nothing rules below also apply to
Excel after the first worksheet is normalized to the same internal tabular
contract.

## Excel Contract

- Only `.xlsx` is accepted; legacy `.xls` is not supported.
- Only the first worksheet is analyzed.
- A workbook can contain up to 500 non-empty records and must be no larger
  than 2 MB.
- The internal ZIP structure is limited to 500 entries and 25 MB after
  decompression before parsing.
- Excel date cells are normalized to `YYYY-MM-DD`.
- Formula cells use only the cached value already stored by Excel; VetFlow
  does not execute formulas.
- Document, phone, GTIN, and SKU columns should be formatted as Text when
  leading zeros must be preserved.
- The downloadable Excel templates use the same headers as the CSV templates.

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

The Suppliers template contains these columns:

```text
nome,cpf_cnpj,telefone,email,cidade,estado,observacoes
```

- `nome` is required.
- `cpf_cnpj`, when filled, is normalized and must be a valid CPF or CNPJ.
- `estado`, when filled, must contain a two-letter UF.
- New records are active and receive the selected `clinic_id`.

The Products template contains these columns:

```text
nome,ean_gtin,sku,categoria,fornecedor_documento,custo,preco_venda,estoque_atual,estoque_minimo
```

- `nome` is required.
- `ean_gtin`, when filled, must contain 8 to 14 digits and cannot identify an
  existing Product in the selected clinic.
- `sku`, when filled, cannot identify an existing Product in the selected
  clinic.
- `fornecedor_documento` is optional. When filled, it must identify exactly one
  active Supplier in the selected clinic.
- The Supplier reference is retained in `lookup_metadata`; Products do not have
  a direct Supplier foreign key.
- Prices and stock values accept Brazilian decimal commas and cannot be
  negative.
- A positive `estoque_atual` creates an `entry` Inventory Movement instead of
  changing `products.stock_quantity` directly.

The initial Stock template contains these columns:

```text
ean_gtin_ou_sku,quantidade,custo_unitario,lote,validade,observacoes
```

- `ean_gtin_ou_sku` must identify exactly one active Product in the selected
  clinic.
- `quantidade` must be greater than zero.
- `custo_unitario` is optional and cannot be negative.
- `validade` accepts `DD/MM/YYYY` or `YYYY-MM-DD`.
- Each row creates an `entry` movement with source `implementation_csv`,
  preserving balance before, balance after, lot, expiration, and notes.
- Stock already created through `estoque_atual` in the Products file must not
  be repeated in the Stock file.

The Financial template contains these columns:

```text
tipo,descricao,pessoa_documento,valor,vencimento,status,forma_pagamento,data_pagamento,referencia,observacoes
```

- `tipo` accepts `entrada`, `receita`, or `income` for income and `saida`,
  `despesa`, or `expense` for expenses.
- `descricao` and `valor` are required; values accept Brazilian decimal commas
  and cannot be negative.
- `pessoa_documento` is optional and resolves only an active Supplier from the
  selected clinic because the current Financial model has no Tutor relation.
- `vencimento` and `data_pagamento` accept `DD/MM/YYYY` or `YYYY-MM-DD`.
- `status` accepts pending/paid/cancelled/overdue and their Portuguese labels.
- Paid records require `data_pagamento`; non-paid records must leave it empty.
- Payment methods accept the internal codes or Portuguese labels for cash, Pix,
  debit card, credit card, transfer, bank slip, and other.
- Each row creates one clinic-scoped Financial Transaction with installment
  number and total equal to one.

## Key Classes

| Class | Role |
| --- | --- |
| `ImplementationController` | Coordinates wizard pages and redirects. |
| `ImplementationFileAnalyzer` | Validates the selected source, safely reads the first XLSX worksheet, and bridges it to the existing import contracts. |
| `ExcelTemplateService` | Streams standardized `.xlsx` templates for all six blocks. |
| `ImplementationImportService` | Runs the selected importer and durable audit write in one outer transaction, and scopes recent history queries. |
| `ImplementationWorkflowService` | Manages session state and private temporary analysis files. |
| `ImplementationImport` | Represents one successfully completed import summary. |
| `CsvFileAnalyzer` | Applies shared delimiter, header, encoding, row-limit, and summary rules to catalog, Stock, and Financial CSV files. |
| `CsvValueNormalizer` | Normalizes strings, Brazilian decimals, and supported dates for catalog, Stock, and Financial imports. |
| `TutorCsvImportService` | Parses, maps, validates, previews, and imports Tutor rows. |
| `PatientCsvImportService` | Parses, maps, validates, resolves Tutors, previews, and imports Patient rows. |
| `SupplierCsvImportService` | Validates CPF/CNPJ and imports clinic Suppliers. |
| `ProductCsvImportService` | Resolves Suppliers, creates Products, and opens optional initial stock. |
| `StockCsvImportService` | Resolves Products and creates audited Inventory entries. |
| `FinancialCsvImportService` | Normalizes ledger labels, resolves Suppliers, and imports Financial Transactions. |
| `SelectClinicRequest` | Restricts the destination clinic to the user's accessible active clinic scope. |
| `SelectSourceRequest` | Enables the supported CSV and Excel sources. |
| `UploadImplementationFileRequest` | Applies the shared `.csv`/`.xlsx` extension and upload-size rules to all six blocks. |

## Tenant And Permission Rules

- Every route is protected by `implementation.manage`.
- A clinic-scoped user can select only their own active clinic.
- A global user must explicitly select an active destination clinic.
- Every imported Tutor receives the selected `clinic_id`.
- Every imported Patient receives the selected `clinic_id` and a `tutor_id`
  belonging to that same clinic.
- Supplier resolution for Products is restricted to the selected clinic.
- Product resolution for Stock is restricted to the selected clinic.
- Supplier resolution for Financial records is restricted to the selected
  clinic.
- Product creation and Inventory movements receive the selected `clinic_id`.
- Product lookup and Inventory source metadata distinguish
  `implementation_csv` from `implementation_excel`.
- Every imported Financial Transaction receives the selected `clinic_id`.
- Temporary analysis is associated with the authenticated user's session.
- Clinic users can see only import history from their own clinic.
- Global implementation users can see recent history across the active clinic
  scope available to the wizard.
- The import rows and their sensitive business fields are never copied into
  the history table.

## Tables

The module owns `implementation_imports`, an append-only operational audit
summary for successfully completed imports. It stores clinic and user foreign
keys plus name snapshots, the imported block and source, the normalized
original file name, total/imported/invalid counts, and completion time.

Confirmed imports continue creating their business records in `tutors`,
`patients`, `suppliers`, `products`, `inventory_movements`, or
`financial_transactions`. Transient normalized rows remain outside the
database and are deleted after completion or reset.

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

`tests/Feature/ImplementationCatalogStockCsvTest.php` covers sequential
Supplier, Product, and Stock imports, Inventory balance traceability, invalid
rows, templates, and cross-clinic isolation.

`tests/Feature/ImplementationFinancialCsvTest.php` covers paid and pending
Financial imports, label normalization, invalid rows, template output, and
cross-clinic Supplier isolation.

`tests/Feature/ImplementationImportHistoryTest.php` covers permanent summaries
after successful confirmation, absence of row payloads, survival after wizard
reset, clinic-scoped visibility, and no history for blocked imports.

`tests/Feature/ImplementationExcelTest.php` covers all six Excel imports,
first-worksheet date normalization, `implementation_excel` trace metadata,
durable history, corrupted workbook blocking, and every Excel template.

## Intentionally Out Of Scope

- Legacy `.xls`, multiple worksheet selection, and manual formula evaluation.
- Manual column mapping.
- Partial import of valid rows.
- Failed-attempt history or background processing.
