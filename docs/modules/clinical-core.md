# Clinical Core Modules

Code paths:

- `app/Modules/Tutors`
- `app/Modules/Patients`
- `app/Modules/Schedules`
- `app/Modules/Appointments`
- `app/Modules/MedicalRecords`
- `app/Modules/Vaccinations`
- `app/Modules/PetShopServices`
- `app/Modules/ServiceOrders`

## Purpose

Groups the customer, pet, scheduling, appointment, service catalog, and service
order flows that connect clinic operations to sales and finance.

## Interface Terminology

The interface calls the person linked to a patient **Responsável**. The existing
`Tutor` model, `tutors` table, `tutor_id` fields, and routes remain unchanged to
preserve compatibility with current records, imports, CPF validation, and CEP
lookup behavior.

## Main Responsibilities

- Register tutors/customers.
- Register patients/pets linked to tutors and clinics.
- Manage schedules and appointments.
- Prepare appointment reminders, record contact outcomes, and retain their
  clinic-scoped history.
- View schedules and appointments together in day, week, and month views.
- Register clinical records linked to appointments and patients.
- Maintain clinic-scoped pathology and exam catalogs, with optional species
  compatibility, and register the selected items in clinical records.
- Track vaccination schedules and applications by patient.
- Maintain the pet shop service catalog.
- Build service orders with product and service items.
- Convert service orders into billable sales when appropriate.

## Tutor And Patient Relationship

Every new Patient must reference a Tutor from the same clinic. Existing legacy
Patient rows remain compatible because the database relationship is nullable,
but the application requires `tutor_id` for new manual records and CSV imports.
Hard-deleting a Tutor keeps the historical Patient and clears its Tutor
reference.

The Patient screens expose the Tutor relationship, and the repository loads it
with the Patient list to avoid repeated queries.

Species and breeds use an extensible catalog that covers companion animals,
birds, reptiles and amphibians, aquatic animals, large animals, and wildlife
classifications. Standard entries are shared, while custom entries remain
scoped to the clinic that created them. The `Other` option creates a reusable
entry instead of storing a generic label. The legacy text snapshots remain for
compatibility with existing records and integrations. See
[Patient Species And Breeds](patient-taxonomy.md).

The Patient form groups identification, structured species/breed references,
and initial clinical data. From the patient list, permitted users can start a
schedule or appointment with the patient and linked responsible person
preselected. Changing the patient in either form updates the selected
responsible person as a convenience only; the existing tenant validation
remains authoritative.

## Tables

- `tutors`
- `patients`
- `animal_species`
- `animal_breeds`
- `schedules`
- `appointments`
- `appointment_reminders`
- `medical_records`
- `animal_pathologies`
- `animal_pathology_species`
- `medical_record_pathology`
- `animal_exams`
- `animal_exam_species`
- `medical_record_exams`
- `vaccinations`
- `petshop_services`
- `service_orders`
- `service_order_items`

## Service Orders

Service orders are the bridge between operational service delivery and checkout.
They can include:

- service items linked to `petshop_services`;
- product items linked to `products`;
- discounts;
- service/product totals;
- a final total that can become sale items.

When a completed sale is linked to a service order, the sale flow marks the
service order as finished.

## Tenant Rules

Clinical records are tenant-scoped through `clinic_id`. Tests cover rejection of
tutors, patients, products, and services from another clinic. Patient creation
also verifies that the selected Tutor belongs to the destination clinic.

## Visual Agenda

The Agenda screen combines existing `schedules` and `appointments` without
duplicating them. It supports day, week, and month navigation and keeps the
same tenant scope as the source records. Selecting an event opens its existing
edit screen.

## Appointment Reminders

The reminder queue presents scheduled and confirmed appointments for today and
the next two days by default. It can prepare a WhatsApp message, but the operator
remains responsible for reviewing and sending the contact. Each actual attempt
is recorded with its channel, outcome, destination snapshot, notes, timestamp,
and recording user.

A confirmed outcome updates a scheduled appointment to `confirmed`; a cancelled
outcome updates it to `cancelled` and removes it from the active queue without
deleting the contact history. The complete workflow is documented in
[Appointment Reminders](appointment-reminders.md).

## Permissions

Relevant permission slugs:

- `tutors.manage`
- `patients.manage`
- `schedules.manage`
- `appointments.manage`
- `medical-records.manage`
- `vaccinations.manage`
- `petshop-services.manage`
- `service-orders.manage`

## Tests

Relevant coverage is present in
`tests/Feature/PurchaseAndClinicalFlowTest.php` and
`tests/Feature/PatientTutorFoundationTest.php`.

`tests/Feature/PatientTaxonomyFlowTest.php` validates the standard and custom
catalogs, species-to-breed relationships, legacy-compatible snapshots,
expandable navigation, and cross-clinic isolation.

`tests/Feature/ClinicalPilotFlowTest.php` validates the complete clinic flow
through the same application routes used by the interface: Responsavel,
Patient, Schedule, Appointment, Medical Record, and Vaccination. It confirms
that every resulting record remains linked to the same clinic, patient, and
responsible person.

Clinical catalogs complement, rather than replace, the free-text diagnosis and
clinical notes already stored in a medical record. A pathology or exam without
species restrictions is available to every species. Results, laboratory
attributes, units, and reference ranges are intentionally outside this first
exam-request delivery because those rules require a defined laboratory workflow.

`tests/Feature/AppointmentReminderFlowTest.php` covers reminder preparation,
outcome side effects, audit history, channel validation, permissions, and
cross-clinic isolation.
