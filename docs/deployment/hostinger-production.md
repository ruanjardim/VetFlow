# Hostinger Production Runbook

This runbook covers the current VetFlow deployment at
`https://vetflowsys.com.br`. It complements the provider-neutral
[deployment guide](../deployment.md), the
[release checklist](../release-checklist.md), the
[backup restore drill](backup-restore-drill.md), and the
[runtime operations probe](runtime-operations-probe.md).

The application health endpoint returned HTTP 200 on 2026-09-17 and identified
the platform as Hostinger/hPanel with PHP 8.3.33. That response proves only that
Laravel can answer a request. It does not prove the deployed Git commit, queue
execution, backup restore, or the complete smoke test.

## Release identity and Git deployment

Before changing production, open **Websites > Dashboard > Advanced > Git** in
hPanel and record:

- repository and selected branch;
- deployment path and document root;
- latest deployment commit and timestamp;
- whether automatic deployment is enabled;
- the available rollback mechanism.

Merge the intended release first. Deploy only an immutable full commit SHA, and
set the same 40-character value in `VETFLOW_RELEASE_SHA`. After deployment,
compare that value with both hPanel's deployment history and `/ops/release`.

The application directory must be the directory that contains `artisan`.
Confirm it in hPanel or SSH; do not infer it from the public URL. Keep the web
document root pointed at that directory's `public` subdirectory.

## Production environment

Use the normal production settings from the deployment guide, plus this queue
configuration for Hostinger shared hosting:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vetflowsys.com.br
QUEUE_CONNECTION=database
VETFLOW_QUEUE_MODE=cron
VETFLOW_QUEUE_CRON_TRANSPORT=cli
VETFLOW_QUEUE_CRON_ENABLED=false
VETFLOW_QUEUE_CRON_MAX_JOBS=25
VETFLOW_QUEUE_CRON_MAX_TIME=45
VETFLOW_QUEUE_CRON_TIMEOUT=30
VETFLOW_QUEUE_CRON_TRIES=3
VETFLOW_RELEASE_SHA=<full-40-character-git-sha>
```

CLI transport deliberately keeps `/ops/cron/queue` unavailable. It also avoids
placing an operational token in a scheduled URL or command. The database queue
and its `jobs` table remain mandatory.

After changing environment variables, rebuild the Laravel configuration cache:

```bash
php artisan config:cache
```

## hPanel Cron Job commands

hPanel escapes shell special characters such as `&` in **Custom** Cron Job
commands and rejects commands longer than 255 characters after escaping. A
command such as `cd <dir> && php artisan migrate --force` is accepted but runs
nothing, and hPanel reports no error. Use a single command that calls
`artisan` by its absolute path; Laravel resolves the application directory
from that path, so no `cd` is needed. **View output** on the Cron Job shows the
output of its last run.

## Migrations cron

The Git deployment only runs `composer install`. Migrations and the
authorization and plan catalogs are applied by an hourly **Custom** Cron Job
(minute 0):

```bash
/usr/bin/php /home/u804718109/domains/vetflowsys.com.br/public_html/artisan migrate --force --seed
```

`--seed` runs `Database\Seeders\DatabaseSeeder`, which in production only calls
the idempotent `AuthorizationSeeder` and `SaasPlanSeeder`; the demo user is
limited to the local and testing environments. Keep example data out of
`DatabaseSeeder`, because this Cron Job runs it every hour.

Ship schema changes in their own pull request, before the code that needs
them. Merge the code only after the next run, once **View output** or the
Migrations check in `/operations/report.json` shows no pending migration.

## Queue cron

Create a **Custom** Cron Job in hPanel. Use the PHP CLI path and application
directory shown by the account. The command shape is:

```bash
<absolute-php-cli> <absolute-application-directory>/artisan vetflow:queue:drain --max-jobs=25 --max-time=45 --timeout=30 --tries=3
```

Schedule it every five minutes after testing that exact command once from the
same account context. Hostinger evaluates Cron Job schedules in UTC, so convert
any business-time schedule before saving it. The queue drain itself is safe to
run every five minutes: it stops when empty, limits jobs and runtime, and uses a
lock to prevent overlapping executions.

Record the command with secrets redacted, the UTC schedule, one successful
output, and the hPanel execution timestamp in the release evidence. A successful
empty run is useful, but the runtime probe below must also prove that a real job
was consumed.

## Backup and isolated restore

Before migrations:

1. Create or confirm a fresh Hostinger database backup.
2. Download the database backup (`.sql.gz`) and record its timestamp and name.
3. Create a separate temporary MySQL database in hPanel.
4. Import the backup into that isolated database through phpMyAdmin or the
   supported account tooling.
5. Point only a temporary VetFlow checkout at the restored database.
6. Run the backup restore drill and retain its generated JSON evidence.
7. Remove the temporary credentials and database after the evidence is safely
   stored.

Never test the restore by importing into the live database. Do not use
`--backup-confirmed` merely because a backup file exists; prefer evidence from a
successful isolated restore.

## Build and deployment

Confirm how hPanel runs post-deployment commands. The release must include the
compiled Vite assets even when Node is unavailable on the web account. From the
application directory, run the provider-neutral release sequence:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=AuthorizationSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run `storage:link` only if the link is absent or broken. Persist `.env`, the
Laravel storage directory, and user uploads outside any replace-on-deploy step.

## Runtime evidence and release gate

After the Cron Job is active, prepare the runtime probe:

```bash
php artisan vetflow:runtime:probe
```

Wait for the scheduled Cron Job to run, then verify the returned probe ID:

```bash
php artisan vetflow:runtime:probe --verify --probe=<ULID> --evidence=<absolute-runtime-evidence-path>
```

Run the release gate with both evidence files:

```bash
php artisan vetflow:release:check --runtime-evidence=<absolute-runtime-evidence-path> --backup-evidence=<absolute-restore-evidence-path>
```

The gate must pass before the operational smoke test. Confirm `/up`, then open
`/ops/release` as an authorized administrator and verify:

- the exact release SHA;
- `production` environment with debug disabled;
- `database` queue and `cron` processing mode;
- writable persistent storage;
- no pending migration, runtime-probe, or restore-evidence checks.

Finish every item in the release checklist, including login, clinic context,
stock, sale, finance, clinical access, logs, and rollback readiness. Record any
failed item and roll back before allowing routine use.

## Rollback

Do not begin the release without the previous commit SHA and a restorable
pre-migration database backup. If the smoke test fails:

1. stop or disable the queue Cron Job when continued job execution could write
   incompatible data;
2. redeploy the previous known-good commit from hPanel;
3. restore the database only when the migration or application write requires
   it, following the documented rollback decision;
4. rebuild Laravel caches;
5. confirm `/up`, `/ops/release`, login, clinic context, and logs before
   reopening use.

Hostinger references:

- [Set up a Cron Job](https://support.hostinger.com/en/articles/1583465-how-to-set-up-a-cron-job-at-hostinger)
- [Deploy a Git repository](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/)
- [Create backups](https://www.hostinger.com/support/2298928-how-to-create-backups-at-hostinger/)
- [Download backups](https://www.hostinger.com/support/5981435-how-to-download-backups-at-hostinger/)
- [Manage MySQL databases](https://www.hostinger.com/support/1864454-how-to-manage-mysql-databases-in-hostinger/)
