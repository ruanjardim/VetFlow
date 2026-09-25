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
- Migrations e seeders rodam por um cron de hora em hora na Hostinger (hPanel >
  Avançado > Cron Jobs, modo "Personalizado", minuto 0). É o único cron da
  conta:
  `/usr/bin/php /home/u804718109/domains/vetflowsys.com.br/public_html/artisan migrate --force --seed`.
  - O `--seed` roda o `DatabaseSeeder`, que em produção só chama o
    `AuthorizationSeeder` e o `SaasPlanSeeder` (idempotentes; o usuário de
    demonstração só existe em local/testing). Não ponha dados de exemplo no
    `DatabaseSeeder`: o cron roda ele toda hora.
  - **No cron do hPanel, um comando só e com caminho absoluto.** O hPanel
    escapa caracteres especiais como `&`: um comando `cd ... && php artisan ...`
    é aceito, mas não roda nada e não mostra erro. Foi assim que as migrations
    ficaram paradas até 24/09/2026 e o PDV, o caixa, os pacotes e as comissões
    deram erro 500. O limite é de 255 caracteres, contados depois do escape.
  - Até o próximo cron (no máximo 1 hora), o código novo rodaria com o banco
    antigo. Por isso, **migration vai antes, num PR só com o schema** (aditivo:
    tabela nova, coluna nullable ou com default). O PR do código só entra
    depois que o cron rodou: espere a hora cheia depois do merge do schema e
    **confira** em "Ver resultado", no cron do hPanel, ou no check
    "Migrations" de `/operations/report.json` ("Nenhuma migration pendente").
  - As migrations precisam rodar em SQLite (testes), PostgreSQL (job do CI)
    e MySQL (produção). O MySQL exige que a coluna do `->after()` exista e
    limita nomes de índice e de chave estrangeira a 64 caracteres; SQLite e
    PostgreSQL não reclamam disso.
  - Permissão nova vai em `App\Support\Auth\PermissionCatalog` (o
    AuthorizationSeeder sincroniza). Recurso de plano vai em
    `App\Modules\Saas\Support\FeatureCatalog` (o SaasPlanSeeder sincroniza).
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

- **No ar — plano de Vendas completo:** PR #25 (saldo do cliente, item 5),
  PR #24 (caixa por operador, item 4) e PR #23 (formas de pagamento por
  maquininha e recebíveis de cartão, item 3), todos sobre o schema do PR #22,
  e PR #21 (orçamento no PDV e tipo de venda/delivery, itens 1 e 2), sobre o
  schema do PR #20. Regras em `docs/modules/sales.md`.
- **24/09/2026 — produção corrigida:** o cron antigo (`cd ... && ...`) nunca
  aplicou nada, então as migrations de 08/09 a 23/09 estavam pendentes e o
  PDV, o caixa, os pacotes e as comissões davam erro 500. Elas rodaram por
  volta das 06:50 (horário de Brasília) pelo cron novo da seção 2, que substituiu o
  antigo; o relatório de operações ficou sem migration pendente e os 54 links
  do menu respondem 200. O cron de minuto em minuto no caminho errado também
  foi apagado.
- **Testes que recebem dinheiro** precisam de caixa aberto: use o trait
  `Tests\Concerns\OpensCashSessions` (`$this->openCashSession($user)`).
- **Antes disso:** PR #18 (banho e tosa). Agenda por
  profissional (horários livres, conflito/encaixe, recorrência, check-in),
  quadro, preço por porte, cobrança da comanda no PDV, pacotes (modelos,
  venda pelo PDV, saldo e consumo automático na comanda) e comissão do
  tosador (gerada ao finalizar a comanda; o fechamento vira conta a pagar).
  Regras em `docs/modules/petshop-operations.md`.
- **PR #17** (mesma branch `feat/banho-tosa`, com base
  `codex/hostinger-production-candidate`) era redundante e foi fechado.
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
  por item, nesta ordem. **Todos entregues** (23-24/09/2026). O próximo passo
  é o item 2 da lista acima: **Pet (foto e castrado)**.
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
  4. ✅ **Caixa por operador:** abertura com fundo de troco (no PDV),
     suprimento, sangria, despesa, fechamento pelo operador (conferência por
     forma, taxas viram uma despesa por maquininha) e encerramento/reabertura
     pelo gestor. Receber, estornar e cancelar venda paga exigem caixa
     aberto (o estorno sai do caixa de quem cancela). Caixa esquecido aberto
     fecha sozinho às 23:59 (como no SimplesVet) e fica para conferência.
  5. ✅ **Saldo do cliente:** venda "paga depois" (só com cliente), crédito
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
  PRs #21, #23, #24 e #25 saíram assim; a próxima sessão com npm pode rodar
  `npm run build`.
- Sem push na sessão, os PRs saíram pelo GitHub web no Chrome do usuário:
  arquivos numa branch nova, PR e merge. O classificador de segurança do
  Claude bloqueia commit direto na `0-hostinger-production`: use sempre
  branch + PR.
- No hPanel, a tradução automática do Chrome pode deixar a lista de crons e
  os campos de horário desatualizados na tela depois de salvar ou remover.
  Recarregue a página antes de clicar em "Remover" e confira o que ficou.
