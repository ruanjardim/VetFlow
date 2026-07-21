# Clinical Core Modules

Code paths:

- `app/Modules/Tutors`
- `app/Modules/Patients`
- `app/Modules/Schedules`
- `app/Modules/Appointments`
- `app/Modules/PetShopServices`
- `app/Modules/ServiceOrders`

## Purpose

Groups the customer, pet, scheduling, appointment, service catalog, and service
order flows that connect clinic operations to sales and finance.

## Main Responsibilities

- Register tutors/customers.
- Register patients/pets linked to tutors and clinics.
- Manage schedules and appointments.
- Maintain the pet shop service catalog.
- Build service orders with product and service items.
- Convert service orders into billable sales when appropriate.

## Tables

- `tutors`
- `patients`
- `schedules`
- `appointments`
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
tutors, patients, products, and services from another clinic.

## Permissions

Relevant permission slugs:

- `tutors.manage`
- `patients.manage`
- `schedules.manage`
- `appointments.manage`
- `petshop-services.manage`
- `service-orders.manage`

## Tests

Relevant coverage is present in `tests/Feature/PurchaseAndClinicalFlowTest.php`.
