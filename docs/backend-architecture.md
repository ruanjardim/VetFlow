# VetFlow 1.0 — Backend Architecture

## Objetivo

Este documento define a arquitetura oficial do Back-end do VetFlow.

O Back-end deve ser modular, previsível, reutilizável e preparado para crescimento.

---

## Stack Principal

O VetFlow utiliza:

* Laravel 12
* PHP 8+
* MySQL
* Eloquent ORM
* Migrations
* Seeders
* Services
* Repositories
* Contracts
* Blade
* Vite

---

## Estrutura Geral

```text
app/
├── Core/
└── Modules/
```

---

## Core

O `Core` contém infraestrutura compartilhada.

Exemplos:

```text
app/Core/
├── Base/
├── Contracts/
├── Exceptions/
├── Helpers/
├── Providers/
└── Support/
```

O `Core` nunca deve conhecer regras específicas do ERP.

---

## Modules

Toda regra de negócio pertence aos módulos.

Exemplos:

```text
app/Modules/
├── Dashboard/
├── Clinics/
├── Tutors/
├── Patients/
├── Appointments/
├── MedicalRecords/
├── Financial/
└── Inventory/
```

Cada módulo deve concentrar sua própria regra de negócio.

---

## Estrutura Recomendada de um Módulo

```text
app/Modules/NomeDoModulo/
├── Contracts/
├── Controllers/
├── Models/
├── Repositories/
├── Requests/
├── Services/
├── Policies/
├── Events/
├── Jobs/
└── Providers/
```

Nem todo módulo precisa de todas as pastas.

Criamos apenas o que for necessário.

---

## Controllers

Controllers recebem a requisição e coordenam a resposta.

Responsabilidades:

* receber request;
* chamar services;
* retornar view, redirect ou response.

Controllers não devem conter regra de negócio complexa.

---

## Services

Services concentram regras de negócio.

Responsabilidades:

* orquestrar operações;
* validar fluxos de negócio;
* chamar repositories;
* preparar dados para controllers;
* integrar módulos quando necessário.

---

## Repositories

Repositories concentram acesso a dados.

Responsabilidades:

* consultas;
* criação;
* atualização;
* exclusão;
* filtros;
* paginação.

Controllers não devem acessar Models diretamente quando existir Repository.

---

## Contracts

Contracts definem interfaces.

Usamos Contracts para reduzir acoplamento entre implementação e consumo.

Exemplo:

```php
interface TutorRepositoryInterface
{
    public function paginate(int $perPage = 15);
}
```

---

## Models

Models representam entidades do banco.

Responsabilidades:

* tabela;
* fillable ou guarded;
* casts;
* relationships;
* scopes simples.

Models não devem virar classes gigantes de regra de negócio.

---

## Requests

Requests devem ser usados para validação de entrada.

Responsabilidades:

* regras de validação;
* mensagens;
* autorização simples.

Controllers devem evitar validações longas diretamente no método.

---

## Migrations

Migrations definem a estrutura do banco.

Regras:

* nomes claros;
* campos explícitos;
* índices quando necessário;
* foreign keys quando fizer sentido;
* soft deletes em entidades importantes.

---

## Policies

Policies controlam autorização.

Devem ser usadas quando uma ação depende do usuário, clínica, perfil ou permissão.

---

## Events

Events devem ser usados para comunicar acontecimentos importantes.

Exemplos:

* tutor criado;
* paciente cadastrado;
* consulta finalizada;
* pagamento confirmado;
* vacina vencendo.

---

## Jobs

Jobs devem ser usados para tarefas assíncronas.

Exemplos:

* envio de e-mails;
* geração de PDFs;
* notificações;
* integrações externas;
* processamento pesado.

---

## Providers

Providers registram dependências do módulo.

Exemplo:

```php
$this->app->bind(
    TutorRepositoryInterface::class,
    TutorRepository::class
);
```

---

## Dependency Injection

O VetFlow deve usar injeção de dependência sempre que possível.

Evitar instanciar classes manualmente dentro da regra de negócio.

---

## Fluxo de uma Requisição

```text
Route
  ↓
Controller
  ↓
Request
  ↓
Service
  ↓
Repository
  ↓
Model
  ↓
Database
```

Resposta:

```text
Database
  ↓
Model
  ↓
Repository
  ↓
Service
  ↓
Controller
  ↓
View / Redirect / Response
```

---

## Comunicação entre Módulos

Um módulo não deve acessar diretamente detalhes internos de outro.

Preferir:

* Services públicos;
* Contracts;
* Events;
* DTOs quando necessário.

Evitar:

* queries diretas em tabelas de outro módulo;
* dependência forte entre controllers;
* duplicação de regra.

---

## Convenções de Nomenclatura

Controllers:

```text
TutorController
PatientController
ClinicController
```

Services:

```text
TutorService
PatientService
ClinicService
```

Repositories:

```text
TutorRepository
PatientRepository
ClinicRepository
```

Contracts:

```text
TutorRepositoryInterface
PatientRepositoryInterface
ClinicRepositoryInterface
```

Models:

```text
Tutor
Patient
Clinic
```

---

## Regras de Criação de Novos Módulos

Ao criar um novo módulo:

1. Definir responsabilidade do módulo.
2. Criar Model e Migration.
3. Criar Repository e Contract, se houver acesso relevante a dados.
4. Criar Service para regra de negócio.
5. Criar Controller apenas para entrada e saída.
6. Criar Requests para validação.
7. Criar Views usando UI Kit.
8. Registrar dependências no Provider quando necessário.
9. Validar rotas.
10. Atualizar documentação.

---

## Regras Proibidas

Evitar:

* regra de negócio em Controller;
* regra de negócio em Blade;
* SQL espalhado pelo sistema;
* Models com muitas responsabilidades;
* módulos altamente acoplados;
* duplicação de lógica;
* validações extensas dentro do Controller.

---

## Validação Técnica

Antes de concluir alterações no Back-end:

```bash
php artisan optimize:clear
php artisan route:list
```

Quando necessário:

```bash
php artisan migrate
php artisan tinker
```

Validar também:

* rotas;
* services;
* repositories;
* models;
* relacionamentos;
* views;
* banco de dados;
* erros no log.

---

## Direção Arquitetural

O Back-end do VetFlow deve evoluir para suportar:

* multi-clínicas;
* permissões por perfil;
* auditoria;
* logs de ações;
* API pública;
* integrações externas;
* filas;
* notificações;
* geração de documentos;
* inteligência operacional.

A arquitetura atual já deve ser preservada pensando nessa evolução.

---

## Conclusão

O Back-end do VetFlow deve ser organizado por módulos, com responsabilidades claras e baixo acoplamento.

Este documento deve orientar todos os novos módulos e todas as futuras refatorações do Back-end.
