# Continuous Integration

VetFlow should run a lightweight CI pipeline on every pull request and push to
`main`.

## Workflow

The GitHub Actions workflow lives at:

```text
.github/workflows/ci.yml
```

It validates:

- PHP dependencies can be installed.
- Node dependencies can be installed.
- Laravel can boot with the example environment.
- Database migrations run on SQLite.
- Feature/unit tests pass.
- Vite production assets build.

## Local Equivalent

Run these commands before pushing:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
npm run build
```

## Notes

- CI uses SQLite because the repository is already configured for SQLite local
  development.
- Production can still use MySQL or MariaDB later.
- External GTIN providers should remain optional in CI. Tests should not depend
  on live third-party APIs.
