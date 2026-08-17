# Hospitalizations Module

## Purpose

Records a patient's clinic admission and operational follow-up, keeping the
admission, discharge, accommodation, and notes visible in the same clinic and
in the patient's clinical profile.

## Current Scope

- Admission linked to a patient in the current clinic.
- Optional link to a medical record for the same patient.
- Statuses for active hospitalization, discharge, and cancellation.
- Admission time, optional expected discharge, discharge time, and
  accommodation or sector.
- Free-text operational follow-up and discharge notes.
- Read-only history inside the patient's clinical profile.

## Boundaries

The module does not create prescriptions, medication administration schedules,
stock deductions, billing, or automatic clinical protocols. Those decisions
remain documented in the clinical record and require their own approved
workflow.

## Permission

- `hospitalizations.manage`

The standard administrator and veterinarian roles receive this permission.

## Tenant Rules

Each hospitalization is scoped to the patient clinic. A linked medical record
must belong to the same patient, and records from another clinic are rejected.
