# Runtime Operations Probe

Updated: 2026-08-23

This probe creates synthetic, short-lived artifacts to prove that the deployed
runtime can retain a storage marker and process a real asynchronous queue job.
It does not create clinic, patient, stock, financial, or clinical records.

## What It Proves

- the configured storage disk can be written and read by different execution
  moments;
- the configured queue is asynchronous and can process a real job;
- the queue job sees the same application environment and storage context as
  the command that prepared it;
- the evidence is recent and has not diverged from its storage sentinel.

The probe does not replace provider monitoring, database backup restoration,
tenant smoke tests, or a long-duration storage retention test.

## Configuration

The probe uses `FILESYSTEM_DISK` unless a dedicated disk is configured:

```text
VETFLOW_RUNTIME_PROBE_DISK=
VETFLOW_RUNTIME_PROBE_EVIDENCE_MAX_AGE_MINUTES=180
```

`QUEUE_CONNECTION` must be asynchronous. `sync`, `null`, `background`, and
`deferred` connections are rejected because they cannot prove an external
worker or cron cycle.

## Prepare

Run in the deployed environment:

```bash
php artisan vetflow:runtime:probe
```

The command prints a ULID, writes a random synthetic sentinel under
`vetflow/runtime-probes/<ULID>/`, and dispatches one queue job. It never prints
the sentinel nonce.

For a persistence test, prepare the probe before the planned restart or deploy
boundary. With a permanent worker, pause processing before preparation and
resume it after the boundary. With the bounded KingHost bridge, prepare before
the next authenticated cron cycle.

## Process And Verify

Let the configured worker process the job. In KingHost cron mode, trigger the
authenticated endpoint or wait for the scheduled cycle. Then run:

```bash
php artisan vetflow:runtime:probe --verify --probe=<ULID> \
  --evidence=/secure/evidence/runtime-evidence.json
```

Verification fails while the job is still queued. On success it writes JSON
evidence and removes only the two synthetic probe artifacts. The evidence
contains environment, queue mode/connection, storage disk, timestamps, a
sentinel digest, and four boolean checks; it contains no credentials or clinic
data.

## Release Gate

In staging and production, pass both operational and restore evidence:

```bash
php artisan vetflow:release:check \
  --runtime-evidence=/secure/evidence/runtime-evidence.json \
  --backup-evidence=/secure/evidence/restore-evidence.json
```

The gate rejects missing, failed, stale, wrong-environment, synchronous-queue,
or incomplete runtime evidence. The default validity window is three hours.

## Failure Handling

- If preparation cannot dispatch the job, it removes the new sentinel.
- If the queue cannot find or validate the sentinel, the job fails normally
  and remains visible through the configured failed-job controls.
- If verification runs too early, it leaves the sentinel available for retry.
- If evidence cannot be written, synthetic artifacts are preserved for
  diagnosis instead of being silently discarded.

Probe artifacts use validated ULIDs and a fixed private prefix. Do not manually
place user files under that prefix.
