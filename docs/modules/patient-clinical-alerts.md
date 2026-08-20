# Patient Clinical Alerts

## Purpose

Keeps short, clinic-scoped safety notices attached to a patient and displays
active notices at the points where the team reviews clinical information.

## Lifecycle

1. A user with `medical-records.manage` records a title and optional factual
   details from the patient profile.
2. The alert remains active and visible on the patient profile, medical record,
   prescription, and hospitalization screens.
3. Resolution requires a written reason and records its user and timestamp.
4. The resolved entry leaves active banners but remains in the patient history.

There are no update or delete routes. A wrong or outdated alert is resolved
with an explanation, preserving its original author, content, and timestamps.

## Safety Boundary

VetFlow does not assign severity, infer allergies, interpret vital signs, or
generate alerts automatically. The feature communicates facts entered by the
clinical team; clinical assessment remains the veterinarian's responsibility.

## Tenant And Access Rules

- `clinic_id` isolates alerts through the standard clinic tenant scope.
- The patient and alert must belong to the signed-in user's clinic.
- Alert routes use `medical-records.manage`, matching the sensitivity of the
  information.
- Users who can register patients but cannot read medical records do not load
  or see alerts in the patient profile.

## Table

- `patient_clinical_alerts`

## Tests

`tests/Feature/PatientClinicalAlertFlowTest.php` covers creation, resolution,
audit fields, visibility in risk-sensitive screens, permission checks,
cross-clinic isolation, patient/alert pairing, and the absence of update or
delete routes.
