# Hostinger Production Incident Response

Use this runbook for outages, suspected data corruption, credential exposure,
unexpected queue failures, or a possible privacy/security incident at
`https://vetflowsys.com.br`. It does not replace legal or LGPD advice.

## 1. Declare And Preserve

- Record detection time, reporter, symptoms, affected clinics, and the current
  full SHA from `/ops/release`.
- Preserve hPanel activity, deployment, Cron Job, application, and database
  logs. Do not paste secrets or personal/clinical data into tickets or Git.
- Capture a fresh provider backup before remediation when the platform is
  stable enough. Record UTC/BRT timestamps and SHA-256 after download.
- Use the Operations Center history as the evidence timeline. Never rewrite or
  delete operational events to make a gate appear healthy.

## 2. Contain Safely

- For a faulty release, pause only the affected write path. Disable the queue
  Cron Job when queued writes can worsen the incident.
- Revoke exposed access keys, database users, sessions, and third-party tokens
  through their owning systems. Rotate production secrets outside Git.
- Put the application in maintenance mode only when continued service risks
  data integrity or confidentiality; record who authorized the interruption.
- Never import a dump over production during triage. Restore and verify it in a
  separate database first.

## 3. Diagnose And Recover

1. Compare the deployed SHA, environment, migrations, queue configuration,
   storage writability, and recent application logs.
2. Run the synthetic runtime probe; do not use customer records as test data.
3. If the release is faulty, redeploy the previous known-good SHA and rebuild
   Laravel caches.
4. Restore the database only for confirmed corruption or an incompatible data
   migration, after an isolated restore passes and an authorized owner approves
   the production restore.
5. Re-enable the queue only after failed jobs are reviewed and safe retries are
   identified by UUID.
6. Repeat `/up`, `/ops/release`, the operational probe, and the release smoke
   checklist before reopening normal service.

## 4. Communicate And Close

- Assign an incident owner, technical owner, communications owner, and a
  rollback decision maker. Maintain timestamps in BRT and UTC.
- Notify clinics with factual impact and recovery information approved by the
  business owner. Do not speculate or expose another tenant's information.
- A suspected personal-data incident must be escalated immediately to the
  controller/DPO or designated privacy owner. They decide notification duties,
  legal deadlines, ANPD communication, and affected-data-subject outreach.
- Record root cause, affected interval, data scope, corrective actions, residual
  risk, and follow-up owner. Preserve evidence under the approved retention and
  access-control policy.

## Required Business And LGPD Decisions

Production operation still requires named owners and approved policies for:

- incident severity and after-hours contact/escalation;
- recovery-point and recovery-time objectives;
- backup retention, encryption, access, geographic location, and disposal;
- log/audit retention and legitimate purpose;
- data-subject request handling and deletion/retention conflicts;
- processor/subprocessor contracts and international transfer assessment;
- breach assessment, notification authority, and customer communications.
