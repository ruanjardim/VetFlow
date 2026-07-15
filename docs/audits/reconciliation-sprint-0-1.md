# Sprint 0.1 - Reconciliacao do estado local

Data: 2026-07-15
Projeto: VetFlow 1.0 - Commercial Edition
Pasta auditada: `C:\Users\ruanj\Documents\Codex\2026-07-08\ama\work\VetFlow`

## Status

CONCLUIDA.

A Sprint 0.1 reconciliou as alteracoes locais encontradas na Sprint 0, separou cache/sensiveis do codigo versionavel, restaurou a build frontend e deixou a arvore de trabalho limpa.

## Fonte de verdade

A fonte principal continua sendo a copia versionada:

```text
C:\Users\ruanj\Documents\Codex\2026-07-08\ama\work\VetFlow
```

A copia `C:\Projetos\VetFlow` permanece fora da baseline porque nao possui `.git` e diverge da copia versionada.

## Implementado

- Restaurado `package-lock.json` a partir de `npm install`.
- Confirmado `vite` e build frontend funcional.
- Adicionada regra em `.gitignore` para cache local de XML de NF-e:

```text
/storage/app/nfe-xml-cache/
```

- Removido arquivo local nao rastreado `app/Console/Commands/BackfillGlobalProductsCommand.php`, pois duplicava o comando `vetflow:global-products:backfill` ja ativo em `routes/console.php` e nao estava registrado pela configuracao atual do Laravel.
- Versionadas as migrations locais que ja estavam aplicadas no banco:

```text
database/migrations/2026_07_12_100000_add_intelligence_fields_to_purchase_entry_items_table.php
database/migrations/2026_07_12_213000_add_purchase_entry_trace_to_inventory_movements_table.php
```

- Versionado bloco funcional local de Product Intelligence / Global Catalog:
  - controllers para catalogo global e API de intelligence;
  - servicos de enriquecimento, auditoria e metricas;
  - views de catalogo global, detalhes, sugestoes e diagnostico;
  - indicadores no dashboard;
  - comandos Artisan de diagnostico e backfill;
  - rotas web/API relacionadas.
- Versionado bloco funcional local de Purchase Entries / NF-e:
  - importacao por XML;
  - tentativa de importacao por chave de acesso;
  - cache interno de XML fora do Git;
  - insights de compra e reposicao;
  - preview de impactos em estoque, lote e financeiro;
  - rastreio de entrada de compra em `inventory_movements`;
  - filtros financeiros por compra, fornecedor, status e tipo.

## Arquivos ignorados

Os XMLs locais de NF-e permanecem na maquina, mas nao entram no Git:

```text
storage/app/nfe-xml-cache/*.xml
```

Motivo: podem conter dados fiscais/sensiveis de fornecedores, notas e operacao.

## Commits criados

```text
de112d8 Reconcilia dependencias frontend e cache fiscal
2978fec Reconcilia inteligencia de produtos e entradas de compra
```

Este documento deve ser commitado separadamente como registro da Sprint 0.1.

## Validacoes executadas

### Sintaxe PHP

Resultado: OK.

Foram verificados os arquivos PHP alterados e novos com `php -l`.

### Rotas Laravel

Comando:

```text
php artisan route:list
```

Resultado: OK.

Foram listadas 119 rotas, incluindo:

- dashboard;
- products;
- global-products;
- product-intelligence API;
- purchase-entries;
- importacao NF-e;
- sales;
- inventory;
- financial.

### Migrations

Comando:

```text
php artisan migrate:status
```

Resultado: OK.

Todas as migrations presentes aparecem como `Ran` no banco local, incluindo as duas que estavam pendentes de versionamento.

### Testes automatizados

Comando:

```text
php artisan test
```

Resultado:

```text
INFO  No tests found.
```

O comando executa, mas a suite de testes ainda nao existe. Isto continua sendo risco estrutural para as proximas sprints.

### Build frontend

Comando:

```text
npm.cmd run build
```

Resultado: OK.

Artefatos gerados localmente em `public/build`, que continua ignorado pelo Git.

### Smoke test HTTP local

Servidor local usado:

```text
http://127.0.0.1:8000
```

Paginas verificadas:

```text
GET / => 200
GET /products => 200
GET /global-products => 200
GET /purchase-entries => 200
```

## Estado final

Arvore de trabalho: limpa.

Status esperado:

```text
## main...origin/main [ahead 2]
```

Depois do commit deste documento, o esperado sera `ahead 3` ate envio ao GitHub.

## Riscos restantes

1. Ainda nao ha testes automatizados reais.
2. A aplicacao continua sem autenticacao/authorization hardening, que sera Sprint 1 e Sprint 2.
3. As novas rotas funcionais continuam publicas enquanto a camada de auth nao for implementada.
4. O fluxo de NF-e por chave depende de configuracao externa opcional via `NFE_KEY_LOOKUP_URL` e `NFE_KEY_LOOKUP_TOKEN`.
5. A copia `C:\Projetos\VetFlow` ainda nao deve ser usada como fonte de desenvolvimento.

## Proximo passo

Sprint 1 - Authentication Hardening.

Antes de iniciar, confirmar que os commits da Sprint 0.1 foram enviados ao GitHub.
