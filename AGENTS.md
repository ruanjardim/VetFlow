# VetFlow Agent Guide

This file is for Codex or any AI-assisted development session working in this repository.

## Start Here

1. Read `STATUS.md`.
2. Read `docs/PROJECT_CONTEXT.md`.
3. Read only the module or architecture docs relevant to the current task.
4. Check `git status --short` before editing.

## Ground Rules

- Do not invent business rules. If the rule is not in code, docs, or the user's request, mark it as an assumption.
- Keep changes scoped to the requested module or workflow.
- Do not commit cached NF-e XML files, secrets, local databases, logs, or generated dependency folders.
- Preserve the modular Laravel structure under `app/Modules`.
- Prefer services for business orchestration and repositories for meaningful data access.
- Keep controllers thin.
- Update documentation when behavior, setup, or architecture changes.

## Validation

For backend changes, prefer:

```bash
php artisan test
```

For frontend asset changes, prefer:

```bash
npm run build
```

For route/config/cache issues:

```bash
php artisan optimize:clear
php artisan route:list
```

If a validation command cannot be run, explain why in the final response.
