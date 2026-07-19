<?php

namespace App\Modules\Implementation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Http\Response;

class ImplementationController extends Controller
{
    private const TEMPLATES = [
        'tutors' => ['nome', 'telefone', 'whatsapp', 'email', 'cpf_cnpj', 'endereco', 'observacoes'],
        'patients' => ['tutor_documento', 'nome_pet', 'especie', 'raca', 'sexo', 'nascimento', 'peso', 'observacoes'],
        'products' => ['nome', 'ean_gtin', 'sku', 'categoria', 'fornecedor_documento', 'custo', 'preco_venda', 'estoque_atual', 'estoque_minimo'],
        'suppliers' => ['nome', 'cpf_cnpj', 'telefone', 'email', 'cidade', 'estado', 'observacoes'],
        'stock' => ['ean_gtin_ou_sku', 'quantidade', 'custo_unitario', 'lote', 'validade', 'observacoes'],
        'financial' => ['tipo', 'descricao', 'pessoa_documento', 'valor', 'vencimento', 'status', 'forma_pagamento'],
    ];

    public function index()
    {
        return view('implementation.index', [
            'clinicsCount' => Clinic::query()->count(),
            'templates' => array_keys(self::TEMPLATES),
            'migrationBlocks' => [
                'Clinica e usuarios',
                'Tutores e contatos',
                'Pacientes e historico basico',
                'Produtos, fornecedores e estoque inicial',
                'Financeiro inicial e contas abertas',
                'Historico clinico e anexos',
            ],
            'implantationSteps' => [
                'Cadastrar ou selecionar a clinica destino',
                'Receber backup/planilhas do sistema antigo',
                'Mapear colunas para o padrao VetFlow',
                'Validar duplicidades e dados obrigatorios',
                'Importar em ambiente de teste',
                'Conferir amostras com a clinica antes do corte',
            ],
        ]);
    }

    public function template(string $template): Response
    {
        abort_unless(isset(self::TEMPLATES[$template]), 404);

        return response(implode(',', self::TEMPLATES[$template]).PHP_EOL, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=vetflow-migracao-{$template}.csv",
        ]);
    }
}
