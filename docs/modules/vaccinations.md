# Vaccinations Module

## Purpose

Maintains the clinic-scoped vaccination card for each patient. A record can be
scheduled, marked as applied, or marked as not applied, and may keep the next
due date, batch, manufacturer, notes, and an optional related medical record.

## Current Scope

- Manual registration of vaccine schedules and applications, preserving the
  historical `vaccine_name` for every record.
- A shared standard catalog plus clinic-owned vaccine options, each optionally
  restricted to compatible animal species.
- Optional dose count and interval fields configured by the clinic. Selecting a
  configured catalog vaccine can suggest the next due date while preserving a
  manually entered date.
- Patient, clinic, and optional medical-record linkage protected by tenant validation.
- No delete route, preserving the clinical history.
- No automatic stock deduction or reminder sending.

## Clinical Boundary

VetFlow does not prescribe vaccination protocols. Standard catalog items do not
contain an assumed dose count or interval. The clinic must configure those
values according to the selected product, its leaflet, and the responsible
veterinarian's clinical decision.

## Permission

- `vaccinations.manage`
