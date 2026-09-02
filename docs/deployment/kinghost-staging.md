# KingHost Staging Runbook

Updated: 2026-08-23

This runbook prepares the first VetFlow staging environment on KingHost shared
Linux hosting. It is intentionally limited to fictitious staging data. It does
not authorize a production pilot with real clinic, patient, financial, or
fiscal data.

## 1. Contract Gate

Before paying, confirm in the checkout or with KingHost support that the chosen
plan provides all of the following:

- Linux hosting with PHP 8.2 or newer;
- DOM, Fileinfo, Filter, LibXML, XMLReader, and ZIP PHP extensions;
- MariaDB/MySQL and enough database storage for the pilot;
- SSH or a supported release mechanism that can run Composer and Artisan;
- Git integration or an equivalent controlled upload path;
- a document root that can point to VetFlow's `public` directory;
- persistent files under `storage/app/public`;
- HTTPS before login is enabled;
- the Cronjob add-on with the `X-Cron-Auth` request header.

Stop the purchase/deployment if the document root cannot be restricted to
`public`, PHP 8.2 is unavailable, or the required XML/ZIP extensions are
missing. Do not expose the repository root or move `.env` into a public path.

## 2. Staging Topology

- One VetFlow application in a Linux shared-hosting account.
- One isolated MariaDB/MySQL database.
- Database-backed sessions, cache, and queue.
- Persistent local Laravel filesystem with `storage:link`.
- A bounded queue-drain request triggered by KingHost Cronjob.
- Mail kept on the `log` driver until a transactional provider is approved.
- Fictitious staging clinic and records only.

Shared hosting does not provide a permanently supervised queue worker. VetFlow
therefore exposes a disabled-by-default operational endpoint that processes a
small queue batch and exits. It is not a substitute for a permanent worker when
the application reaches production volume.

## 3. Release Candidate

Validate the exact commit locally before upload:

```bash
composer validate --no-check-publish
php artisan test
npm ci
npm run build
git status --short
```

Record the commit SHA. Do not upload local `.env`, SQLite databases, logs,
cached NF-e XML, `node_modules`, or test fixtures containing credentials.

## 4. Environment

Create `.env` directly in the non-public application root. Use values issued
by the hosting panel and never commit them.

```dotenv
APP_NAME="VetFlow Staging"
APP_ENV=staging
APP_KEY=
APP_DEBUG=false
APP_URL=https://staging-address.example

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=provided-by-kinghost
DB_PORT=3306
DB_DATABASE=provided-by-kinghost
DB_USERNAME=provided-by-kinghost
DB_PASSWORD=provided-by-kinghost

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log

VETFLOW_SEED_DEMO_USER=false
VETFLOW_QUEUE_MODE=cron
VETFLOW_QUEUE_CRON_ENABLED=true
VETFLOW_QUEUE_CRON_TOKEN=copy-the-panel-token-here
VETFLOW_QUEUE_CRON_HEADER=X-Cron-Auth
VETFLOW_QUEUE_CRON_MAX_JOBS=25
VETFLOW_QUEUE_CRON_MAX_TIME=45
VETFLOW_QUEUE_CRON_TIMEOUT=30
VETFLOW_QUEUE_CRON_TRIES=3
```

The cron token must contain at least 32 characters. Generate or copy it only in
the KingHost panel/server environment; do not send it through chat, issue text,
commit messages, logs, or screenshots.

Generate the application key on the server after `.env` exists:

```bash
php artisan key:generate
```

## 5. Server Release

From the non-public application root:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=AuthorizationSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The Vite build produced by the validated release candidate must be present in
the uploaded artifact. Because `public/build` is intentionally ignored by Git,
upload that directory through the controlled release/SFTP path after the Git
checkout, or run `npm ci && npm run build` on the server only when Node is
officially supported. Do not commit generated build output. If Composer or
Artisan cannot run through the supported SSH/release mechanism, stop rather
than committing `vendor` or moving private files into the web root.

## 6. Controlled Queue Cron

In KingHost Cronjob, configure a request to:

```text
https://staging-address.example/ops/cron/queue
```

Start with an interval of five minutes (`*/5`). Set the `.env` token to the
secret value that KingHost sends in `X-Cron-Auth`, then rebuild the config cache.

Expected responses:

- `404`: the endpoint is disabled;
- `403`: the header/token does not match;
- `204`: the bounded queue cycle completed or the queue was empty;
- `503`: the operational configuration or queue cycle failed.

Each cycle processes at most 25 jobs for at most 45 seconds. A cache lock skips
overlapping invocations. The endpoint never returns Artisan output or secrets.

After any change to the queue settings:

```bash
php artisan config:cache
php artisan vetflow:runtime:probe
```

Record the printed ULID, let the authenticated cron cycle process it, and then
generate evidence as described in the
[runtime operations probe](runtime-operations-probe.md). Preparing before a
normal deploy and processing afterward also verifies the configured storage
across that lifecycle boundary.

## 7. Backup And Restore Drill

Before the first migration against staging:

1. Export the staging database through the supported KingHost database tool.
2. Verify that the export exists, is non-empty, and is stored outside the
   application deployment directory.
3. Create a separate temporary database.
4. Import the export into that temporary database.
5. Point a temporary local or isolated staging configuration to the restored
   database and run a read-only login/clinic/record-count check.
6. Record the backup identifier, restore time, result, operator, and cleanup
   decision without recording credentials.

Use the repository's [backup restore drill](backup-restore-drill.md) to capture
control totals and produce a recent evidence file. Only after the isolated
restore and runtime probe succeed may the operator run:

```bash
php artisan vetflow:release:check \
  --runtime-evidence=/secure/evidence/runtime-evidence.json \
  --backup-evidence=/secure/evidence/restore-evidence.json
```

The commands do not create or import the provider backup; they verify the
restored database without changing its records.

## 8. Smoke Tests

Follow the repository release checklist and additionally confirm:

- `/up` returns success over HTTPS;
- the repository root, `.env`, `storage/logs`, and cached XML are not web
  accessible;
- an invalid cron token receives `403` and the valid KingHost job receives
  `204`;
- the runtime-probe job disappears from the `jobs` table and produces approved
  evidence;
- `failed_jobs` remains empty after the queue probe;
- the runtime-probe sentinel crosses the selected restart/deploy boundary;
- only fictitious records exist in the staging clinic.

## 9. Rollback

- Preserve the previous release artifact and its commit SHA.
- Do not run a destructive migration without a verified restore path.
- If application rollback is sufficient, restore the previous artifact and
  rebuild Laravel caches.
- If a migration changed data incompatibly, stop traffic first and use the
  recorded database restore decision.
- Disable `VETFLOW_QUEUE_CRON_ENABLED` and rebuild config cache if queue
  processing must be stopped independently.

Do not proceed to real pilot data until backup restoration, storage persistence,
queue processing, tenant isolation, and the full smoke checklist are recorded
as successful.
