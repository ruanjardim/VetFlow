# Legal and operational publication checklist

The VetFlow repository contains technically structured drafts and acceptance evidence controls. They are not legal advice and must remain in `draft` status until this checklist is completed with Brazilian counsel and the responsible business owner.

## Identity and authority

- Confirm the provider's legal name, CNPJ/CPF, registered address and commercial/privacy contacts.
- Define who may sign for the provider and what evidence is required from the customer's representative.
- Review electronic acceptance wording and evidence retention under applicable Brazilian law.

## Commercial terms

- Confirm plan prices, billing cycle, due date, payment method, delinquency handling and tax-document responsibility.
- Define cancellation, notice period, refunds, plan changes, annual adjustment index and forum.
- Review the coupon disclosure: eligible plan, 10/20/30/40 percentage, start/end, duration, non-cumulative rule, base/final/post-promotion price.
- Keep payment-gateway, automatic renewal and fiscal issuance promises out of the contract until the corresponding product scope is approved.

## LGPD and data processing

- Confirm controller/operator roles for customer, pet, clinical, employee and user data.
- Inventory infrastructure, email, monitoring, fiscal and support subprocessors and their locations.
- Define retention/deletion, incident notification, data-subject requests, export/return at termination and international-transfer safeguards.
- Review security measures and avoid promising controls or certifications not evidenced in operations.

## Support, backup and availability

- Choose real support channels and staffed hours.
- Approve measurable response/restore targets by severity.
- Approve backup frequency, retention, encryption, restore testing, RPO and RTO from observed operational capability.
- Define planned maintenance, exclusions, force majeure, customer responsibilities and incident communication.

## Publication procedure

1. Create a new version instead of editing an already accepted document.
2. Verify that all placeholders and `A DEFINIR` items are resolved.
3. Obtain the named lawyer/reviewer's approval outside VetFlow and retain the source evidence securely.
4. Compare the rendered document and SHA-256 hash with the reviewed text.
5. In global SaaS administration, confirm legal review and publish the exact version.
6. Publish all five required document types before inviting a tenant administrator to accept.
7. Export/retain the resulting package UUID and evidence according to the approved retention policy.

## Commercial gate

Task 2 of `docs/commercial-readiness.md` remains non-green until this checklist is signed off. Software controls reduce accidental publication and preserve evidence; they do not determine enforceability or replace professional legal review.
