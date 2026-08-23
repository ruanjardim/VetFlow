# Deployment Guide

This is a production-readiness guide for VetFlow. It documents the intended
deployment concerns without locking the project to one hosting provider.

Provider-specific staging instructions live in the
[KingHost staging runbook](deployment/kinghost-staging.md).

## Required Runtime

- PHP 8.2 or newer.
- PHP extensions required by the application and Excel reader: DOM, Fileinfo,
  Filter, LibXML, XMLReader, and ZIP.
- Composer dependencies installed with optimized autoload.
- Node/Vite assets built before release.
- A persistent database, preferably MySQL or MariaDB for production.
- A persistent storage disk for uploaded product images and future documents.
- A queue worker, or the explicitly bounded staging cron bridge documented for
  a provider that cannot supervise a permanent worker.

## Environment

Start from `.env.example` and set production values:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
VETFLOW_SEED_DEMO_USER=false
VETFLOW_QUEUE_MODE=worker
```

Generate a real `APP_KEY` during provisioning:

```bash
php artisan key:generate
```

## Build And Release

Recommended release sequence:

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

## Storage

VetFlow currently stores uploaded product images and lookup images through the
Laravel filesystem. In production:

- run `php artisan storage:link` when using local public storage;
- persist the storage directory across deploys;
- do not commit uploaded files, cached NF-e XML, or provider payload dumps.

## Database

- Run migrations through the deployment pipeline.
- Run `AuthorizationSeeder` after migrations to synchronize permissions and
  standard role presets. It is idempotent and should not be replaced by manual
  role edits.
- Back up the database before every production migration.
- Keep `clinic_id` nullable only where the code intentionally supports global
  records or historical migration state.
- Do not manually edit stock quantities in production; use inventory movement
  flows to preserve the ledger.

## Operational Checks

Run the automated runtime check after migrations and caches are ready:

```bash
php artisan vetflow:runtime:probe
php artisan vetflow:runtime:probe --verify --probe=<ULID> \
  --evidence=/secure/evidence/runtime-evidence.json
php artisan vetflow:release:check \
  --runtime-evidence=/secure/evidence/runtime-evidence.json \
  --backup-evidence=/secure/evidence/restore-evidence.json
```

Follow the [backup restore drill](deployment/backup-restore-drill.md) and prefer
its generated evidence. Use `--backup-confirmed` only as a documented manual
fallback after an operator has verified that a restorable database backup
exists for the release. The command also checks the application
key, production debug/HTTPS settings, database connectivity, pending
migrations, logging, queue configuration, the `jobs` table when applicable,
and a temporary write/delete probe on the configured storage disk. The
[runtime operations probe](deployment/runtime-operations-probe.md) separately
proves that a real asynchronous job can read the prepared persistent marker and
write a verifiable result.

After deployment:

- login as an active administrator;
- confirm clinic selection/context;
- run one product lookup without requiring paid providers;
- create a manual stock entry in a test/staging clinic;
- create a draft sale and confirm it does not apply side effects;
- create a completed sale and verify stock and finance records;
- review logs for provider, storage, and mail failures.

When `VETFLOW_QUEUE_MODE=cron`, the runtime check also requires the database
queue, an enabled operational endpoint, a token of at least 32 characters, and
execution limits below the hosting request timeout.

Use the complete [release checklist](release-checklist.md) to record the
pre-release validation, rollback decision, smoke tests, and release evidence.
