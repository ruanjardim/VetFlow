# Responsáveis Module

Code path: `app/Modules/Tutors`

## Purpose

Maintains the people responsible for veterinary patients. The historical table
is still named `tutors`, while the interface uses **Responsáveis**.

## Registration Flow

- Identification: name, CPF, RG, birth date, and gender.
- Contacts: primary and secondary phone numbers, plus email.
- Address: CEP, street, number, complement, district, city, and state.
- Operational status and internal observations.

The browser formats CPF, Brazilian phone numbers, and CEP. When an eight-digit
CEP is entered, it requests the public ViaCEP service to prefill the address.
An unavailable or unknown CEP never blocks manual address entry.

## Data Safety

- No migration was required for the complete form: all fields already exist in
  the `tutors` table.
- CPF is normalized to digits before validation and storage.
- CPF must be valid when supplied and remains unique.
- Editing a responsible person preserves their own existing CPF; the uniqueness
  validation ignores the current record.

## Tenant Rules

Responsible people are scoped to a clinic through `clinic_id`. Clinic users can
only access their own records, while global users follow the selected clinic
context.
