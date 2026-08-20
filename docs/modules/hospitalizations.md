# Hospitalizations Module

## Purpose

Records a patient's clinic admission and operational follow-up, keeping the
admission, discharge, accommodation, and notes visible in the same clinic and
in the patient's clinical profile. Active admissions also keep an append-only
diary of observed evolutions.

## Current Scope

- Admission linked to a patient in the current clinic.
- Optional link to a medical record for the same patient.
- Statuses for active hospitalization, discharge, and cancellation.
- Admission time, optional expected discharge, discharge time, and
  accommodation or sector.
- Free-text operational follow-up and discharge notes.
- Immutable evolution entries with observation time, recording user, notes,
  and optional weight, temperature, heart-rate, and respiratory-rate snapshots.
- Read-only history inside the patient's clinical profile.

## Evolution Diary

New evolution entries are accepted only while the status is `hospitalized`.
The observation time must be between the admission and the current time. There
are no update or delete routes: corrections are added as a new entry, keeping
the original authored record intact. After discharge or cancellation, the
complete diary remains available in read-only mode.

The optional vital signs are observations only. VetFlow does not classify
values, suggest conduct, or generate automatic clinical alerts from them.

## Boundaries

The module does not create prescriptions, medication administration schedules,
stock deductions, billing, or automatic clinical protocols. Those decisions
remain documented in the clinical record and require their own approved
workflow.

## Tables

- `hospitalizations`
- `hospitalization_evolutions`

## Permission

- `hospitalizations.manage`

The standard administrator and veterinarian roles receive this permission.

## Tenant Rules

Each hospitalization is scoped to the patient clinic. A linked medical record
must belong to the same patient, and records from another clinic are rejected.
Evolution entries inherit the admission clinic and can only be created through
a tenant-visible hospitalization.

## Tests

- `tests/Feature/HospitalizationFlowTest.php`
- `tests/Feature/HospitalizationEvolutionFlowTest.php`
