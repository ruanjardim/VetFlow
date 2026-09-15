# Printers Module

Code path: `app/Modules/Printers`

## Purpose

Stores each establishment's printer inventory and operational print preferences.
The screen supports fiscal, non-fiscal, label, document, and other printers,
including their primary purpose, connection, paper size, queue name, network
address, manufacturer, model, status, and default selection.

## Routes And Classes

- `GET /settings/printers` lists the clinic's configured printers.
- `GET /settings/printers/create` presents the form and
  `POST /settings/printers` creates a printer through `PrinterController` and
  `SavePrinterRequest`.
- `GET /settings/printers/{printer}/edit` and
  `PUT /settings/printers/{printer}` edit a printer.
- `GET /settings/printers/{printer}/test` presents a print-ready test page.
- `PrinterService` owns default-printer normalization, persistence, and audit
  recording.
- `Printer` owns the tenant-scoped `printers` table.

## Business Rules

- Printer records belong to one clinic and cannot be viewed or changed by a
  user from another clinic.
- Global platform users do not configure tenant devices from this screen.
- All six standard clinic role presets receive `printers.manage`, matching the
  requirement that every operational responsible person can reach the setup.
  Custom access remains controllable through the permission system.
- A clinic can have at most one active default printer. Selecting a new default
  clears the previous selection; an inactive printer cannot remain the default.
- Network host and port are retained only for the network connection type.
- Create and update actions are recorded in the administrative audit trail.

## Browser And Fiscal Boundary

The web application stores configuration and provides a test document, while
the browser's print dialog selects the physical printer. Browsers do not grant
the application silent control over arbitrary USB, serial, Bluetooth, or
operating-system print queues. Fiscal issuance also requires the establishment's
homologated provider or driver, certificate, and tax configuration. This module
does not claim to replace those components.

## Permission

Protected by `printers.manage`.

## Tests

Covered by `tests/Feature/PrinterManagementTest.php`, including configuration,
default selection, print test rendering, authorization, audit evidence, and
tenant isolation.
