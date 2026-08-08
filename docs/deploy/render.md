# Deploy de demonstração no Render

Este guia prepara um ambiente descartável de demonstração/testes do VetFlow.
Use exclusivamente dados fictícios. Não envie dados de clínicas, documentos
reais, credenciais ou chaves de NF-e para esse ambiente.

## Escopo e pré-requisitos

- Repositório: `https://github.com/ruanjardim/VetFlow`.
- Branch obrigatória: `deploy/render`; não faça merge direto em `main`.
- Conta no Render conectada ao GitHub e acesso ao domínio no Registro.br.
- Um banco Render Postgres e o Web Service devem ficar na mesma região. O
  `render.yaml` usa `virginia`; mantenha essa região ao criar o banco.
- O Blueprint cria somente o Web Service. Crie o banco manualmente para
  revisar as credenciais antes de qualquer conexão.

## 1. Criar o PostgreSQL

1. No Render, clique em **New** > **Postgres**.
2. Use `vetflow-demo-db` como nome, **Virginia** como região e **Free** como
   instância.
3. Clique em **Create Database** e espere o estado **Available**.
4. Abra o banco, clique em **Connect** e use os valores da conexão **Internal**:
   `Host`, `Port` (normalmente `5432`), `Database`, `User` e `Password`.
5. Não use a URL externa no Web Service. A conexão interna reduz latência e
   evita trânsito público de credenciais.

## 2. Criar o Web Service

1. No Render, clique em **New** > **Blueprint**.
2. Selecione `ruanjardim/VetFlow`, escolha a branch `deploy/render` e o arquivo
   `render.yaml` na raiz.
3. Confirme o serviço `vetflow-demo`, runtime **Docker**, plano **Free**, região
   **Virginia**, health check `/up` e auto deploy desligado.
4. Para cada chave marcada como secreta, informe os valores abaixo. Não cole
   segredos em commits, comentários de PR, logs ou capturas de tela.

| Chave | Valor no painel do Render |
| --- | --- |
| `APP_KEY` | Saída de `php artisan key:generate --show`, incluindo o prefixo `base64:` |
| `APP_URL` | Defina depois do primeiro deploy para `https://<subdomínio>.onrender.com` |
| `DB_HOST` | Host da conexão **Internal** do Postgres |
| `DB_PORT` | Porta da conexão **Internal** (`5432`, salvo indicação diferente) |
| `DB_DATABASE` | Nome do banco da conexão **Internal** |
| `DB_USERNAME` | Usuário da conexão **Internal** |
| `DB_PASSWORD` | Senha da conexão **Internal** |

Os valores públicos vêm do `render.yaml`: `APP_ENV=production`,
`APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `LOG_LEVEL=info`,
`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
`FILESYSTEM_DISK=local`, `VETFLOW_SEED_DEMO_USER=false`,
`DB_CONNECTION=pgsql`, `DB_SSLMODE=prefer`, `SESSION_SECURE_COOKIE=true`,
`SESSION_SAME_SITE=lax` e `TRUSTED_PROXIES=*`.

Gere a chave localmente em uma cópia confiável, nunca no repositório:

```bash
php artisan key:generate --show
```

Clique em **Apply** no Blueprint e, quando o serviço for exibido, em
**Manual Deploy** > **Deploy latest commit**. O primeiro endereço
`onrender.com` aparece no topo da página do serviço.

## O que o deploy executa

O `Dockerfile` usa build multiestágio: Node 22 gera os assets do Vite e PHP
8.3 instala apenas as dependências Composer de produção. A imagem final não
contém `node_modules`, `vendor` local, `.env`, testes ou documentos.

Antes de iniciar Nginx e PHP-FPM, `docker/start.sh` valida `APP_KEY` e a
conexão PostgreSQL configurada, executa `php artisan migrate --force` antes de
limpar caches, descobre pacotes, cria o link de storage e recompila os caches de
configuração, rotas e views. Ele nunca executa `migrate:fresh`, `db:wipe`,
`migrate:reset`, seeders genéricos nem seeders de demonstração.

`AuthorizationSeeder` não roda no boot. Embora seja idempotente para papéis e
permissões, ele atribui o perfil `administrador` a usuários ativos que ainda
não tenham perfil. Execute-o conscientemente, uma única vez e somente se a
base precisar dos perfis padrão:

```bash
php artisan db:seed --class=AuthorizationSeeder --force
```

Não há worker ou cron no primeiro Web Service. O código atual não despacha
jobs; se isso mudar, crie um Worker/cron separado antes de depender de fila.

## Verificação após o deploy

1. Em **Logs**, confirme as mensagens de migration concluída e inicialização
   de Nginx/PHP-FPM. Erros de variável ausente interrompem o boot de propósito.
2. Abra `https://<subdomínio>.onrender.com/up`; o endpoint não exige login e
   deve responder `200`.
3. Abra a tela de login. Não existe usuário de demonstração automático: crie o
   primeiro administrador manualmente, no Shell do Render ou em um ambiente
   controlado, com `php artisan vetflow:admin:create`.
4. Teste com uma clínica e dados fictícios: login, isolamento por clínica,
   importação CSV/XLSX, leitura de NF-e XML sem salvar documento real e um
   upload descartável.
5. Rode, no Shell do Render após confirmar backup restaurável em ambiente pago,
   `php artisan vetflow:release:check --backup-confirmed`.

### Bootstrap inicial sem Shell

Instâncias Free não oferecem Shell. Para criar somente o primeiro administrador
em uma demonstração, defina temporariamente estas variáveis secretas no Render
e faça um deploy manual:

```text
VETFLOW_BOOTSTRAP_ADMIN=true
VETFLOW_BOOTSTRAP_ADMIN_NAME=Administrador VetFlow
VETFLOW_BOOTSTRAP_ADMIN_EMAIL=admin@example.com
VETFLOW_BOOTSTRAP_ADMIN_PASSWORD=<senha-com-pelo-menos-10-caracteres>
```

O script de inicialização executa `vetflow:admin:create`, que cria ou atualiza
o usuário e o perfil administrador. Depois de confirmar o login, remova as
quatro variáveis imediatamente. Nunca use uma senha conhecida ou dados reais
nesse ambiente de demonstração.

## Storage e limites do plano Free

O disco local de Web Services do Render é efêmero: uploads, imagens de produto,
XML de NF-e e arquivos temporários podem desaparecer em restart, redeploy ou
quando o serviço gratuito adormecer. Não os trate como armazenamento
permanente, não use documentos reais e não configure SQLite nesse ambiente.

O serviço gratuito entra em suspensão após 15 minutos sem tráfego e o próximo
acesso pode levar cerca de um minuto. O Postgres Free tem 1 GB, expira 30 dias
após a criação, não possui backups gerenciados e, após 14 dias de carência,
pode ser apagado. Exporte dados de teste antes disso. O plano Free suporta um
único Postgres Free ativo por workspace.

## Domínio `demo.vetflowsys.com.br`

Faça esta etapa somente depois que o endereço `onrender.com` e `/up` estiverem
funcionando.

1. No Web Service, abra **Settings** > **Custom Domains** > **Add Custom
   Domain**, informe `demo.vetflowsys.com.br` e salve.
2. Copie o hostname `onrender.com` que o Render mostrar para esse serviço. Não
   invente nem reutilize um destino de outro serviço.
3. No Registro.br, abra o editor DNS de `vetflowsys.com.br` e crie um registro
   **CNAME** com nome/host `demo` apontando para esse hostname `onrender.com`.
   Remova qualquer registro **AAAA** para `demo` que conflite.
4. Aguarde a propagação, volte ao Render e clique em **Verify** ao lado do
   domínio. O Render emite e renova HTTPS automaticamente.
5. Em **Environment**, altere `APP_URL` para
   `https://demo.vetflowsys.com.br`; faça **Manual Deploy** > **Deploy latest
   commit** para o script limpar e reconstruir os caches.
6. Teste `/up`, login, sessão e isolamento por clínica a partir de uma rede
   externa (por exemplo, dados móveis), sem usar dados reais.

## Rollback e remoção

Para reverter código, em **Manual Deploy** selecione um deploy anterior; o
Render Free mantém apenas os dois últimos deploys anteriores. Se uma migration
incompatível já tiver rodado, restaure o banco somente a partir de um backup
validado — nunca execute comandos destrutivos para "voltar". Para encerrar a
demonstração, remova primeiro o Custom Domain no Render, depois o CNAME `demo`
no Registro.br e, por fim, delete o Web Service e o Postgres no Render.

## Checklist de segurança

- `APP_DEBUG=false`, `APP_KEY` e credenciais somente no painel do Render.
- `VETFLOW_SEED_DEMO_USER=false`; nenhum seeder ou usuário fictício automático.
- Sem dados, NF-e XML, uploads ou backups reais no serviço Free.
- Web Service e banco na mesma região, usando conexão interna.
- Health check `/up` verde, logs em stderr e domínio HTTPS validado.
- Verifique `git diff --check`, arquivos rastreados e histórico antes de push.
