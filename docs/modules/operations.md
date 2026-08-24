# Operations

The Operations module gives authorized administrators a safe, read-only view
of the deployed release identity and its operational context.

## Boundaries

- Protected by `operations.readiness`.
- Exposes only the short Git SHA, environment name, queue mode/connection, and
  default storage disk.
- Does not expose credentials, connection strings, environment variables, file
  paths, probe sentinels, or backup contents.
- Does not change runtime configuration or execute a deployment.
- Reuses the same `ReleaseReadinessService` as the Artisan release gate, so
  the interface and command cannot drift into different technical rules.

## Main Components

- Route: `/operations`.
- Controller: `App\Modules\Operations\Controllers\OperationsController`.
- Release identity: `App\Support\Operations\ReleaseIdentityService`.
- Technical gates: `App\Support\Operations\ReleaseReadinessService`.

## Tests

`tests/Feature/OperationsConsoleTest.php` covers permission isolation and the
safe release-context presentation.
