# VetFlow Module Index

This index maps the current code modules and the documentation that should eventually describe each one.

## Implemented Or Present In Code

| Module | Purpose | Documentation status |
| --- | --- | --- |
| Appointments | Appointment workflows | Covered in [Clinical Core](clinical-core.md) |
| Clients | Client-related area | Pending module doc |
| ClinicProducts | Clinic-specific product association | Pending module doc |
| Clinics | Clinic administration and tenant foundation | Partially covered in database docs |
| Dashboard | Operational dashboard data and widgets | Pending module doc |
| Finance | Finance-related area | Pending review; overlaps with Financial |
| Financial | Financial transactions and payable/receivable flows | Documented in [Financial](financial.md) |
| Implementation | Implementation/onboarding flow | Pending module doc |
| Inventory | Stock movement and lot/expiration control | Documented in [Inventory](inventory.md) |
| MedicalRecords | Clinical records area | Pending implementation/doc review |
| Patients | Pets/patients | Covered in [Clinical Core](clinical-core.md) |
| Pets | Pet-related area | Pending review; overlaps with Patients |
| PetShopServices | Pet shop service catalog | Covered in [Clinical Core](clinical-core.md) |
| ProductIntelligence | Global product data, suggestions, GTIN intelligence | Documented in [Product Intelligence](product-intelligence.md) |
| Products | Local product catalog and lookup | Documented in [Products](products.md) |
| PurchaseEntries | Purchase entry and NF-e import flows | Documented in [Purchase Entries](purchase-entries.md) |
| Reports | Reporting area | Pending implementation/doc review |
| Sales | Sales, sale items, payments, returns, cash register support | Documented in [Sales](sales.md) |
| Schedules | Scheduling base | Covered in [Clinical Core](clinical-core.md) |
| ServiceOrders | Service orders and service order items | Covered in [Clinical Core](clinical-core.md) |
| Suppliers | Supplier management | Pending module doc |
| Tutors | Tutor/customer records | Covered in [Clinical Core](clinical-core.md) |
| Users | User records and access foundation | Partially covered in database docs |
| Validation | Shared validation services/controllers | Pending module doc |

## Priority For Module Documentation

1. Dashboard and reporting documentation.
2. Clinics, Users, Roles, and Permissions as operational docs, beyond database
   notes.
3. Suppliers and PetShopServices as standalone docs if their business rules grow.
4. Validation and shared support services.

## Suggested Module Doc Template

Each module doc should answer:

- What business problem does this module solve?
- Which routes/controllers/services/models belong to it?
- Which tables does it own?
- Which permissions protect it?
- Which modules does it depend on?
- Which tests cover it?
- What is intentionally out of scope?
