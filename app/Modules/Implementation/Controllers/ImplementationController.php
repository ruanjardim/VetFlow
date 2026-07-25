<?php

namespace App\Modules\Implementation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImplementationController extends Controller
{
    private const TEMPLATES = [
        'tutors' => [
            'nome',
            'telefone',
            'whatsapp',
            'email',
            'cpf_cnpj',
            'endereco',
            'observacoes',
        ],
        'patients' => [
            'tutor_documento',
            'nome_pet',
            'especie',
            'raca',
            'sexo',
            'nascimento',
            'peso',
            'observacoes',
        ],
        'products' => [
            'nome',
            'ean_gtin',
            'sku',
            'categoria',
            'fornecedor_documento',
            'custo',
            'preco_venda',
            'estoque_atual',
            'estoque_minimo',
        ],
        'suppliers' => [
            'nome',
            'cpf_cnpj',
            'telefone',
            'email',
            'cidade',
            'estado',
            'observacoes',
        ],
        'stock' => [
            'ean_gtin_ou_sku',
            'quantidade',
            'custo_unitario',
            'lote',
            'validade',
            'observacoes',
        ],
        'financial' => [
            'tipo',
            'descricao',
            'pessoa_documento',
            'valor',
            'vencimento',
            'status',
            'forma_pagamento',
        ],
    ];

    private const WIZARD_STEPS = [
        1 => [
            'slug' => 'clinic',
            'title' => 'Clínica destino',
            'short_title' => 'Clínica',
            'description' => 'Selecione a clínica que receberá os dados da implantação.',
        ],
        2 => [
            'slug' => 'source',
            'title' => 'Origem dos dados',
            'short_title' => 'Origem',
            'description' => 'Informe de onde os dados serão obtidos.',
        ],
        3 => [
            'slug' => 'upload',
            'title' => 'Envio de arquivos',
            'short_title' => 'Upload',
            'description' => 'Envie os arquivos que serão analisados pelo VetFlow.',
        ],
        4 => [
            'slug' => 'mapping',
            'title' => 'Mapeamento',
            'short_title' => 'Mapeamento',
            'description' => 'Relacione as colunas do sistema antigo aos campos do VetFlow.',
        ],
        5 => [
            'slug' => 'validation',
            'title' => 'Validação',
            'short_title' => 'Validação',
            'description' => 'Confira dados obrigatórios, duplicidades e inconsistências.',
        ],
        6 => [
            'slug' => 'preview',
            'title' => 'Pré-visualização',
            'short_title' => 'Prévia',
            'description' => 'Revise uma amostra dos registros antes da importação.',
        ],
        7 => [
            'slug' => 'import',
            'title' => 'Importação',
            'short_title' => 'Importação',
            'description' => 'Acompanhe o processamento dos blocos selecionados.',
        ],
        8 => [
            'slug' => 'finish',
            'title' => 'Finalização',
            'short_title' => 'Finalização',
            'description' => 'Confira o resumo e conclua a implantação.',
        ],
    ];

    public function index(Request $request)
    {
        $currentStep = (int) $request->integer('step', 1);
        $currentStep = max(1, min($currentStep, count(self::WIZARD_STEPS)));

        return view('implementation.index', [
            'clinics' => Clinic::query()
                ->orderBy('trade_name')
                ->get(),
            'clinicsCount' => Clinic::query()->count(),
            'templates' => array_keys(self::TEMPLATES),
            'wizardSteps' => self::WIZARD_STEPS,
            'currentStep' => $currentStep,
            'currentStepData' => self::WIZARD_STEPS[$currentStep],
            'previousStep' => $currentStep > 1 ? $currentStep - 1 : null,
            'nextStep' => $currentStep < count(self::WIZARD_STEPS)
                ? $currentStep + 1
                : null,
            'progressPercentage' => (int) round(
                ($currentStep / count(self::WIZARD_STEPS)) * 100
            ),
            'migrationBlocks' => [
                'Tutores e contatos',
                'Pacientes e histórico básico',
                'Produtos',
                'Fornecedores',
                'Estoque inicial',
                'Financeiro inicial e contas abertas',
            ],
            'dataSources' => [
                'csv' => 'Arquivo CSV',
                'excel' => 'Planilha Excel',
                'sqlite' => 'Banco SQLite',
                'mysql' => 'Banco MySQL',
                'postgresql' => 'Banco PostgreSQL',
                'sql-server' => 'Banco SQL Server',
                'xml' => 'Arquivo XML',
                'other-erp' => 'Outro sistema ou ERP',
            ],
        ]);
    }

    public function template(string $template): Response
    {
        abort_unless(isset(self::TEMPLATES[$template]), 404);

        return response(
            implode(',', self::TEMPLATES[$template]).PHP_EOL,
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=vetflow-migracao-{$template}.csv",
            ]
        );
    }
}
