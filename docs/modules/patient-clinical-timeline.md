# Patient Clinical Timeline

## Purpose

Provides one reverse-chronological read model for the patient's permitted
clinical history. It helps the team understand sequence without copying or
changing the source records.

## Sources

The timeline can combine up to 30 of the most recent visible events from:

- appointments;
- medical records and structured exam results;
- prescriptions;
- vaccination schedules and applications;
- hospitalization admissions, discharges, and append-only evolutions;
- patient clinical alerts and their resolutions.

Each event keeps a link to its source screen. The timeline stores no duplicate
database row and remains factual: it does not infer causality, severity,
diagnosis, treatment, or clinical relationships between events.

## Permission And Tenant Rules

- The patient is resolved through the standard clinic tenant scope.
- Each source is queried only when the signed-in user has its module
  permission.
- A user with `patients.manage` alone receives no restricted clinical events.
- Hospitalization evolutions are filtered by both their tenant scope and the
  selected patient's hospitalization.
- Shared events are sorted only after their permitted source collections have
  been assembled.

## Code Paths

- `app/Modules/Patients/Services/PatientClinicalTimelineService.php`
- `app/Modules/Patients/Services/PatientClinicalProfileService.php`
- `resources/views/patients/show.blade.php`

## Tests

`tests/Feature/PatientClinicalTimelineTest.php` covers reverse chronological
ordering, source links, authorship, permission filtering, and cross-clinic
isolation.
