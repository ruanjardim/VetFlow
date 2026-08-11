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

## Main Responsibilities

- Register tutors/customers.
- Register patients/pets linked to tutors and clinics.
- Manage schedules and appointments.
- Register clinical records linked to appointments and patients.
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

Species remains an open field so clinics can register animals beyond dogs and
cats. The form suggests common veterinary groups such as Canino, Felino,
Equino, Bovino, Ave, and Répteis, while allowing any other value when needed.
Patient edits apply the same tutor and clinic validation as patient creation.

## Tables

- `tutors`
- `patients`
- `schedules`
- `appointments`
- `medical_records`
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
