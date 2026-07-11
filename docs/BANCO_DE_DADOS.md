# VetFlow ERP — Banco de Dados

## 1. Objetivo

Este documento define a estrutura inicial do banco de dados do VetFlow ERP.

Antes de criar migrations, models ou telas, as tabelas principais serão planejadas aqui para reduzir retrabalho e manter o sistema escalável.

---

## 2. Padrão Geral

Todas as tabelas principais deverão seguir, sempre que fizer sentido:

- id
- clinic_id
- created_at
- updated_at
- deleted_at

O campo `clinic_id` será usado para separar os dados de cada clínica.

---

## 3. Tabelas Base

As primeiras tabelas do sistema serão:

- clinics
- users
- roles
- permissions
- role_user
- permission_role
- employees

---

## 4. Próxima Etapa

Detalhar cada tabela com:

- campos
- tipos
- relacionamentos
- índices
- regras de exclusão

---

# 5. Núcleo do Sistema

## 5.1 Tabela: clinics

Tabela responsável por armazenar as clínicas, unidades ou redes que usarão o VetFlow ERP.

### Campos

| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| id | bigint unsigned | Sim | Chave primária |
| parent_clinic_id | bigint unsigned nullable | Não | Clínica matriz, quando for filial |
| corporate_name | string | Sim | Razão social |
| trade_name | string | Sim | Nome fantasia |
| document | string | Sim | CPF ou CNPJ |
| state_registration | string nullable | Não | Inscrição estadual |
| municipal_registration | string nullable | Não | Inscrição municipal |
| email | string nullable | Não | E-mail principal |
| phone | string nullable | Não | Telefone |
| whatsapp | string nullable | Não | WhatsApp |
| zip_code | string nullable | Não | CEP |
| state | string nullable | Não | Estado |
| city | string nullable | Não | Cidade |
| district | string nullable | Não | Bairro |
| street | string nullable | Não | Rua |
| number | string nullable | Não | Número |
| complement | string nullable | Não | Complemento |
| logo | string nullable | Não | Caminho do logotipo |
| timezone | string | Sim | Fuso horário padrão |
| currency | string | Sim | Moeda padrão |
| active | boolean | Sim | Define se a clínica está ativa |
| created_at | timestamp | Sim | Criado automaticamente pelo Laravel |
| updated_at | timestamp | Sim | Atualizado automaticamente pelo Laravel |
| deleted_at | timestamp nullable | Não | Exclusão lógica |

### Relacionamentos

- Uma clínica pode ter várias filiais.
- Uma clínica filial pode pertencer a uma clínica matriz.
- Uma clínica terá vários usuários.
- Uma clínica terá vários funcionários.
- Uma clínica terá vários clientes/tutores.
- Uma clínica terá vários pets.
- Uma clínica terá vários produtos.
- Uma clínica terá várias vendas.
- Uma clínica terá vários registros financeiros.

### Índices

- `document` deve ser único.
- `parent_clinic_id` deve ser indexado.
- `active` deve ser indexado.
- `deleted_at` será usado pelo Soft Delete.

### Regras

- Nenhuma clínica deve ser apagada fisicamente do banco.
- Ao excluir uma clínica, usar Soft Delete.
- Clínicas inativas não podem acessar o sistema.
- Uma filial deve conseguir herdar configurações da matriz no futuro.
- Toda tabela operacional deverá ter `clinic_id`.