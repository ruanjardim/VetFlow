<?php

namespace App\Modules\Saas\Support;

class FeatureCatalog
{
    /** @return array<string, array{name: string, type: string, description: string}> */
    public static function definitions(): array
    {
        return [
            'dashboard' => ['name' => 'Dashboard', 'type' => 'boolean', 'description' => 'Indicadores iniciais do estabelecimento.'],
            'clients' => ['name' => 'Clientes e responsáveis', 'type' => 'boolean', 'description' => 'Cadastro e gestão de tutores.'],
            'pets' => ['name' => 'Pets e pacientes', 'type' => 'boolean', 'description' => 'Cadastro e gestão de pacientes.'],
            'agenda' => ['name' => 'Agenda e consultas', 'type' => 'boolean', 'description' => 'Agenda, consultas e compromissos.'],
            'petshop_services' => ['name' => 'Banho, tosa e serviços', 'type' => 'boolean', 'description' => 'Serviços de pet shop e ordens de serviço.'],
            'veterinary' => ['name' => 'Módulo veterinário', 'type' => 'boolean', 'description' => 'Prontuário, vacinação, internação e receituário.'],
            'pdv' => ['name' => 'PDV e vendas', 'type' => 'boolean', 'description' => 'Frente de caixa e gestão de vendas.'],
            'products' => ['name' => 'Produtos e catálogo', 'type' => 'boolean', 'description' => 'Catálogo local e inteligência de produtos.'],
            'inventory' => ['name' => 'Estoque', 'type' => 'boolean', 'description' => 'Movimentações e posição de estoque.'],
            'purchases' => ['name' => 'Compras e entradas', 'type' => 'boolean', 'description' => 'Entradas e compras de mercadorias.'],
            'suppliers' => ['name' => 'Fornecedores', 'type' => 'boolean', 'description' => 'Cadastro e gestão de fornecedores.'],
            'financial' => ['name' => 'Financeiro', 'type' => 'boolean', 'description' => 'Contas, caixa e visão financeira.'],
            'commissions' => ['name' => 'Comissões', 'type' => 'boolean', 'description' => 'Regras e apuração de comissões.'],
            'max_users' => ['name' => 'Limite de usuários ativos', 'type' => 'numeric', 'description' => 'Quantidade máxima de usuários ativos.'],
            'max_units' => ['name' => 'Limite de unidades', 'type' => 'numeric', 'description' => 'Quantidade máxima de unidades do estabelecimento.'],
        ];
    }

    /** @return array<string, string> */
    public static function permissionMap(): array
    {
        return [
            'dashboard.view' => 'dashboard',
            'tutors.manage' => 'clients',
            'patients.manage' => 'pets',
            'schedules.manage' => 'agenda',
            'appointments.manage' => 'agenda',
            'petshop-services.manage' => 'petshop_services',
            'service-orders.manage' => 'petshop_services',
            'medical-records.manage' => 'veterinary',
            'vaccinations.manage' => 'veterinary',
            'hospitalizations.manage' => 'veterinary',
            'prescriptions.manage' => 'veterinary',
            'sales.manage' => 'pdv',
            'products.manage' => 'products',
            'global-products.manage' => 'products',
            'inventory.manage' => 'inventory',
            'purchase-entries.manage' => 'purchases',
            'suppliers.manage' => 'suppliers',
            'financial.manage' => 'financial',
            'commissions.manage' => 'commissions',
        ];
    }

    public static function forPermission(string $permission): ?string
    {
        return self::permissionMap()[$permission] ?? null;
    }
}
