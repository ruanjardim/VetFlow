# Patient Species And Breeds

Code paths:

- `app/Modules/Patients`
- `resources/views/patients/catalog`
- `database/migrations/2026_08_13_010000_create_patient_taxonomy_tables.php`

## Purpose

Provides an extensible clinical catalog for patient species, breeds, varieties,
and lineages without limiting VetFlow to dogs and cats. The initial catalog
covers companion animals, birds, reptiles and amphibians, aquatic animals,
large animals, and broader wildlife classifications.

The catalog is operational rather than a substitute for scientific taxonomy.
Clinics can use the most appropriate level for their work and create a more
specific entry when the standard options do not cover the animal.

## Standard And Clinic Catalogs

`animal_species` and `animal_breeds` distinguish two origins:

- system entries, shared by all clinics and maintained by VetFlow;
- clinic entries, visible and reusable only inside the clinic that created
  them.

Species are grouped by area and breeds belong to one species. The patient form
filters the breed list after the species is selected, preventing a breed from
being linked to an unrelated species.

## The Other Option

The last option in both selectors allows the operator to enter a new species or
breed. VetFlow never stores the literal value `Other` in the patient. It creates
a normalized clinic catalog entry and links the patient to it, so the same
choice is available on the next registration.

The standalone `Cadastros` menu also exposes screens for reviewing the standard
catalog and adding clinic-specific species or breeds in advance.

## Compatibility

The existing `patients.species` and `patients.breed` text columns remain in
place for reports, integrations, imports, and historical compatibility. New
foreign keys (`animal_species_id` and `animal_breed_id`) add structure while the
service synchronizes the text snapshots.

The migration links recognized legacy values to the standard catalog. An
unrecognized legacy value appears as a prefilled custom option when edited, so
the record is not silently erased. CSV and Excel patient imports also resolve
or create catalog entries using the same rules.

## Routes And Permission

- `GET /catalog/species` lists the visible species catalog.
- `POST /catalog/species` creates a clinic species.
- `GET /catalog/breeds` lists breeds for a selected species.
- `POST /catalog/breeds` creates a clinic breed or variety.

All routes use the existing `patients.manage` permission. Clinic users can see
system entries and their own clinic entries. Global users choose the clinic
that owns a custom entry.

## Navigation

The admin sidebar is organized as an expandable navigation menu. Its groups are
Atendimento clínico, Agenda, Vendas e serviços, Estoque e compras, Financeiro,
Cadastros, and Administração. A group automatically opens when one of its child
routes is active, and permissions continue to determine which groups and links
are rendered.

## Tests

`tests/Feature/PatientTaxonomyFlowTest.php` covers:

- exotic and nontraditional species in the standard catalog;
- species-to-breed selection and patient snapshots;
- reusable clinic entries created through `Other`;
- cross-clinic rejection and catalog isolation;
- expandable menu rendering and active-group behavior.

Existing patient and implementation import tests protect compatibility with the
previous text-based input path.
