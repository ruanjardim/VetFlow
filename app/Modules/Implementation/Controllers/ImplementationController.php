<?php

namespace App\Modules\Implementation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;

class ImplementationController extends Controller
{
    public function index()
    {
        return view('implementation.index', [
            'clinicsCount' => Clinic::query()->count(),
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
}
