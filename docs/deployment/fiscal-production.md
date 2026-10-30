# Fiscal Production Pilot

This runbook enables real fiscal effects only for the reviewed VetFlow scope:

- Focus NFe as the provider;
- NFC-e model 65 for an RJ issuer;
- NFS-e Nacional for the Niterói pilot;
- a customer-owned Focus account and credentials;
- one explicitly activated clinic at a time.

It does not add NF-e model 55, another state, another municipality or an
automatic fiscal obligation decision. The clinic's accountant remains
responsible for validating issuer, catalog and tax treatment.

## Safety Boundary

Production requires all of these independent controls:

1. the homologation evidence gate is technically complete;
2. its latest password-confirmed human review is current and approved;
3. the clinic connects a separate encrypted Focus production token;
4. `VETFLOW_FISCAL_PRODUCTION_ENABLED=true` is configured on the server;
5. the operator enters production-only sequence and CSC references and
   confirms activation with the current VetFlow password;
6. outbound HTTP, submission and the asynchronous fiscal queue remain enabled.

Missing or revoking any clinic activation blocks new production work. Turning
off `VETFLOW_FISCAL_PRODUCTION_ENABLED`, `VETFLOW_FISCAL_SUBMISSION_ENABLED` or
`VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED` is the server-side emergency stop.
Never delete queued documents, fiscal events or an activation record.

## Provider Environments

The Focus base URLs are intentionally separate:

```dotenv
FOCUS_NFE_HOMOLOGATION_URL=https://homologacao.focusnfe.com.br
FOCUS_NFE_PRODUCTION_URL=https://api.focusnfe.com.br
```

VetFlow resolves the URL from the immutable document environment. A production
token is stored in a distinct credential row and cannot resolve a homologation
document, or vice versa. Tokens are encrypted with `APP_KEY`, hidden from
serialization and never copied into document snapshots or audit metadata.

## Pre-Go-Live Checklist

1. Back up the database and `APP_KEY`, and verify restoration evidence.
2. Complete the homologation runbook for every document type enabled by the
   clinic.
3. Confirm the latest homologation approval is current in the Fiscal center.
4. Confirm the clinic owns an active Focus account, certificate and production
   configuration.
5. For NFC-e, confirm the CNPJ is credentialed in SEFAZ-RJ and obtain the
   public CSC identifier plus an opaque external CSC reference. Never enter the
   CSC secret in VetFlow.
6. Reconcile the initial production series and next number with Focus, SEFAZ
   and the accountant. Production never reuses the homologation sequence.
7. For NFS-e, reconcile the production DPS series and next number.
8. Confirm the database queue worker and authenticated NFS-e webhook.
9. Set `VETFLOW_FISCAL_PRODUCTION_ENABLED=true`, rebuild configuration and
   verify that no clinic becomes active automatically.
10. Connect the clinic's production token in the Fiscal center.
11. Activate production with the responsible accountant/operator present.

## First Controlled Emission

1. Use a low-value real sale reviewed by the clinic and accountant.
2. Prepare only the applicable NFC-e or NFS-e from the completed sale.
3. Confirm the red **Produção** label before generating the payload.
4. Review issuer, recipient, items, payments, totals, series and number.
5. Queue exactly one document and observe the fiscal queue.
6. Confirm authorization by provider status, protected XML and DANFCe/DANFSe.
7. Reconcile the VetFlow sale, provider portal and accounting record.
8. Preserve only the allowlisted operational evidence. Never copy tokens,
   provider request bodies or customer personal data into support tickets.

The application uses stable `vf-{ulid}` provider references and reconciles a
Focus `422` conflict before treating it as a failure. Retrying a page or job
must not create a second fiscal document.

## Emergency Stop

For one clinic, use **Desativar produção** in the Fiscal center with a reason
and the operator's password. This restores its homologation profile and revokes
the production activation while preserving all issued documents.

For the whole deployment, set:

```dotenv
VETFLOW_FISCAL_PRODUCTION_ENABLED=false
VETFLOW_FISCAL_SUBMISSION_ENABLED=false
VETFLOW_FISCAL_EXTERNAL_HTTP_ENABLED=false
```

Rebuild the Laravel configuration cache and stop the fiscal queue worker while
the incident is reviewed. Already authorized documents remain legally and
operationally relevant and must not be removed.

## Official Provider References

- Environments: <https://doc.focusnfe.com.br/reference/ambiente>
- NFC-e emission: <https://doc.focusnfe.com.br/reference/emitir_nfce>
- NFS-e Nacional DPS emission: <https://doc.focusnfe.com.br/reference/emitir_dps_nacional>
