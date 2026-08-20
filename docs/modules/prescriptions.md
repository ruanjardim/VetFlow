# Prescriptions Module

## Purpose

Creates structured veterinary prescription documents linked to an existing
medical record and patient. The module keeps each medication instruction as a
historical snapshot instead of depending on the product or inventory catalog.

## Code Paths

- `app/Modules/Prescriptions`
- `resources/views/prescriptions`
- `database/migrations/2026_08_20_010000_create_prescriptions_tables.php`

## Lifecycle

1. A permitted user creates a `draft` from a tenant-visible medical record.
2. The draft can be reviewed and edited while preserving its patient,
   medical-record, clinic, and creator links.
3. Finalization records its timestamp and user and makes the clinical content
   immutable.
4. A finalized prescription can be cancelled with a required reason. The
   original content remains visible and is marked as cancelled.

There is no delete route. This protects the clinical history while still
allowing an unfinished draft to remain explicitly identifiable.

## Structured Items

Every prescription requires at least one item and accepts up to 30. Each item
stores medication name, optional concentration, dose, optional route,
frequency, optional duration, optional quantity, and free instructions. Dose
and frequency are required so a finalized item is not silently incomplete.

Items are snapshots. This first version does not link them to Products or
deduct stock because the text prescribed to the patient must not change when a
commercial catalog record changes.

## Tenant And Permission Rules

- `prescriptions.manage` protects every route.
- Administrator and Veterinarian standard presets receive the permission.
- The medical record is resolved through its clinic scope; clinic users cannot
  create or read prescriptions for another clinic.
- Patient and clinic are derived from the medical record instead of accepted
  from browser input.
- The patient clinical profile only loads prescription details for users with the
  prescription permission.

## Document View

The detail page is print-friendly and displays patient, responsible person,
date, author, items, directions, general instructions, and lifecycle state.
Drafts are explicitly marked as having no final-document validity. Cancelled
documents retain a visible reason.

This version does not implement a veterinarian credential registry, digital
signature, controlled-substance forms, external validation, or automatic
regulatory compliance. Those require a separate legal and product definition.

## Tables

- `prescriptions`
- `prescription_items`

## Tests

`tests/Feature/PrescriptionFlowTest.php` covers creation, item persistence,
finalization, post-finalization immutability, cancellation history, permission
checks, cross-clinic isolation, and patient-profile integration.
