# VetFlow Engineering Process

This document defines the working process for technical changes in VetFlow.

## Default Flow

1. Understand the requested business behavior.
2. Check the current code and documentation before editing.
3. Keep the change scoped to the smallest module or workflow that solves the problem.
4. Update tests when behavior changes.
5. Update documentation when setup, architecture, or business behavior changes.
6. Run the relevant validation commands.
7. Summarize what changed, what was validated, and what remains risky.

## Before Editing

Run:

```bash
git status --short
```

If the working tree has unrelated changes, do not revert them. Work around them or ask for direction only when they block the task.

## Backend Changes

For Laravel backend work:

- Keep controllers thin.
- Put business orchestration in services.
- Use repositories for meaningful data-access logic.
- Use requests for validation.
- Use middleware/policies for access control.
- Keep module boundaries clear.

Preferred validation:

```bash
php artisan test
php artisan route:list
```

When diagnosing framework state:

```bash
php artisan optimize:clear
```

## Frontend And Blade Changes

For Blade, CSS, or JavaScript work:

- Use `data-*` attributes for JavaScript hooks.
- Avoid selecting elements by visual classes when behavior is involved.
- Keep JavaScript modules focused and reusable.
- Validate the page in the browser when layout or interaction changes.

Preferred validation:

```bash
npm run build
```

## Database Changes

For migrations:

- Use explicit names and fields.
- Add indexes where lookup/filter behavior needs them.
- Keep tenant/clinic ownership clear.
- Update database documentation when adding or changing core tables.

Preferred validation:

```bash
php artisan migrate
php artisan test
```

## Documentation Changes

Use this ownership model:

- Current mutable state: root `STATUS.md`.
- Public onboarding: root `README.md`.
- Stable project facts: `docs/PROJECT_CONTEXT.md`.
- Architecture rules: `docs/ARQUITETURA.md`, `docs/backend-architecture.md`, `docs/frontend-architecture.md`.
- Module behavior: `docs/modules/`.
- Audits and investigations: `docs/audits/`.

## Release Readiness

A change is not release-ready until:

- The working tree contains only intentional changes.
- Tests/build relevant to the touched area were run or explicitly documented as not run.
- New environment variables are documented.
- New sensitive storage paths are ignored or documented.
- Any user-facing behavior change is reflected in docs or release notes.
