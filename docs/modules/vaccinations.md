# Vaccinations Module

## Purpose

Maintains the clinic-scoped vaccination card for each patient. A record can be
scheduled, marked as applied, or marked as not applied, and may keep the next
due date, batch, manufacturer, notes, and an optional related medical record.

## Current Scope

- Manual registration of vaccine schedules and applications.
- Patient, clinic, and optional medical-record linkage protected by tenant validation.
- No delete route, preserving the clinical history.
- No automatic stock deduction, reminder sending, or protocol calculation yet.

## Permission

- `vaccinations.manage`
