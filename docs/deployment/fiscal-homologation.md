# Fiscal Homologation Activation

This runbook activates the Focus NFe adapter for the reviewed Niterói/RJ NFS-e
Nacional pilot and the RJ NFC-e model 65 homologation flow. It does not enable
production issuance.

## Safety Boundary

Keep every fiscal runtime switch disabled until the clinic profile, tax
catalog, DPS/NFC-e sequences, accountant review and queue are ready. The
authenticated webhook is an additional requirement for NFS-e recovery.
Selecting Focus in the VetFlow interface never changes server runtime
configuration.

The Focus subscription belongs to the clinic. In customer-managed mode, the
token may be entered only in VetFlow's protected connection form, which
requires the current user's password and encrypts the value with `APP_KEY`
before persistence. It must never be committed to Git, included in support
tickets, copied to profile fields or printed in deployment logs.

## Customer-Owned Focus Account

The recommended SaaS mode is `encrypted_database`. Each clinic contracts Focus
directly, owns its certificate and provider configuration, and connects only
its homologation token. VetFlow stores one tenant-scoped credential reference
in the profile; the encrypted token is hidden from serialization, documents,
events and every later interface response. Rotation and disconnection require
the operator's current VetFlow password and produce secret-free audit events.

`APP_KEY` is part of this protection boundary. Back it up securely and never
replace it casually: losing or changing it without a controlled re-encryption
procedure makes every connected clinic token unreadable.

Disconnecting erases the encrypted token, revokes its reference and blocks new
submissions, consultations, cancellations and inutilizations for that clinic.
It does not affect PDV operation or the explicitly non-fiscal receipt.

## External Credential File Alternative

Create a JSON file outside both the repository and the public document root.
On Linux, restrict it to the deployment user with mode `0600`. Use an absolute
path in the environment configuration.

```json
{
  "schema_version": 1,
  "credentials": {
    "vault://vetflow/focus/niteroi": {
      "provider": "focus_nfe",
      "environment": "homologation",
      "token": "<FOCUS_HOMOLOGATION_TOKEN>"
    }
  }
}
```

The reference must exactly match the clinic's fiscal profile. VetFlow rejects
a missing reference, provider/environment mismatch, malformed token, invalid
JSON, oversized file, or any credential file located inside the application.
The file is read at request/job time, so an atomic replacement rotates the
token without changing fiscal documents or storing a secret in the database.

## Pre-activation Configuration

Start with outbound traffic and submission disabled:

```dotenv
VETFLOW_FISCAL_PROVIDER_ADAPTER=focus_nfe
VETFLOW_FISCAL_CREDENTIAL_DRIVER=encrypted_database
VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED=false
VETFLOW_FISCAL_SUBMISSION_ENABLED=false
VETFLOW_FISCAL_PRODUCTION_ENABLED=false
VETFLOW_FISCAL_QUEUE_CONNECTION=database
VETFLOW_FISCAL_QUEUE=fiscal
VETFLOW_FISCAL_PROCESSING_STALE_MINUTES=180
VETFLOW_FISCAL_CANCELLATION_STALE_MINUTES=30
VETFLOW_FISCAL_ALERT_MAX_ITEMS=20
VETFLOW_FISCAL_WEBHOOK_RESEND_COOLDOWN_MINUTES=5
VETFLOW_FISCAL_ARTIFACT_MAX_BYTES=10485760
VETFLOW_FISCAL_ARTIFACT_REDIRECT_HOSTS=
VETFLOW_NFCE_CANCELLATION_WINDOW_MINUTES=30
FOCUS_NFE_WEBHOOK_ENABLED=false
FOCUS_NFE_WEBHOOK_AUTHORIZATION_HEADER=X-VetFlow-Focus-Webhook
FOCUS_NFE_WEBHOOK_AUTHORIZATION_TOKEN=<RANDOM_VALUE_WITH_AT_LEAST_32_CHARACTERS>
FOCUS_NFE_PRODUCTION_URL=https://api.focusnfe.com.br
```

Use `external_file` plus `VETFLOW_FISCAL_CREDENTIAL_FILE` only for an
operations-managed deployment that intentionally does not allow clinic
self-service. Rebuild the Laravel configuration cache after environment
changes. In customer-managed mode, an authorized clinic operator then connects
its Focus token in the Fiscal center. Confirm that the adapter, encrypted
credential, clinic reference and asynchronous queue are reported as ready.
The screen never displays the complete token, resolved reference or webhook
secret.

## Webhook Registration

For NFS-e recovery, register the HTTPS URL below in the Focus homologation
account for the `nfsen` event:

```text
https://<vetflow-host>/webhooks/focus-nfe/nfsen
```

Configure Focus to send the exact header name and authorization value from the
deployment environment. Then set `FOCUS_NFE_WEBHOOK_ENABLED=true`, rebuild the
configuration cache, and confirm that an absent/invalid header receives `401`
while an unknown document with the valid header receives `204`.

Focus documents that accepted initial validation are processed
asynchronously. VetFlow therefore keeps both the authenticated webhook and
bounded status reconciliation available.

## Controlled Enablement

1. Confirm the clinic owns an active Focus account and is configured for
   Niterói (`3303302`), homologation and the applicable reviewed DPS/NFC-e
   sequence. Connect its homologation token through the protected form. For
   NFC-e, also confirm IE, SEFAZ-RJ credentialing,
   certificate, public CSC ID, external CSC reference and product tax data.
2. Confirm adapter, credential, reference and asynchronous queue checks in the
   Fiscal center. If NFS-e is enabled, also complete the webhook check.
3. Have the clinic's accountant approve issuer, service, ISS and IBS/CBS data
   in the generated payload using non-production data.
4. Set `VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED=true` and rebuild configuration.
5. Set `VETFLOW_FISCAL_SUBMISSION_ENABLED=true` only for the supervised pilot,
   rebuild configuration and verify that the applicable operation reports
   homologation available. The full `Pronta para piloto` indicator additionally
   requires the NFS-e webhook.
6. Queue one reviewed document of each enabled type, observe the database queue, provider response,
   webhook/poll reconciliation and append-only events before proceeding.
7. Cancel one authorized test document with a non-sensitive justification.
   Confirm the queued event, provider confirmation, final timestamp and the
   absence of a duplicate request after refreshing or repeating the action.
   For NFC-e, do this within the configured 30-minute operational window.
8. With the accountant, create a harmless skipped NFC-e range in homologation,
   confirm that no prepared document owns a number in it, and exercise the
   separately confirmed inutilization queue once.
9. Preserve redacted evidence for accepted, authorized, rejected, cancelled
   and delayed-processing scenarios. Never preserve tokens or personal payloads
   in the evidence package.
10. In the Fiscal center, confirm that every enabled-type row in **Gate seguro
    de homologação** is marked as proven. Record a follow-up while anything is
    pending; the approval option is intentionally unavailable before that.
11. Have the responsible operator/accountant confirm the current password and
    append the final review. A later profile or tax-catalog revision marks that
    approval stale and requires another review.
12. Print **Abrir dossiê** for the human handoff or download its JSON version
    for controlled evidence storage. Confirm that neither output contains
    tokens, credential/certificate references, raw provider payloads or
    document identifiers.

An **Apta para análise técnica** badge does not enable production, change
credentials or alter the document environment. It only unlocks the separate,
fail-closed procedure documented in
[`fiscal-production.md`](fiscal-production.md). Production still requires its
own token, server gate, sequences, CSC reference and password-confirmed clinic
activation.

## Operational Alerts And Recovery

The Fiscal center surfaces exhausted transmission, consultation and
cancellation attempts. It also warns when processing exceeds 180 minutes or a
cancellation remains queued for more than 30 minutes by default. Adjust those
thresholds only from observed homologation behavior, and keep the visible list
bounded with `VETFLOW_FISCAL_ALERT_MAX_ITEMS`.

Before retrying a failed cancellation, check the document by its stable Focus
reference using the protected reconciliation action. If Focus reports it as
cancelled, VetFlow closes the cancellation without submitting a new reason. If
it remains authorized, the failed state and frozen reason are retained for a
reviewed retry. Never rewrite the justification or delete the failed event.

If a webhook was lost, the protected resend action asks Focus to notify every
registered trigger and applies a local cooldown. Do not use it until the
VetFlow callback is enabled and authenticated. A failed request is recorded
without provider body or credential data.

XML, DANFSe and DANFCe downloads use only relative paths received in the provider's
allowlisted response. If Focus redirects a file to a CDN, add only the exact
HTTPS hostname observed and reviewed in homologation to
`VETFLOW_FISCAL_ARTIFACT_REDIRECT_HOSTS`. Comma-separate multiple exact hosts;
wildcards are not supported. VetFlow deliberately omits its Focus Basic Auth
header from the redirected request. Keep the list empty until that review.

## Immediate Stop And Rotation

To stop new submissions, set `VETFLOW_FISCAL_SUBMISSION_ENABLED=false` and
rebuild configuration. To stop all outbound fiscal HTTP, also set
`VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED=false`. Do not delete queued documents or
their events.

For a customer-managed token rotation, use **Substituir o token de
homologação** in the clinic's Fiscal center and confirm with the operator's
current password. If compromise is suspected, first disable outbound HTTP,
disconnect the clinic account so the stored ciphertext is erased, rotate both
the Focus token and webhook authorization value, reconnect, test authentication
and only then re-enable the pilot. External-file deployments rotate by atomic
replacement of the private JSON file with the same reference.

## Official Focus References

- Environments and authentication:
  <https://doc.focusnfe.com.br/reference/ambiente>
- NFS-e Nacional submission:
  <https://doc.focusnfe.com.br/reference/emitir_dps_nacional>
- NFS-e Nacional consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfse_nacional>
- NFS-e Nacional cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfse_nacional>
- NFS-e Nacional webhook resend:
  <https://doc.focusnfe.com.br/reference/reenviar_hook_nfsen>
- NFS-e Nacional XML download:
  <https://doc.focusnfe.com.br/reference/consultar_nfsen_recebida_individual_xml>
- NFS-e Nacional DANFSe PDF download:
  <https://doc.focusnfe.com.br/reference/consultar_nfsen_recebida_individual_pdf>
- Webhook behavior and retries:
  <https://doc.focusnfe.com.br/reference/webhooks>
- Webhook creation and custom authorization header:
  <https://doc.focusnfe.com.br/reference/criar_webhook>
- NFC-e synchronous emission contract:
  <https://doc.focusnfe.com.br/reference/emitir_nfce>
- NFC-e consultation:
  <https://doc.focusnfe.com.br/reference/consultar_nfce>
- NFC-e cancellation:
  <https://doc.focusnfe.com.br/reference/cancelar_nfce>
- NFC-e numbering inutilization:
  <https://doc.focusnfe.com.br/reference/inutilizar_numeracao_nfce>
- NFC-e inutilization consultation:
  <https://doc.focusnfe.com.br/reference/consultar_inutilizacoes_nfce>
- Focus company fields for homologation CSC/token setup:
  <https://doc.focusnfe.com.br/reference/atualizar_empresa>
- SEFAZ-RJ NFC-e requirements, CSC and numbering:
  <https://portal.fazenda.rj.gov.br/dfe/wp-content/uploads/sites/17/2023/01/DF-e_NFC-e.pdf>
