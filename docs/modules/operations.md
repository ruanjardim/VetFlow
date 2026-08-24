# Operations

The Operations module gives authorized administrators a safe operational
surface for the deployed release identity and its release controls.

## Boundaries

- Read access is protected by `operations.readiness`; every state-changing
  action additionally requires `operations.execute`.
- Exposes only the short Git SHA, environment name, queue mode/connection, and
  default storage disk.
- Does not expose credentials, connection strings, environment variables, file
  paths, probe sentinels, or backup contents.
- Does not change runtime configuration or execute a deployment.
- An explicit administrator action may start and verify a synthetic runtime
  probe; it never creates clinic, patient, financial, or clinical records.
- Reuses the same `ReleaseReadinessService` as the Artisan release gate, so
  the interface and command cannot drift into different technical rules.

## Main Components

- Route: `/operations`.
- Controller: `App\Modules\Operations\Controllers\OperationsController`.
- Release identity: `App\Support\Operations\ReleaseIdentityService`.
- Technical gates: `App\Support\Operations\ReleaseReadinessService`.
- Evidence discovery: `App\Support\Operations\OperationalEvidenceService`.

The console discovers only the newest legible `*-evidence.json` in the private
backup and runtime-probe directories. It never sends file paths, hashes,
database fingerprints, or sentinel contents to the view.

## Runtime Probe Runs

Authorized administrators can prepare and verify the synthetic queue/storage
probe from the Operations Center. Every transition is appended to
`operations_runtime_probe_events` and scoped by clinic, environment, and full
release SHA. The interface keeps only the probe identifier, safe runtime
context, actor, timestamp, and status; sentinel hashes and evidence paths stay
private. A successful verification writes the same evidence format used by
the CLI release gate, then removes the temporary synthetic artifacts.

## Restore Evidence Intake

The actual export, restore, and verification remain outside the web process
and must target an isolated database. An authorized operator may import the
resulting JSON evidence into the Operations Center. Uploads are limited to
512 KB, structurally validated, freshness checked, reduced to an allowlisted
schema, and written only to the configured private directory. Unexpected keys,
control-total payloads, paths, and arbitrary uploaded content are discarded.

Each accepted import appends an `operations_backup_evidence_events` record
bound to clinic, environment, and release SHA. The database stores only the
safe identifier, status, timestamps, check count, actor, and a SHA-256 digest;
the UI never exposes manifest or restored-connection fingerprints.

## Smoke Checklist

The release smoke checklist is derived from `docs/release-checklist.md`.
Completing or reopening an item appends an `operations_smoke_checks` event; it
does not overwrite an earlier decision. Current state is isolated by clinic,
environment, and full release SHA, and every event keeps its actor and note.

## Consolidated Decision

Five gates consolidate release identity, platform diagnostics, runtime-probe
evidence, restore evidence, and the smoke checklist. Approval is rejected while
any gate is pending. Approval or hold creates an append-only
`operations_release_decisions` record with a SHA-256 hash of the current safe
evidence snapshot; later evidence changes make that decision visibly stale.

The same state is available through a print-friendly report and a no-cache JSON
download. Neither export includes evidence file paths, database fingerprints,
storage sentinels, credentials, or environment variables.

## Tests

`tests/Feature/OperationsConsoleTest.php` covers permission isolation and the
safe release-context presentation.
