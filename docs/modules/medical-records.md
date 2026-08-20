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
- One or more structured pathologies selected from the catalog, while the
  free-text diagnosis remains available for hypotheses, differentials, and
  clinical context.
- One or more structured exam requests selected from the catalog, each able to
  receive a protected result document with draft, finalized, and cancelled
  states.
- Zero or more structured prescriptions maintained through their own protected
  lifecycle and linked back to the medical record.

## Pathology Catalog

`Cadastros > Patologias` exposes an accent-insensitive alphabetical catalog
that can be searched and filtered by species. Standard entries are shared by
all clinics; a clinic can also create a reusable private entry and relate it to
one or more species. An entry without species links is considered compatible
with every patient.

The medical-record form filters the visible choices as soon as its appointment
or patient determines the species. `Outra patologia` creates a normalized
clinic entry, links it to the patient's species when known, and selects it on
the current record. The service rejects entries belonging to another clinic or
incompatible with the patient's species.

The initial standard dataset covers companion animals, equids, ruminants,
swine, birds, reptiles, small mammals, and aquatic animals. Its sanitary naming
is informed by the MAPA compulsory animal-disease list and the WOAH listed
diseases. It supports consistent recording and does not replace veterinary
clinical judgment or regulatory notification duties.

## Exam Catalog

`Cadastros > Exames` follows the same clinic and species visibility rules as
the pathology catalog. Standard entries cover common laboratory and imaging
requests; a clinic can add a reusable private exam and optionally restrict it
to one or more species. The medical-record form stores a name snapshot for
each selected request, preserving the clinical history if the catalog changes
later. A request with result history cannot be removed from the medical record.

## Exam Results

Each request can receive one result document with collection/result dates,
laboratory identification, summary, detailed text, source reference notes, and
internal notes. Drafts remain editable; finalization records its user and time
and makes the content immutable. A finalized result can only be cancelled with
an auditable reason. See [Exam Results](exam-results.md).

## Tables

- `medical_records`
- `animal_pathologies`
- `animal_pathology_species`
- `medical_record_pathology`
- `animal_exams`
- `animal_exam_species`
- `medical_record_exams`
- `medical_record_exam_results`
- `prescriptions`
- `prescription_items`

## Permission

- `medical-records.manage`

The system administrator and veterinarian presets receive this permission.
Reception roles do not receive it by default because the module contains
clinical-sensitive information.

## Intentionally Out Of Scope

The structured catalog does not calculate or suggest a diagnosis. Exam results
are recorded exactly as informed and do not generate automatic flags,
interpretations, analytes, units, or reference ranges. Prescriptions do not yet
provide a digital signature or regulated controlled-substance forms;
hospitalization remains a separate clinical flow.
