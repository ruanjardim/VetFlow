# Backup Restore Drill

Updated: 2026-08-23

This runbook proves that an exported VetFlow database can be restored into an
isolated database before a pilot or material migration. The commands are
read-only for both source and restored databases: export and import remain the
operator's responsibility through the hosting provider's supported tooling.

## Safety Boundary

- Put the application in the provider-supported maintenance window before the
  export so the backup and control totals describe the same point in time.
- Store the backup and generated JSON outside the application deploy directory.
- Never configure `backup_restore` with the live database identity.
- Use fictitious data for staging drills and remove the temporary restored
  database after recording the evidence.
- Neither command prints credentials or record contents.

The verifier rejects a target whose connection fingerprint matches the source.
It never runs migrations, imports SQL, writes application records, or deletes
the temporary database.

## 1. Capture The Backup Snapshot

Immediately after exporting the database, record a provider-safe identifier:

```bash
php artisan vetflow:backup:snapshot \
  --identifier=kinghost-staging-20260823-01 \
  --output=/secure/evidence/kinghost-staging-20260823-01-manifest.json
```

The manifest contains only the database driver, hashed connection identity,
migration digest, table counts, maximum IDs, and latest update timestamps. It
does not contain tutor, patient, user, financial, or clinical field values.

## 2. Restore Into An Isolated Database

Create a separate temporary database through the provider and import the
export. Configure only the restore target variables in the isolated command
environment:

```dotenv
RESTORE_DB_CONNECTION=mysql
RESTORE_DB_HOST=127.0.0.1
RESTORE_DB_PORT=3306
RESTORE_DB_DATABASE=vetflow_restore_drill
RESTORE_DB_USERNAME=restore_operator
RESTORE_DB_PASSWORD=change-me
```

Do not add these credentials to Git or to the evidence file.

## 3. Verify And Record Evidence

```bash
php artisan vetflow:backup:verify \
  --manifest=/secure/evidence/kinghost-staging-20260823-01-manifest.json \
  --connection=backup_restore \
  --evidence=/secure/evidence/kinghost-staging-20260823-01-evidence.json
```

The command fails when migrations, table presence, row totals, maximum IDs, or
latest update timestamps diverge. These are operational control totals, not a
cryptographic checksum of every field; provider import logs should be retained
alongside the evidence.

## 4. Release Gate And Cleanup

Use a successful, recent evidence file in the release diagnostic:

```bash
php artisan vetflow:release:check \
  --backup-evidence=/secure/evidence/kinghost-staging-20260823-01-evidence.json
```

Evidence is accepted for 30 days by default. Adjust
`VETFLOW_BACKUP_EVIDENCE_MAX_AGE_DAYS` only through an approved operational
decision. `--backup-confirmed` remains a manual fallback, but recorded evidence
is preferred.

After the check, record the operator, provider backup identifier, result,
restore duration, evidence location, and cleanup decision. Then remove the
temporary database through the provider's controlled interface.
