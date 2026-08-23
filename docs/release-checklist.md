# VetFlow Release Checklist

Updated: 2026-08-23

Use this checklist for a staging release and before the first production pilot.
It complements the [deployment guide](deployment.md); it does not replace a
provider-specific runbook.

## 1. Release Scope

- Confirm the target commit and pull request.
- Configure `VETFLOW_RELEASE_SHA` with the full 40-character commit SHA, unless
  the hosting platform provides `RENDER_GIT_COMMIT` automatically.
- Confirm CI is green for Laravel tests and the frontend build.
- Review migrations, seeders, environment changes, storage paths, and queue
  changes included in the release.
- Record the operator, target environment, start time, and rollback owner.
- Keep demo fixtures disabled in production.

## 2. Backup And Rollback

- Create a database backup before running migrations.
- Verify the backup file is readable, non-empty, and stored outside the deploy
  directory.
- Perform a restore drill in an isolated database before the first pilot and
  after material database changes.
- Preserve the previous application build and environment configuration.
- Define the rollback commit and the database restore decision before starting.

Prefer the repository's [backup restore drill](deployment/backup-restore-drill.md),
which records control totals before export validation and produces evidence
after an isolated restore. `--backup-confirmed` remains a manual operator
attestation and does not create or validate a backup by itself.

## 3. Pre-Release Validation

Run from the release candidate:

```bash
composer validate --no-check-publish
php artisan test
npm ci
npm run build
php artisan route:list
```

Review the working tree and dependency audit before publishing:

```bash
git status --short
composer audit
```

## 4. Deployment

Follow the release sequence from the deployment guide:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=AuthorizationSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Restart the application runtime and queue worker using the hosting platform's
supported process controls.

## 5. Automated Runtime Check

After deployment, prepare a synthetic probe, let the real worker or bounded
cron process it, and generate operational evidence:

```bash
php artisan vetflow:runtime:probe
php artisan vetflow:runtime:probe --verify --probe=<ULID> \
  --evidence=/secure/evidence/runtime-evidence.json
php artisan vetflow:release:check \
  --runtime-evidence=/secure/evidence/runtime-evidence.json \
  --backup-evidence=/secure/evidence/restore-evidence.json
```

See the [runtime operations probe](deployment/runtime-operations-probe.md) for
the worker/cron and restart-boundary procedure.

The command blocks a production release when it finds:

- a missing or invalid full Git commit identity;
- a missing `APP_KEY`;
- `APP_DEBUG=true`;
- an `APP_URL` without HTTPS;
- a failed database connection;
- pending migrations;
- an invalid log channel;
- a synchronous or invalid queue connection;
- a missing `jobs` table for the database queue;
- a storage disk that cannot create and remove a temporary probe;
- missing, stale, failed, or wrong-environment evidence that storage and the
  asynchronous queue completed an end-to-end probe;
- missing fresh evidence or operator confirmation for a restorable backup.

Run the command without `--backup-confirmed` in local or testing environments.

## 6. Smoke Tests

- Open `/up` and confirm a successful health response.
- Open `/ops/release`, confirm `200`, and compare `release.sha` with the exact
  deployed commit before testing business flows.
- Log in with an active administrator.
- Confirm the expected clinic context and tenant-scoped lists.
- Open Implementation and confirm coverage, data-quality, checklist,
  release-plan, and readiness evidence remain scoped to the selected clinic.
- Confirm a readiness approval is unavailable while any of the four evidence
  gates is pending; do not approve a real pilot during a smoke test.
- Open Users and Access and confirm the administrator preset.
- Run one product lookup without depending on paid providers.
- Import a known fictitious NF-e XML in staging and stop before saving the
  purchase entry.
- Create a manual stock entry in a staging clinic and verify its inventory
  movement.
- Create a draft sale and confirm stock and financial side effects are absent.
- Complete a small staging sale and verify stock, payment, and financial
  records.
- Confirm the queue worker consumes a harmless test job or, for the KingHost
  staging bridge, that the authenticated cron receives `204` and removes the
  job from the `jobs` table without creating a `failed_jobs` record.
- Upload and remove a disposable staging asset using the configured storage
  disk.
- Review application logs for provider, storage, queue, database, and mail
  failures.

Do not perform destructive smoke tests in a real clinic. Use a dedicated
staging or pilot-validation clinic.

## 7. Release Decision

Proceed only when:

- the automated runtime check passes;
- all required smoke tests pass;
- no unexpected errors appear in logs;
- the database backup and rollback owner are confirmed.

Stop and roll back when a migration fails, tenant isolation is uncertain,
stock/financial side effects diverge, storage is unavailable, or login and
permission checks fail.

## 8. Evidence

Record:

- deployed commit and release time;
- `/ops/release` response matched to that commit;
- migration and seeder result;
- runtime-check output;
- runtime-probe ULID and evidence location, without sentinel contents;
- smoke-test operator and result;
- backup location identifier, without credentials;
- rollback or follow-up decision.
