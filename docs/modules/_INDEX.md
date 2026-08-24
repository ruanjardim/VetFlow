# VetFlow Module Index

This index maps the current code modules and the documentation that should eventually describe each one.

## Implemented Or Present In Code

| Module | Purpose | Documentation status |
| --- | --- | --- |
| Access | Collaborator records and role preset assignment | Documented in [Access](access.md) |
| Audit | Append-only history for sensitive administrative changes | Documented in [Administrative Audit Trail](audit.md) |
| Appointments | Appointment workflows and assisted contact follow-up | Documented in [Appointment Reminders](appointment-reminders.md) and [Clinical Core](clinical-core.md) |
| Clients | Client-related area | Pending module doc |
| ClinicProducts | Clinic-specific product association | Pending module doc |
| Clinics | Clinic administration and tenant foundation | Documented in [Clinics](clinics.md) |
| Dashboard | Operational dashboard data and widgets | Documented in [Dashboard](dashboard.md) |
| Finance | Finance-related area | Pending review; overlaps with Financial |
| Financial | Financial transactions and payable/receivable flows | Documented in [Financial](financial.md) |
| Implementation | Assisted imports, data-quality review, versioned pilot planning, and evidence-bound readiness decisions | Documented in [Implementation](implementation.md) |
| Inventory | Stock movement and lot/expiration control | Documented in [Inventory](inventory.md) |
| Hospitalizations | Patient admission, append-only evolution diary, and discharge record | Documented in [Hospitalizations](hospitalizations.md) |
| MedicalRecords | Clinical records linked to appointments | Documented in [Medical Records](medical-records.md) |
| Operations | Protected release and environment readiness console | Documented in [Operations](operations.md) |
| ExamResults | Protected result lifecycle for structured exam requests | Documented in [Exam Results](exam-results.md) |
| Prescriptions | Structured clinical prescriptions with protected lifecycle | Documented in [Prescriptions](prescriptions.md) |
| Vaccinations | Patient vaccination schedules and applications | Documented in [Vaccinations](vaccinations.md) |
| Patients | Pets/patients with extensible taxonomy, clinical alerts, and longitudinal timeline | Documented in [Patient Taxonomy](patient-taxonomy.md), [Patient Clinical Alerts](patient-clinical-alerts.md), [Patient Clinical Timeline](patient-clinical-timeline.md), and [Clinical Core](clinical-core.md) |
| Pets | Pet-related area | Pending review; overlaps with Patients |
| PetShopServices | Pet shop service catalog | Covered in [Clinical Core](clinical-core.md) |
| ProductIntelligence | Global product data, suggestions, GTIN intelligence | Documented in [Product Intelligence](product-intelligence.md) |
| Products | Local product catalog and lookup | Documented in [Products](products.md) |
| PurchaseEntries | Purchase entry and NF-e import flows | Documented in [Purchase Entries](purchase-entries.md) |
| Reports | Reporting area | Pending implementation/doc review |
| Sales | Sales, sale items, payments, returns, cash register support | Documented in [Sales](sales.md) |
| Commissions | Seller commission rules and period previews | Documented in [Commissions](commissions.md) |
| Schedules | Scheduling base | Covered in [Clinical Core](clinical-core.md) |
| ServiceOrders | Service orders and service order items | Covered in [Clinical Core](clinical-core.md) |
| Suppliers | Supplier management | Documented in [Suppliers](suppliers.md) |
| Tutors | Responsible person records for patients | Documented in [Responsáveis](tutors.md) |
| Users | User records and access foundation | Documented in [Access](access.md) and database docs |
| Validation | Shared validation services/controllers | Documented in [Validation](validation.md) |

## Priority For Module Documentation

1. Reporting documentation.
2. PetShopServices as a standalone doc if its business rules grow.
3. Clients and other present-but-thin module areas.

## Suggested Module Doc Template

Each module doc should answer:

- What business problem does this module solve?
- Which routes/controllers/services/models belong to it?
- Which tables does it own?
- Which permissions protect it?
- Which modules does it depend on?
- Which tests cover it?
- What is intentionally out of scope?
