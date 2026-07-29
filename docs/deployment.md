# Deployment Guide

This is a production-readiness guide for VetFlow. It documents the intended
deployment concerns without locking the project to one hosting provider.

## Required Runtime

- PHP 8.2 or newer.
- PHP extensions required by the application and Excel reader: DOM, Fileinfo,
  Filter, LibXML, XMLReader, and ZIP.
- Composer dependencies installed with optimized autoload.
- Node/Vite assets built before release.
- A persistent database, preferably MySQL or MariaDB for production.
- A persistent storage disk for uploaded product images and future documents.
- A queue worker if queued jobs are enabled beyond the local database queue.

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
- Back up the database before every production migration.
- Keep `clinic_id` nullable only where the code intentionally supports global
  records or historical migration state.
- Do not manually edit stock quantities in production; use inventory movement
  flows to preserve the ledger.

## Operational Checks

After deployment:

- login as an active administrator;
- confirm clinic selection/context;
- run one product lookup without requiring paid providers;
- create a manual stock entry in a test/staging clinic;
- create a draft sale and confirm it does not apply side effects;
- create a completed sale and verify stock and finance records;
- review logs for provider, storage, and mail failures.
