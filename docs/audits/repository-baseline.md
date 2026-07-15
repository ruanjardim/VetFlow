# Sprint 0 - Repository Baseline

Data da auditoria: 2026-07-15
Projeto auditado: VetFlow 1.0 - Commercial Edition
Pasta versionada auditada: `C:\Users\ruanj\Documents\Codex\2026-07-08\ama\work\VetFlow`
Remoto Git: `https://github.com/ruanjardim/VetFlow.git`

## Status

PARCIAL.

A baseline Git foi coletada e o commit local esta sincronizado com `origin/main`, mas a arvore de trabalho nao esta limpa. Existem alteracoes locais relevantes e migrations aplicadas no banco local que ainda nao estao versionadas. Portanto, ainda nao e seguro iniciar a Sprint 1 como se o GitHub representasse todo o estado atual do VetFlow.

## Comandos executados

- `git status -sb`
- `git log --oneline -10`
- `git fetch origin`
- `git status`
- `git diff --stat`
- `git diff --name-status`
- `git diff --cached --stat`
- `git diff --cached --name-status`
- `git branch -vv`
- `git rev-parse HEAD`
- `git rev-parse origin/main`
- `git rev-list --left-right --count HEAD...origin/main`
- `php artisan migrate:status`
- `php artisan test`
- `npm run build` e `npm.cmd run build`

## Git baseline

Branch atual: `main`

HEAD local:

```text
621c3041f00598a5451d43675d08fd49f3a095f9
```

`origin/main`:

```text
621c3041f00598a5451d43675d08fd49f3a095f9
```

Ultimos commits:

```text
621c304 Fix sales returns and draft deletion
b0bb8fd Initial VetFlow project snapshot
```

Divergencia local/remoto:

```text
HEAD...origin/main = 0 local / 0 remoto
```

Conclusao: a branch local `main` esta apontando para o mesmo commit de `origin/main`, mas ha alteracoes locais nao commitadas.

## Estado da arvore de trabalho

Resumo do diff rastreado:

```text
26 files changed, 3655 insertions(+), 124 deletions(-)
```

Arquivos modificados nao staged:

```text
app/Modules/Dashboard/Services/DashboardDataService.php
app/Modules/Financial/Controllers/FinancialTransactionController.php
app/Modules/Financial/Services/FinancialTransactionService.php
app/Modules/Inventory/Models/InventoryMovement.php
app/Modules/ProductIntelligence/Services/GlobalProductCatalogService.php
app/Modules/ProductIntelligence/Services/ProductIntelligenceService.php
app/Modules/Products/Controllers/ProductController.php
app/Modules/Products/Routes/web.php
app/Modules/Products/Services/ProductService.php
app/Modules/PurchaseEntries/Controllers/PurchaseEntryController.php
app/Modules/PurchaseEntries/Models/PurchaseEntry.php
app/Modules/PurchaseEntries/Models/PurchaseEntryItem.php
app/Modules/PurchaseEntries/Requests/StorePurchaseEntryRequest.php
app/Modules/PurchaseEntries/Routes/web.php
app/Modules/PurchaseEntries/Services/PurchaseEntryService.php
resources/css/app.css
resources/js/app.js
resources/views/dashboard/index.blade.php
resources/views/financial-transactions/index.blade.php
resources/views/layouts/admin.blade.php
resources/views/products/global-catalog.blade.php
resources/views/products/index.blade.php
resources/views/purchase-entries/form.blade.php
resources/views/purchase-entries/index.blade.php
routes/console.php
routes/web.php
```

Arquivos novos nao rastreados:

```text
app/Console/Commands/BackfillGlobalProductsCommand.php
app/Modules/Dashboard/Services/DashboardProductIntelligenceService.php
app/Modules/ProductIntelligence/Controllers/GlobalProductController.php
app/Modules/ProductIntelligence/Controllers/ProductIntelligenceApiController.php
app/Modules/Products/Services/ProductIntelligenceAuditService.php
app/Modules/PurchaseEntries/Exceptions/NfeAccessKeyLookupException.php
app/Modules/PurchaseEntries/Services/NfeAccessKeyImportService.php
app/Modules/PurchaseEntries/Services/NfeXmlImportService.php
app/Modules/PurchaseEntries/Services/PurchaseEntryInsightService.php
database/migrations/2026_07_12_100000_add_intelligence_fields_to_purchase_entry_items_table.php
database/migrations/2026_07_12_213000_add_purchase_entry_trace_to_inventory_movements_table.php
resources/views/products/diagnostics.blade.php
resources/views/products/global-show.blade.php
resources/views/products/global-suggestions.blade.php
resources/views/purchase-entries/replenishment.blade.php
storage/app/nfe-xml-cache/33260656268933000119551000000103091706911509.xml
storage/app/nfe-xml-cache/35260644017677000108550010000276371093174289.xml
storage/app/nfe-xml-cache/35260649166985000119550010008403151399074296.xml
storage/app/nfe-xml-cache/42260527429284000192550010001960691676552742.xml
```

Arquivos staged:

```text
Nenhum.
```

## Migrations

Migrations aplicadas no banco local: todas as migrations presentes na pasta `database/migrations` aparecem como `Ran` no `php artisan migrate:status`.

Migrations pendentes de versionamento:

```text
database/migrations/2026_07_12_100000_add_intelligence_fields_to_purchase_entry_items_table.php
database/migrations/2026_07_12_213000_add_purchase_entry_trace_to_inventory_movements_table.php
```

Risco: o banco local ja executou migrations que o GitHub ainda nao possui. Outro ambiente que clone apenas `origin/main` nao tera essas alteracoes de schema.

## Confirmacao das areas criticas solicitadas

### Sales / PDV

Encontrado e versionado:

```text
app/Modules/Sales/Controllers/SaleController.php
app/Modules/Sales/Services/SaleService.php
app/Modules/Sales/Models/Sale.php
app/Modules/Sales/Models/SaleItem.php
app/Modules/Sales/Models/SalePayment.php
app/Modules/Sales/Models/SaleEvent.php
resources/views/sales/return.blade.php
database/migrations/2026_07_09_173600_create_sales_table.php
database/migrations/2026_07_09_173700_create_sale_items_table.php
database/migrations/2026_07_09_173800_create_sale_payments_table.php
database/migrations/2026_07_11_071000_harden_sales_for_intelligence_snapshots.php
```

Status: os arquivos de Sales citados estao limpos no Git no momento da auditoria. O ultimo commit relevante e `621c304 Fix sales returns and draft deletion`.

### Inventory

Encontrado:

```text
app/Modules/Inventory/Models/InventoryMovement.php
database/migrations/2026_07_09_075500_create_inventory_movements_table.php
database/migrations/2026_07_10_161000_add_lot_and_expiration_to_inventory_movements_table.php
```

Status: `InventoryMovement.php` esta modificado localmente. A migration `2026_07_12_213000_add_purchase_entry_trace_to_inventory_movements_table.php` esta aplicada localmente, mas nao rastreada no Git.

### Product Intelligence / Global Products / GTIN

Encontrado:

```text
app/Modules/ProductIntelligence/Models/GlobalProduct.php
app/Modules/ProductIntelligence/Models/GlobalProductImage.php
app/Modules/ProductIntelligence/Models/GlobalProductRegulatoryData.php
app/Modules/ProductIntelligence/Models/GlobalProductSource.php
app/Modules/ProductIntelligence/Models/GlobalProductSuggestion.php
app/Modules/ProductIntelligence/Services/ProductIntelligenceService.php
app/Modules/ProductIntelligence/Services/GlobalProductCatalogService.php
app/Modules/Products/Contracts/ProductLookupProviderInterface.php
app/Modules/Products/LookupProviders/OpenFoodFactsFamilyProvider.php
app/Modules/Products/LookupProviders/CommercialGtinJsonProvider.php
app/Modules/Products/Services/ProductLookupService.php
app/Modules/Products/Support/ProductLookupImageDownloader.php
database/migrations/2026_07_10_090000_create_product_intelligence_tables.php
```

Status: ha alteracoes locais relevantes em `ProductIntelligenceService.php` e `GlobalProductCatalogService.php`, alem de controllers novos ainda nao rastreados.

### Purchase Entries

Encontrado:

```text
app/Modules/PurchaseEntries/Controllers/PurchaseEntryController.php
app/Modules/PurchaseEntries/Models/PurchaseEntry.php
app/Modules/PurchaseEntries/Models/PurchaseEntryItem.php
app/Modules/PurchaseEntries/Services/PurchaseEntryService.php
app/Modules/PurchaseEntries/Routes/web.php
database/migrations/2026_07_11_021000_create_purchase_entries_tables.php
```

Status: ha muitas alteracoes locais e services novos nao rastreados, incluindo importacao/consulta de NF-e.

## Copias locais encontradas

Foram encontradas duas copias relevantes:

1. `C:\Users\ruanj\Documents\Codex\2026-07-08\ama\work\VetFlow`
   - Possui `.git`.
   - Remoto aponta para `https://github.com/ruanjardim/VetFlow.git`.
   - Deve ser tratada como fonte auditavel neste momento.

2. `C:\Projetos\VetFlow`
   - Possui estrutura Laravel, mas nao possui `.git`.
   - Comparada contra os arquivos rastreados da copia versionada, apresentou:
     - 308 arquivos rastreados comparados.
     - 167 arquivos ausentes em `C:\Projetos\VetFlow`.
     - 56 arquivos diferentes.
   - Nao deve ser usada como fonte de verdade antes de reconciliacao.

## Testes e build

### `php artisan test`

Resultado:

```text
INFO  No tests found.
```

Status: comando terminou com sucesso, mas nao ha testes automatizados detectados. Isso nao valida comportamento do ERP.

### `npm run build`

Primeira tentativa via PowerShell:

```text
npm.ps1 nao pode ser carregado porque a execucao de scripts foi desabilitada neste sistema.
```

Segunda tentativa via `npm.cmd run build`:

```text
> vite build
'vite' nao e reconhecido como um comando interno ou externo, um programa operavel ou um arquivo em lotes.
```

Status: build frontend bloqueada por dependencia frontend ausente/incompleta na copia versionada auditada.

## O que ja existe

- Projeto Laravel 12 com arquitetura modular em `app/Modules`.
- Modulos presentes: Appointments, Clients, ClinicProducts, Clinics, Dashboard, Finance, Financial, Inventory, MedicalRecords, Patients, Pets, PetShopServices, ProductIntelligence, Products, PurchaseEntries, Reports, Sales, Schedules, ServiceOrders, Suppliers, Tutors, Users e Validation.
- Migrations para users, roles, permissions, clinics, tutors, patients, schedules, appointments, financial, products, inventory, pet shop services, service orders, sales, product intelligence, suppliers, purchase entries e cashier closures.
- Implementacoes existentes para Sales/PDV, retornos/devolucoes, Product Intelligence, GTIN lookup, entradas de compra e movimentacao de estoque.

## O que esta incompleto ou inconsistente

- Repositorio nao esta limpo.
- Ha alteracoes locais nao versionadas em areas criticas.
- Duas migrations aplicadas no banco local ainda nao estao no Git.
- Ha XMLs de NF-e em `storage/app/nfe-xml-cache` aparecendo como nao rastreados.
- Nao ha testes automatizados detectados.
- Build frontend nao executa na copia versionada por falta de `vite` disponivel.
- Existe uma copia em `C:\Projetos\VetFlow` sem Git e divergente da copia versionada.

## Riscos encontrados

1. O GitHub nao representa o estado completo do VetFlow local.
2. Um ambiente novo clonado de `origin/main` nao tera as duas migrations locais ja aplicadas.
3. Alteracoes em Product Intelligence, Purchase Entries, Products, Dashboard, Financial e Inventory podem ser perdidas se a pasta local for substituida sem reconciliacao.
4. XMLs de NF-e podem conter dados sensiveis e nao devem ser commitados sem avaliacao.
5. Ausencia de testes automatizados impede regressao confiavel.
6. Build frontend bloqueada impede validacao completa de deploy.
7. A pasta `C:\Projetos\VetFlow` pode induzir trabalho na copia errada.
8. Ha aviso de normalizacao CRLF/LF em `app/Modules/Dashboard/Services/DashboardDataService.php`.

## Arquivos alterados pela Sprint 0

```text
docs/audits/repository-baseline.md
```

Nenhuma alteracao funcional foi feita nesta Sprint.

## Migrations necessarias nesta Sprint

Nenhuma migration nova e necessaria para a Sprint 0.

Pendencia de baseline: decidir se as duas migrations locais nao rastreadas devem ser incorporadas ao Git junto com o restante do trabalho local de Purchase Entries/Product Intelligence.

## Testes necessarios antes da Sprint 1

- Restaurar dependencias frontend na copia versionada e executar `npm run build` com sucesso.
- Criar suite minima de Feature Tests para auth antes de endurecer autorizacao.
- Adicionar testes de regressao para Sales ja existentes, pois Sales esta versionado, mas nao coberto por testes detectaveis.
- Criar testes especificos para migrations e fluxos locais nao versionados antes de consolidar Product Intelligence/Purchase Entries.

## Recomendacao imediata

Antes da Sprint 1, executar uma Sprint 0.1 de reconciliacao curta:

1. Confirmar que a copia fonte sera `C:\Users\ruanj\Documents\Codex\2026-07-08\ama\work\VetFlow`.
2. Separar as alteracoes locais por assunto:
   - Product Intelligence / Global Catalog.
   - Purchase Entries / NF-e.
   - Dashboard e UI.
   - Inventory trace.
   - Financial adjustments.
3. Nao versionar XMLs de `storage/app/nfe-xml-cache`; avaliar regra de `.gitignore` especifica.
4. Instalar/restaurar dependencias frontend e validar build.
5. Criar commits separados para o trabalho local existente antes de iniciar Authentication Hardening.

## Confirmacao de integridade

Integridade do commit remoto: OK.

Integridade do estado local como release candidate: NAO OK.

Motivo: embora `HEAD` e `origin/main` estejam alinhados, o estado local possui alteracoes significativas fora do Git e validacao automatizada/build incompletas.
