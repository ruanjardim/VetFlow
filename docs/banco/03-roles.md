# Roles

Tabela: `roles`

## Objetivo

Armazena os perfis de acesso do VetFlow. Um usuario pode ter um ou mais perfis por meio da tabela `user_roles`.

## Campos principais

- `clinic_id`: permite perfis especificos por clinica. Perfis globais do sistema usam `null`.
- `name`: nome exibivel do perfil.
- `slug`: identificador tecnico usado pelo sistema.
- `description`: descricao operacional do perfil.
- `system`: indica perfil padrao do VetFlow.
- `active`: quando falso, o perfil nao autoriza acesso.
- `deleted_at`: permite desativacao logica sem perda historica.

## Perfis padrao

Os perfis padrao sao criados por `Database\Seeders\AuthorizationSeeder`.

```text
administrador
veterinario
atendimento
estoque-compras
caixa
financeiro
```

## Regras atuais

- `administrador` recebe todas as permissoes do catalogo.
- Somente `administrador` recebe `users.manage`.
- `veterinario`, `atendimento`, `estoque-compras`, `caixa` e `financeiro`
  delimitam os principais fluxos operacionais sem expor configuracao livre de
  permissoes.
- Usuarios ativos sem nenhum perfil recebem `administrador` ao executar o seeder, evitando bloqueio acidental em bases ja existentes.
- Perfis inativos nao autorizam nenhum modulo, mesmo quando possuem permissoes vinculadas.
- O pivot `user_roles` usa `deleted_at`; relacoes Eloquent ignoram vinculos removidos logicamente.
- O modulo [Access](../modules/access.md) aceita apenas perfis globais do
  sistema que estejam ativos.
