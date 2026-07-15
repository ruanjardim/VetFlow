# Permissions

Tabela: `permissions`

## Objetivo

Armazena as permissoes funcionais usadas para controlar acesso aos modulos do VetFlow.

## Campos principais

- `name`: nome exibivel da permissao.
- `slug`: identificador tecnico usado em middleware, Gates e views.
- `description`: descricao operacional da permissao.
- `group`: agrupamento para futuras telas administrativas.
- `active`: quando falso, a permissao nao autoriza acesso.
- `deleted_at`: permite desativacao logica sem perda historica.

## Catalogo atual

O catalogo central fica em `App\Support\Auth\PermissionCatalog`.

```text
dashboard.view
clinics.manage
tutors.manage
patients.manage
schedules.manage
appointments.manage
petshop-services.manage
service-orders.manage
sales.manage
products.manage
global-products.manage
inventory.manage
purchase-entries.manage
suppliers.manage
financial.manage
```

## Uso no sistema

- Rotas internas usam `App\Http\Middleware\EnsureUserHasPermission`.
- Views usam Gates do Laravel com `@can`.
- O menu lateral mostra somente os modulos autorizados para o usuario logado.
- Permissoes inativas nao autorizam acesso, mesmo quando ainda estao vinculadas a um perfil.
