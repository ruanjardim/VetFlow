# Security Policy

VetFlow handles operational, financial, customer, patient, and clinic data. Security reports should be handled privately and with enough detail to reproduce the issue safely.

## Supported Versions

VetFlow is in active development. Security fixes are expected to target the `main` branch unless a release branch is introduced later.

## Reporting A Vulnerability

Use GitHub private vulnerability reporting when it is enabled for this repository. If private reporting is unavailable, contact the repository owner directly and avoid posting exploitable details in a public issue.

Include:

- Affected area or module.
- Steps to reproduce.
- Expected and actual behavior.
- Potential data exposure or privilege impact.
- Any relevant environment details.

Do not include real patient, tutor, clinic, payment, NF-e, or credential data in reports.

## Security Expectations

- Authentication and active-user checks must remain enforced on protected routes.
- Permission checks must be explicit for restricted modules.
- Tenant-scoped records must not leak across clinics.
- Secrets belong in environment variables, not in source control.
- Runtime files under `storage/`, generated frontend assets, logs, and cached NF-e XML payloads must not be committed.

