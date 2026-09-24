# VetFlow — contexto para sessões do Claude

Regras gerais do repositório (arquitetura, validação, o que não commitar):
@AGENTS.md

Este arquivo guarda o que o AGENTS.md não diz: em que branch trabalhar, como
a produção funciona, o combinado de trabalho com o dono do projeto e onde
paramos. Atualize a seção "Onde estamos" a cada entrega.

## 1. Antes de qualquer coisa: a branch

A branch de trabalho **e** de produção é `0-hostinger-production`. Desde
23/09/2026 ela também é a branch padrão do GitHub, então um clone novo já
cai nela. A `main` parou em 01/08/2026 (#15): não parta dela e não abra PR
para ela.

```bash
git fetch origin
git checkout 0-hostinger-production && git pull --ff-only
git checkout -b feat/<assunto>      # toda branch nova sai daqui
```

- Se `git branch --show-current` não for `0-hostinger-production` (ou uma
  branch criada a partir dela), troque antes de editar qualquer arquivo.
- Todo PR tem base `0-hostinger-production`. Nunca `main`, nunca
  `codex/hostinger-production-candidate`.

## 2. Produção

- Site: https://vetflowsys.com.br, hospedado na Hostinger (hPanel), MySQL.
- **Merge em `0-hostinger-production` publica sozinho.** Só faça merge com os
  testes passando e com o ok do usuário.
- O deploy roda apenas `composer install`. Não roda npm, migrations nem
  `config:cache`; mantém `.env`, `vendor/` e o config em cache. Por isso:
  - **Todo `config()` novo leva valor padrão no ponto de uso**, porque uma
    chave nova em `config/*.php` não entra no cache de produção. Ex.:
    `config('petshop.grooming.slot_minutes', 30)`.
  - **`public/build` é versionado.** Mexeu em `resources/js` ou
    `resources/css`, rode `npm run build` e commite `public/build`
    (manifest e assets) no mesmo PR.
- Migrations e seeders rodam por um cron de hora em hora na Hostinger:
  `cd /home/u804718109/domains/vetflowsys.com.br/public_html && php artisan migrate --force`,
  seguido de `db:seed --class=AuthorizationSeeder --force` e
  `db:seed --class=SaasPlanSeeder --force`.
  - Até o próximo cron (no máximo 1 hora), o código novo rodaria com o banco
    antigo. Por isso, **migration vai antes, num PR só com o schema** (aditivo:
    tabela nova, coluna nullable ou com default). O PR do código só entra
    depois que o cron rodou (espere uma hora cheia depois do merge do schema).
  - As migrations precisam rodar em SQLite (testes), PostgreSQL (job do CI)
    e MySQL (produção).
  - Permissão nova vai em `App\Support\Auth\PermissionCatalog` (o
    AuthorizationSeeder sincroniza). Recurso de plano vai em
    `App\Modules\Saas\Support\FeatureCatalog` (o SaasPlanSeeder sincroniza).
- Pendência do usuário no hPanel: apagar o cron antigo, de minuto em minuto,
  que aponta para o caminho errado (`/home/u804718109/public_html`).
- Runbook completo: `docs/deployment/hostinger-production.md`.

## 3. Como trabalhar (combinado com o usuário)

1. **Olhar:** o código atual e a tela equivalente no SimplesVet, que é a
   referência de produto. O usuário fica logado no SimplesVet no Chrome
   dele; use o Claude in Chrome só para ler, sem criar ou alterar nada lá.
2. **Mostrar o plano** e esperar o ok antes de implementar.
3. **Implementar** numa branch nova a partir de `0-hostinger-production`, com
   testes de feature para as regras novas.
4. **Validar:** `php artisan test` (e `npm run build` se mexeu em JS/CSS).
5. **Abrir o PR** para `0-hostinger-production` e **fazer o merge**: é o
   merge que publica.
6. **Documentar** no mesmo PR: doc do módulo em `docs/modules/`, `STATUS.md`
   e a seção "Onde estamos" abaixo.

## 4. Onde estamos

Última atualização: 24/09/2026.

- **No ar:** PR #23 (formas de pagamento por maquininha e recebíveis de
  cartão, item 3 do plano de Vendas), sobre o schema do PR #22, e PR #21
  (orçamento no PDV e tipo de venda/delivery, itens 1 e 2), sobre o schema do
  PR #20. Regras em `docs/modules/sales.md`.
- **Schema dos itens 4 e 5 já está no ar** (PR #22): `cash_sessions`,
  `cash_session_movements`, `customer_credit_entries`, as colunas
  `cash_session_id` em `sales`/`sale_payments` e a permissão
  `cash-sessions.review`. Os próximos PRs desses itens podem ir direto com o
  código, sem esperar cron.
- **Antes disso:** PR #18 (banho e tosa). Agenda por
  profissional (horários livres, conflito/encaixe, recorrência, check-in),
  quadro, preço por porte, cobrança da comanda no PDV, pacotes (modelos,
  venda pelo PDV, saldo e consumo automático na comanda) e comissão do
  tosador (gerada ao finalizar a comanda; o fechamento vira conta a pagar).
  Regras em `docs/modules/petshop-operations.md`.
- **PR #17** (mesma branch `feat/banho-tosa`, com base
  `codex/hostinger-production-candidate`) ficou aberto e é redundante; pode
  ser fechado.
- **Pendente:** avançar a `main` até a `0-hostinger-production` com um
  fast-forward (`git push origin origin/0-hostinger-production:main`). A
  main é ancestral pura da produção, então não há conflito, e nada publica a
  partir dela.
- **Próximos passos**, na ordem, comparando com o SimplesVet:
  1. Vendas: orçamento no PDV, tipo de venda/delivery, saldo do cliente,
     abertura de caixa por operador, formas de pagamento por maquininha.
  2. Pet: foto e castrado.
  3. Depois, a parte da clínica.
- **Plano de Vendas** (aprovado em 23/09/2026, com as decisões padrão): um PR
  por item, nesta ordem. Itens 1 a 3 entregues; **o próximo é o 4**.
  1. ✅ **Orçamento no PDV:** seletor Venda/Orçamento, código `ORC-`, validade
     (padrão de 7 dias), impressão/WhatsApp, lista de orçamentos e
     "converter em venda", que abre o PDV preenchido. Não mexe em estoque,
     financeiro nem comissão. Pacote não entra em orçamento.
  2. ✅ **Tipo de venda:** as 6 opções do SimplesVet (padrão: presencial para
     consumidor final); nos tipos de delivery, endereço e taxa de entrega.
  3. ✅ **Formas de pagamento por maquininha:** cadastro por clínica (tipo,
     maquininha, taxa à vista e parcelada, prazo, parcelas, NSU), iniciado
     com as 6 formas atuais. O pagamento guarda taxa, líquido e data
     prevista do repasse; relatório "Recebíveis de cartão" por parcela.
     **Fica para o item 4:** lançar as taxas como uma despesa por maquininha
     no fechamento do caixa (hoje o fechamento ainda concilia por tipo).
  4. **Caixa por operador:** abertura com fundo de troco, suprimento,
     sangria, despesa, fechamento pelo operador (conferência por forma) e
     encerramento pelo gestor (permissão nova). Receber exige caixa aberto.
  5. **Saldo do cliente:** venda "paga depois" (só com cliente), crédito
     como forma de pagamento, troco como crédito, adiantamento (vira
     receita quando usado), quitação de várias vendas e devolução em
     crédito. O saldo aparece no PDV, na ficha do tutor e numa lista.
  - Decisões já tomadas: delivery com endereço e taxa (sem status de
    entrega); caixa aberto obrigatório para receber, com abertura no próprio
    PDV; taxas de maquininha como uma despesa por maquininha no fechamento;
    adiantamento vira receita só quando usado; orçamento vencido ainda pode
    virar venda, com aviso, pelos preços orçados.

## 5. Mapa rápido do código

- Laravel 12, PHP 8.2+ (a produção roda PHP 8.3). Módulos em
  `app/Modules/<Modulo>/{Controllers,Services,Repositories,Models,Requests,Routes}`;
  regra de negócio em Services, controllers finos.
- As rotas de cada módulo entram pelo mapa `$moduleRoutes` em
  `routes/web.php` (permissão → arquivo de rotas). O grupo recebe
  `EnsureUserHasPermission` e, se o módulo tiver recurso de plano,
  `EnsureTenantHasFeature`.
- Multi-clínica: registro operacional tem `clinic_id` e toda consulta filtra
  pela clínica ativa. Referência de isolamento: `ClinicTenantIsolationTest`.
- Interface em pt-BR; código e docs de módulo em inglês. Blade em
  `resources/views/<área>`, com o menu lateral em `layouts/admin.blade.php`.
- Front sem framework e sem Tailwind: `resources/js/app.js` (importa
  `pdv.js` e `service-orders.js`) e `resources/css/app.css` (importa
  `pdv.css`).
- Testes em `tests/Feature`, com SQLite em memória (`phpunit.xml`). A suíte
  inteira (cerca de 300 testes) roda em menos de 1 minuto; `--parallel` não
  funciona (falta o paratest). O CI (`.github/workflows/ci.yml`) roda testes,
  build e migrations em PostgreSQL nos PRs.
- Preparar um clone novo para testar:
  `composer install && cp .env.example .env && php artisan key:generate`
  (o `phpunit.xml` não define `APP_KEY`).

## 6. Sessões na nuvem: armadilhas conhecidas

- Push e API do GitHub só funcionam se o repositório estiver entre as fontes
  da sessão, com escrita. Sem isso, o proxy recusa com "not in this
  session's authorized repository set". Peça ao usuário logo no início.
- `gh` não vem instalado.
- Se `registry.npmjs.org` não estiver liberado na rede da sessão, `npm ci`
  falha com 403 e não há como gerar `public/build`. Peça a liberação antes de
  mexer em JS/CSS.
- O `composer install` não consegue baixar os pacotes zipados do GitHub e
  cai para git clone. Funciona, só demora alguns minutos.
- Sem npm, dá para gerar o `public/build` com o esbuild que vem no `tsx`
  global (`~/.npm-global/lib/node_modules/tsx/node_modules/@esbuild/linux-x64/bin/esbuild`):
  `--bundle --minify --target=es2020` (e `--format=esm` no JS) para
  `resources/js/app.js` e `resources/css/app.css`, com nome
  `assets/app-<hash>.<ext>` e o `public/build/manifest.json` atualizado no
  mesmo formato. Não reconstrua o `landing.css` se a fonte dele não mudou. Os
  PRs #21 e #23 saíram assim; a próxima sessão com npm pode rodar
  `npm run build`.
- Sem push na sessão, os PRs saíram pelo GitHub web no Chrome do usuário:
  arquivos numa branch nova, PR e merge. O classificador de segurança do
  Claude bloqueia commit direto na `0-hostinger-production`: use sempre
  branch + PR.
