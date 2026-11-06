# Commercial readiness

This checklist separates a feature that exists in code from a capability that can be promised without qualification during a sale. A green item must have working software, documented operating limits, and the necessary production evidence. Copy changes alone never turn an external dependency into a green item.

## Five-task launch cycle

| Task | Commercial gate | Current state | Evidence required to close |
| --- | --- | --- | --- |
| 1 | Public plans and scope | Completed in the launch-pricing delivery | Landing page, internal SaaS plans and user limits show the same values; fiscal and hardware extras are explicit |
| 2 | LGPD, contracts and support | Pending | Privacy notice, terms, data-processing responsibilities, support channels, backup/restore policy and legal review |
| 3 | Operational scale | Pending | Monitoring and restore drill, incident runbook, support SLA and evidence from the first assisted customers |
| 4 | Fiscal activation | Controlled scope | Establishment-owned provider account, accountant validation and the existing homologation and production gates for the supported municipality/state |
| 5 | Direct scale integration | Model-specific | Selected manufacturer/model, documented protocol, bench test and pilot evidence; EAN variable-weight labels remain the default supported path |

## Sales boundary

- The online PDV, offline continuity with a prepared device, cash, stock, finance, grooming operation, non-fiscal receipt and EAN variable-weight label workflow can be sold inside their documented limits.
- Direct USB/serial communication with a scale is not advertised as generic compatibility. It is a hardware project until one model passes the evidence gate.
- Fiscal issuance is never included implicitly in a plan. It is activated separately after configuration, provider credentials, accountant review and homologation.
- The first customers are an assisted paid pilot. Broad self-service or national-scale marketing starts only after support, restore and field-usage evidence exists.

The canonical technical limits remain in `docs/modules/sales.md`, `docs/modules/fiscal.md`, `docs/modules/saas.md`, and `docs/deployment/hostinger-production.md`.
