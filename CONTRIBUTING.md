# Contributing To VetFlow

VetFlow is an active Laravel ERP for veterinary clinics and pet shops. Contributions should keep the product reliable, tenant-safe, and easy to operate.

## Before You Start

- Read [README.md](README.md) for setup and validation commands.
- Read [STATUS.md](STATUS.md) for the current state.
- Read [docs/PROJECT_CONTEXT.md](docs/PROJECT_CONTEXT.md) before making architectural changes.
- Use [docs/modules/_INDEX.md](docs/modules/_INDEX.md) to find the module that owns the behavior.

## Local Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```

Use the Composer development script when you need the application, queue worker, and Vite running together:

```bash
composer run dev
```

## Contribution Workflow

1. Create a focused branch.
2. Keep each change inside the owning module whenever possible.
3. Add or update tests when behavior changes.
4. Update documentation when setup, workflows, permissions, tenant behavior, or domain rules change.
5. Run the validation commands before opening a pull request.

## Engineering Guidelines

- Controllers should stay thin.
- Services own business orchestration.
- Repositories own data access where they add clarity.
- Tenant-scoped data must respect the current clinic context.
- Do not commit local secrets, cached NF-e XML files, logs, generated assets, or runtime storage files.
- Prefer explicit validation through form requests or shared validators.
- Keep product intelligence and NF-e import behavior resilient when external providers or local cache paths are unavailable.

## Pull Request Checklist

- `php artisan test` passes.
- `npm run build` passes or the PR explains why it was not run.
- New migrations are reversible and tenant-safe.
- Documentation was updated when public behavior changed.
- The change does not expose clinic data across tenants.

