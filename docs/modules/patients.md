# Patients

Code paths: `app/Modules/Patients`, `resources/views/patients`, and the
`patients` table.

## Purpose

The Patients module keeps the clinic-scoped identity of each animal and links
it to one responsible tutor. Clinical records, appointments, vaccinations,
hospitalizations, prescriptions, grooming orders, and longitudinal views reuse
that identity without duplicating the patient record.

## Registration fields

The main record supports:

- responsible tutor and active clinic;
- name, species, breed, coat or physical pattern, and sex;
- birth date, reference weight, and grooming size;
- optional microchip identifier, pedigree declaration and registration number,
  and free-text identification markings;
- optional notes;
- optional sterilization state;
- one optional profile photo.

Sterilization is deliberately tri-state: **sterilized**, **not sterilized**, or
**not informed**. Existing and imported records remain not informed until a
human confirms the value; absence is never interpreted as “not sterilized”.

Microchip and identification markings are operational identity fields, not
clinical conclusions. The microchip is not unique in VetFlow because imported
or provisional records may be incomplete or duplicated and must not be hidden
by a database constraint. Both values can be searched together with patient
name and responsible tutor, while the normal patient tenant scope continues to
limit results to the active clinic.

Pedigree is also tri-state: **has pedigree**, **does not have pedigree**, or
**not informed**. Its registration number is an independent optional string so
legacy records can preserve the evidence available at import time without
inventing a status. VetFlow stores the clinic/tutor declaration and can search
the number; it does not verify the issuing organization, authenticity,
genealogy, ownership, or legal validity of the document.

## Patient photo

- JPEG, PNG, and WebP are accepted, up to 5 MB.
- SVG and animated formats are rejected.
- The photo is stored on the private `local` disk under a clinic-specific
  directory with a generated name; the original client filename is not kept.
- The image is served only by the authenticated `patients.photo` route. The
  normal patient tenant scope returns `404` for a user from another clinic.
- Uploading a new photo replaces the previous file. If upload and removal are
  requested together, the new upload wins.
- Explicit removal clears the database reference and deletes the previous
  private file. Archiving the patient or recording its death keeps the photo
  with the historical record.
- The current registration photo is identification media. Clinical image
  galleries, captions, exam images, and clinical interpretation are outside
  this workflow.

The product reference used for this slice was the public SimplesVet support
description of the animal form: sterilization and identification markings are
part of the primary animal data, while chip, pedigree, pedigree number, and the
optional animal photo are available among additional fields. The VetFlow
storage, tenant isolation, tri-state values, search, non-unique identifiers,
and file limits are its own explicit implementation rules.

## Patient lifecycle

Every patient has one explicit lifecycle status:

- **active**: the normal operational state;
- **archived**: the record is retained but cannot receive new operations;
- **deceased**: death was reported and the record cannot receive new
  operations.

Archiving, restoring, recording a death, and correcting a death recorded by
mistake require a justification between 10 and 2,000 characters. The current
status keeps the latest reason, responsible user, and timestamp, while every
transition also appends a tenant-scoped `audit_events` record. An active or
archived patient can be marked deceased; an archived patient can be restored;
and a deceased patient can return to active only through the explicit death-
correction action.

Archiving or recording death cancels future appointments in `scheduled` or
`confirmed` state and schedules in `agendado` or `confirmado` state. Restoring
the patient never reopens those cancelled events automatically. Completed and
past events, clinical records, prescriptions, vaccinations, hospitalizations,
photos, and the longitudinal timeline remain attached and readable according
to their existing permissions.

Inactive patients are excluded from normal selectors and from the default
patient list, and their identity data cannot be edited until the record is
restored. The list can explicitly filter archived, deceased, or all records.
Shared tenant-scoped validation also rejects an inactive patient when creating
a new appointment, schedule, hospitalization, vaccination, medical record,
service order, package sale, sale, or clinical alert. Existing source records
remain available; this rule does not rewrite their history.

The generic patient deletion route is not exposed. Lifecycle transitions are
the supported operational mechanism, which prevents a mistaken cleanup action
from erasing or hiding the source of clinical and financial history.

## Routes and permissions

Patient create, read, update, lifecycle, and photo routes are protected by the
existing `patients.manage` module boundary. A generic delete route is not
registered. Global administrators retain their documented cross-clinic access;
clinic users remain restricted to the active clinic.

## Deployment

The additive migrations introduce nullable `photo_path`, `neutered`,
`microchip`, `markings`, `has_pedigree`, and `pedigree_number` columns, plus
the defaulted `lifecycle_status` and nullable lifecycle reason, timestamp, and
responsible-user reference. Under the Hostinger production procedure, publish
and verify those schema migrations before deploying code that reads any of the
new columns.

## Tests

`tests/Feature/PatientTutorFoundationTest.php` covers:

- clinic-safe create and update;
- private photo storage and protected delivery;
- JPEG/PNG/WebP validation and the 5 MB limit;
- replacement and explicit removal without leaving the previous file;
- sterilized, not sterilized, and not informed states;
- denial of photo access from another clinic;
- microchip and markings create/update behavior, boundary validation, search,
  and tenant isolation;
- pedigree tri-state, registration-number boundaries, CSV/XLSX import, search,
  and tenant isolation.

`tests/Feature/PatientLifecycleTest.php` covers:

- archive, restore, death registration, and death correction;
- required justifications and rejected invalid transitions;
- cancellation of future appointments and schedules without reopening them;
- preservation of completed history and lifecycle audit metadata;
- active-only lists, selectors, and write validation;
- edit blocking until an inactive record is restored;
- tenant isolation and the absence of a generic delete route.
