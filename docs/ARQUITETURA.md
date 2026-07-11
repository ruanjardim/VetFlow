# VetFlow ERP — Arquitetura do Sistema

## 1. Visão Geral

O VetFlow ERP será um sistema de gestão para clínicas veterinárias, pet shops e operações integradas com atendimento, loja, estoque e financeiro.

O sistema será construído desde o início como multi-clínica, permitindo que uma mesma plataforma atenda várias clínicas, mantendo dados separados por unidade.

---

## 2. Módulos do Sistema

- Dashboard
- Agenda
- Recepção
- Clientes / Tutores
- Pets
- Prontuários
- Vacinas
- Exames
- Cirurgias
- Internações
- Farmácia
- Loja / PDV
- Estoque
- Compras
- Financeiro
- Funcionários
- Usuários
- Permissões
- Relatórios
- Configurações

---

## 3. Conceito Multi-Clínica

Toda informação principal do sistema deverá pertencer a uma clínica.

Exemplo:

- um cliente pertence a uma clínica
- um pet pertence a uma clínica
- um produto pertence a uma clínica
- uma venda pertence a uma clínica
- um usuário acessa uma ou mais clínicas

A tabela principal será:

```text
clinics