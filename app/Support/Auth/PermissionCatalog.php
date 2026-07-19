<?php

namespace App\Support\Auth;

class PermissionCatalog
{
    /**
     * @return array<int, array{slug: string, name: string, group: string, description: string}>
     */
    public static function permissions(): array
    {
        return [
            [
                'slug' => 'dashboard.view',
                'name' => 'Visualizar dashboard',
                'group' => 'Dashboard',
                'description' => 'Permite acessar os indicadores iniciais do VetFlow.',
            ],
            [
                'slug' => 'clinics.manage',
                'name' => 'Gerenciar clinicas',
                'group' => 'Administrativo',
                'description' => 'Permite acessar cadastro e manutencao de clinicas.',
            ],
            [
                'slug' => 'implementation.manage',
                'name' => 'Gerenciar implantacao',
                'group' => 'Administrativo',
                'description' => 'Permite acessar o roteiro de implantacao e migracao de dados da clinica.',
            ],
            [
                'slug' => 'tutors.manage',
                'name' => 'Gerenciar tutores',
                'group' => 'Atendimento',
                'description' => 'Permite acessar cadastro e manutencao de tutores.',
            ],
            [
                'slug' => 'patients.manage',
                'name' => 'Gerenciar pacientes',
                'group' => 'Atendimento',
                'description' => 'Permite acessar cadastro e manutencao de pacientes.',
            ],
            [
                'slug' => 'schedules.manage',
                'name' => 'Gerenciar agenda',
                'group' => 'Atendimento',
                'description' => 'Permite acessar a agenda operacional.',
            ],
            [
                'slug' => 'appointments.manage',
                'name' => 'Gerenciar consultas',
                'group' => 'Atendimento',
                'description' => 'Permite acessar consultas e atendimentos clinicos.',
            ],
            [
                'slug' => 'petshop-services.manage',
                'name' => 'Gerenciar servicos PetShop',
                'group' => 'Operacao',
                'description' => 'Permite acessar servicos e procedimentos do PetShop.',
            ],
            [
                'slug' => 'service-orders.manage',
                'name' => 'Gerenciar comandas',
                'group' => 'Operacao',
                'description' => 'Permite acessar comandas e ordens de servico.',
            ],
            [
                'slug' => 'sales.manage',
                'name' => 'Gerenciar PDV e vendas',
                'group' => 'Vendas',
                'description' => 'Permite acessar vendas, caixa, recibos, cancelamentos e devolucoes.',
            ],
            [
                'slug' => 'products.manage',
                'name' => 'Gerenciar produtos',
                'group' => 'Estoque',
                'description' => 'Permite acessar produtos, consultas GTIN e diagnosticos.',
            ],
            [
                'slug' => 'global-products.manage',
                'name' => 'Gerenciar catalogo global',
                'group' => 'Inteligencia',
                'description' => 'Permite acessar catalogo global e inteligencia de produtos.',
            ],
            [
                'slug' => 'inventory.manage',
                'name' => 'Gerenciar estoque',
                'group' => 'Estoque',
                'description' => 'Permite acessar movimentacoes, lotes e alertas de estoque.',
            ],
            [
                'slug' => 'purchase-entries.manage',
                'name' => 'Gerenciar entradas',
                'group' => 'Compras',
                'description' => 'Permite acessar entradas de compra, NF-e e reposicao.',
            ],
            [
                'slug' => 'suppliers.manage',
                'name' => 'Gerenciar fornecedores',
                'group' => 'Compras',
                'description' => 'Permite acessar cadastro e manutencao de fornecedores.',
            ],
            [
                'slug' => 'financial.manage',
                'name' => 'Gerenciar financeiro',
                'group' => 'Financeiro',
                'description' => 'Permite acessar contas, fluxo de caixa, baixas e cancelamentos.',
            ],
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public static function roles(): array
    {
        $allPermissions = self::slugs();

        return [
            'administrador' => [
                'name' => 'Administrador',
                'description' => 'Acesso completo ao VetFlow.',
                'permissions' => $allPermissions,
            ],
            'atendimento' => [
                'name' => 'Atendimento',
                'description' => 'Acesso aos fluxos de recepcao, agenda, tutores, pacientes e comandas.',
                'permissions' => [
                    'dashboard.view',
                    'implementation.manage',
                    'tutors.manage',
                    'patients.manage',
                    'schedules.manage',
                    'appointments.manage',
                    'petshop-services.manage',
                    'service-orders.manage',
                    'sales.manage',
                ],
            ],
            'estoque-compras' => [
                'name' => 'Estoque e compras',
                'description' => 'Acesso a produtos, estoque, compras, fornecedores e catalogo global.',
                'permissions' => [
                    'dashboard.view',
                    'products.manage',
                    'global-products.manage',
                    'inventory.manage',
                    'purchase-entries.manage',
                    'suppliers.manage',
                ],
            ],
            'financeiro' => [
                'name' => 'Financeiro',
                'description' => 'Acesso a financeiro, vendas, fornecedores e entradas.',
                'permissions' => [
                    'dashboard.view',
                    'sales.manage',
                    'purchase-entries.manage',
                    'suppliers.manage',
                    'financial.manage',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_column(self::permissions(), 'slug');
    }
}
