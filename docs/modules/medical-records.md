# Medical Records Module

## Purpose

Records the clinical information produced during a veterinary appointment while
preserving the relationship between the patient, the appointment, the clinic,
and the user who created the record.

## Code Paths

- `app/Modules/MedicalRecords`
- `resources/views/medical-records`

## Main Rules

- Each appointment can have only one active medical record.
- A medical record must use the same patient linked to its appointment.
- Creation stamps the clinic from the appointment and the logged-in creator.
- Clinic-scoped users can only select and read records from their own clinic.
- The appointment and patient links are immutable after creation.
- There is no delete route in the first version, preserving the clinical
  history recorded in the system.

## Information Captured

- Appointment date/time, patient, appointment, and creator.
- Weight, temperature, heart rate, respiratory rate, and hydration.
- Chief complaint, anamnesis, clinical findings, diagnosis, treatment plan,
  prescription notes, and additional notes.

## Tables

- `medical_records`

## Permission

- `medical-records.manage`

The system administrator and veterinarian presets receive this permission.
Reception roles do not receive it by default because the module contains
clinical-sensitive information.

## Intentionally Out Of Scope

This first version does not generate formal prescriptions, manage vaccines,
store laboratory exams, or control hospitalization. These are separate
clinical flows to be planned after the record foundation is validated.
