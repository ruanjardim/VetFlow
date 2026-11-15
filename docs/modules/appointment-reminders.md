# Appointment Reminders

Code paths:

- `app/Modules/Appointments`
- `resources/views/appointments/reminders.blade.php`
- `database/migrations/2026_08_12_235000_create_appointment_reminders_table.php`

## Purpose

Provides an assisted appointment follow-up queue so the clinic can prepare a
contact, open WhatsApp when available, record the actual outcome, and retain a
clinic-scoped audit history.

This workflow intentionally does not send messages automatically. The operator
reviews the appointment and message, performs the contact through the selected
channel, and then records what happened in VetFlow.

## Reminder Queue

The default queue covers today and the next two calendar days. Operators can
change the period and filter by the latest contact outcome. Only appointments
with `scheduled` or `confirmed` status remain in the active queue.

Each row exposes:

- appointment date, time, patient, and responsible person;
- the latest recorded reminder state;
- available phone and email contact details;
- an assisted WhatsApp link with a prefilled message;
- the form used to record the completed contact.

## Contact Outcomes

The supported outcomes are:

- notice sent;
- presence confirmed;
- no response;
- reschedule requested;
- appointment cancelled.

Confirming a presence changes a scheduled appointment to `confirmed`.
Recording a cancellation changes the appointment to `cancelled`, removing it
from the active queue while preserving the reminder audit row.

## History And Traceability

Every recorded attempt is stored in `appointment_reminders` with:

- clinic and appointment references;
- the user who recorded the action;
- channel and outcome;
- a snapshot of the destination used at contact time;
- optional notes;
- the contact timestamp.

The appointment edit screen displays this history. Destination snapshots are
kept even if the responsible person's contact details change later.

## Routes And Main Classes

- `GET /appointments/reminders` lists and filters the queue.
- `POST /appointments/{appointment}/reminders` records an outcome.
- `AppointmentController` handles the HTTP boundary.
- `StoreAppointmentReminderRequest` validates the submitted outcome.
- `AppointmentReminderService` prepares the queue, WhatsApp message, contact
  destination, audit record, and appointment status side effects.
- `AppointmentReminder` represents the audit record.

## Tenant And Permission Rules

Both routes require `appointments.manage`. Appointments and reminders use the
existing clinic tenant scope, so a clinic user cannot list or record a contact
for another clinic's appointment.

A phone channel requires a primary phone, WhatsApp accepts the secondary phone
first and then the primary phone, and email requires a registered email. The
`other` channel is available when the contact happened outside those fields.

## Tests

`tests/Feature/AppointmentReminderFlowTest.php` covers:

- assisted WhatsApp message preparation and confirmation history;
- cancellation status side effects without losing the audit record;
- permission enforcement and cross-clinic rejection;
- validation when the chosen channel has no matching destination.

## Intentionally Out Of Scope

- automatic or bulk message dispatch;
- background delivery queues and provider webhooks;
- claiming that a message was delivered or read;
- automatic rescheduling.
