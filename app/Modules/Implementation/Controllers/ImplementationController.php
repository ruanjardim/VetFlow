<?php

namespace App\Modules\Implementation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Requests\SelectClinicRequest;
use App\Modules\Implementation\Requests\SelectSourceRequest;
use App\Modules\Implementation\Requests\UploadPatientCsvRequest;
use App\Modules\Implementation\Requests\UploadTutorCsvRequest;
use App\Modules\Implementation\Services\ImplementationWorkflowService;
use App\Modules\Implementation\Services\PatientCsvImportService;
use App\Modules\Implementation\Services\TutorCsvImportService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImplementationController extends Controller
{
    private const IMPORTS = [
        'tutors' => [
            'label' => 'Tutores',
            'singular' => 'Tutor',
            'template' => 'tutors',
            'template_label' => 'Tutores CSV',
            'upload_route' => 'implementation.tutors.upload',
            'import_route' => 'implementation.tutors.import',
            'input_name' => 'tutors_file',
            'input_id' => 'tutors-file',
            'default_file' => 'tutores.csv',
            'expected_columns' => 'nome, telefone, whatsapp, email, cpf_cnpj, endereco e observacoes',
            'preview_columns' => [
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'phone', 'label' => 'Telefone'],
                ['key' => 'phone_secondary', 'label' => 'WhatsApp'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'cpf', 'label' => 'CPF'],
            ],
        ],
        'patients' => [
            'label' => 'Pacientes',
            'singular' => 'Paciente',
            'template' => 'patients',
            'template_label' => 'Pacientes CSV',
            'upload_route' => 'implementation.patients.upload',
            'import_route' => 'implementation.patients.import',
            'input_name' => 'patients_file',
            'input_id' => 'patients-file',
            'default_file' => 'pacientes.csv',
            'expected_columns' => 'tutor_documento, nome_pet, especie, raca, sexo, nascimento, peso e observacoes',
            'preview_columns' => [
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'tutor_name', 'label' => 'Tutor'],
                ['key' => 'species', 'label' => 'Espécie'],
                ['key' => 'breed', 'label' => 'Raça'],
                ['key' => 'birth_date', 'label' => 'Nascimento'],
                ['key' => 'weight', 'label' => 'Peso'],
            ],
        ],
    ];

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
            'description' => 'Confira como as colunas do CSV serão gravadas no VetFlow.',
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
            'description' => 'Confirme a gravação dos registros validados na clínica destino.',
        ],
        8 => [
            'slug' => 'finish',
            'title' => 'Finalização',
            'short_title' => 'Finalização',
            'description' => 'Confira o resumo e conclua a implantação.',
        ],
    ];

    public function __construct(
        private readonly ImplementationWorkflowService $workflow,
        private readonly TutorCsvImportService $tutorCsvImporter,
        private readonly PatientCsvImportService $patientCsvImporter
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $clinics = $this->accessibleClinics($request)
            ->orderBy('trade_name')
            ->get();
        $state = $this->workflow->state();
        $selectedClinic = isset($state['clinic_id'])
            ? $clinics->firstWhere('id', (int) $state['clinic_id'])
            : null;

        if (isset($state['clinic_id']) && $selectedClinic === null) {
            $this->workflow->reset();
            $state = [];
        }

        $maxAllowedStep = $this->workflow->maxAllowedStep();
        $currentStep = (int) $request->integer('step', 1);
        $currentStep = max(1, min($currentStep, count(self::WIZARD_STEPS)));

        if ($currentStep > $maxAllowedStep) {
            return redirect()
                ->route('implementation.index', ['step' => $maxAllowedStep])
                ->with('warning', 'Conclua a etapa atual antes de continuar.');
        }

        $analysis = $this->workflow->analysis();
        $entityType = isset(self::IMPORTS[$state['entity_type'] ?? null])
            ? $state['entity_type']
            : 'tutors';
        $activeImport = self::IMPORTS[$entityType];
        $importer = $entityType === 'patients'
            ? $this->patientCsvImporter
            : $this->tutorCsvImporter;

        return view('implementation.index', [
            'clinics' => $clinics,
            'clinicsCount' => $clinics->count(),
            'selectedClinic' => $selectedClinic,
            'availableImports' => self::IMPORTS,
            'entityType' => $entityType,
            'activeImport' => $activeImport,
            'wizardSteps' => self::WIZARD_STEPS,
            'currentStep' => $currentStep,
            'currentStepData' => self::WIZARD_STEPS[$currentStep],
            'maxAllowedStep' => $maxAllowedStep,
            'previousStep' => $currentStep > 1 ? $currentStep - 1 : null,
            'nextStep' => $currentStep < count(self::WIZARD_STEPS)
                ? $currentStep + 1
                : null,
            'progressPercentage' => (int) round(
                ($currentStep / count(self::WIZARD_STEPS)) * 100
            ),
            'migrationBlocks' => [
                ['label' => 'Tutores e contatos', 'available' => true],
                ['label' => 'Pacientes e histórico básico', 'available' => true],
                ['label' => 'Produtos', 'available' => false],
                ['label' => 'Fornecedores', 'available' => false],
                ['label' => 'Estoque inicial', 'available' => false],
                ['label' => 'Financeiro inicial e contas abertas', 'available' => false],
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
            'wizardState' => $state,
            'analysis' => $analysis,
            'mappingDefinitions' => $importer->mappingDefinitions(),
            'completedSummary' => $state['completed'] ?? null,
        ]);
    }

    public function selectClinic(SelectClinicRequest $request): RedirectResponse
    {
        $this->workflow->start((int) $request->validated('clinic_id'));

        return redirect()
            ->route('implementation.index', ['step' => 2])
            ->with('success', 'Clínica destino selecionada.');
    }

    public function selectSource(SelectSourceRequest $request): RedirectResponse
    {
        $state = $this->workflow->state();

        if ($this->selectedClinic($request, $state) === null) {
            $this->workflow->reset();

            return redirect()
                ->route('implementation.index', ['step' => 1])
                ->with('warning', 'Selecione uma clínica disponível antes de continuar.');
        }

        $this->workflow->selectSource($request->validated('data_source'));

        return redirect()
            ->route('implementation.index', ['step' => 3])
            ->with('success', 'Origem CSV selecionada.');
    }

    public function uploadTutors(UploadTutorCsvRequest $request): RedirectResponse
    {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);

        if ($clinic === null || ($state['data_source'] ?? null) !== 'csv') {
            return redirect()
                ->route('implementation.index', ['step' => $clinic === null ? 1 : 2])
                ->with('warning', 'Conclua a configuração da importação antes de enviar o CSV.');
        }

        $file = $request->file('tutors_file');
        $analysis = $this->tutorCsvImporter->analyze($file, $clinic->id);

        $this->workflow->storeAnalysis(
            $analysis,
            $file->getClientOriginalName(),
            'tutors'
        );

        $response = redirect()->route('implementation.index', ['step' => 4]);

        return $analysis['can_import']
            ? $response->with('success', 'CSV analisado. Confira o mapeamento e a prévia antes de importar.')
            : $response->with('warning', 'CSV analisado com pendências. Revise os erros encontrados.');
    }

    public function importTutors(Request $request): RedirectResponse
    {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);
        $analysis = $this->workflow->analysis();

        if (
            $clinic === null
            || $analysis === null
            || ($state['entity_type'] ?? null) !== 'tutors'
            || ! ($analysis['can_import'] ?? false)
        ) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('warning', 'Corrija as pendências do arquivo antes de importar.');
        }

        try {
            $result = $this->tutorCsvImporter->import($analysis, $clinic->id);
        } catch (DomainException $exception) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('error', $exception->getMessage());
        }

        $this->workflow->complete([
            'entity_type' => 'tutors',
            'entity_label' => self::IMPORTS['tutors']['label'],
            'clinic_name' => $clinic->trade_name,
            'file_name' => $state['file_name'] ?? 'tutores.csv',
            'imported_count' => $result['imported_count'],
            'completed_at' => now()->format('d/m/Y H:i'),
        ]);

        return redirect()
            ->route('implementation.index', ['step' => 8])
            ->with('success', 'Importação de tutores concluída com sucesso.');
    }

    public function uploadPatients(UploadPatientCsvRequest $request): RedirectResponse
    {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);

        if ($clinic === null || ($state['data_source'] ?? null) !== 'csv') {
            return redirect()
                ->route('implementation.index', ['step' => $clinic === null ? 1 : 2])
                ->with('warning', 'Conclua a configuração da importação antes de enviar o CSV.');
        }

        $file = $request->file('patients_file');
        $analysis = $this->patientCsvImporter->analyze($file, $clinic->id);

        $this->workflow->storeAnalysis(
            $analysis,
            $file->getClientOriginalName(),
            'patients'
        );

        $response = redirect()->route('implementation.index', ['step' => 4]);

        return $analysis['can_import']
            ? $response->with('success', 'CSV analisado. Confira o mapeamento e a prévia antes de importar.')
            : $response->with('warning', 'CSV analisado com pendências. Revise os erros encontrados.');
    }

    public function importPatients(Request $request): RedirectResponse
    {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);
        $analysis = $this->workflow->analysis();

        if (
            $clinic === null
            || $analysis === null
            || ($state['entity_type'] ?? null) !== 'patients'
            || ! ($analysis['can_import'] ?? false)
        ) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('warning', 'Corrija as pendências do arquivo antes de importar.');
        }

        try {
            $result = $this->patientCsvImporter->import($analysis, $clinic->id);
        } catch (DomainException $exception) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('error', $exception->getMessage());
        }

        $this->workflow->complete([
            'entity_type' => 'patients',
            'entity_label' => self::IMPORTS['patients']['label'],
            'clinic_name' => $clinic->trade_name,
            'file_name' => $state['file_name'] ?? 'pacientes.csv',
            'imported_count' => $result['imported_count'],
            'completed_at' => now()->format('d/m/Y H:i'),
        ]);

        return redirect()
            ->route('implementation.index', ['step' => 8])
            ->with('success', 'Importação de pacientes concluída com sucesso.');
    }

    public function reset(): RedirectResponse
    {
        $this->workflow->reset();

        return redirect()
            ->route('implementation.index')
            ->with('success', 'O assistente está pronto para uma nova importação.');
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

    private function accessibleClinics(Request $request): Builder
    {
        $query = Clinic::query()->active();
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId !== null) {
            $query->whereKey($clinicId);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function selectedClinic(Request $request, array $state): ?Clinic
    {
        $clinicId = $state['clinic_id'] ?? null;

        if ($clinicId === null) {
            return null;
        }

        return $this->accessibleClinics($request)
            ->whereKey((int) $clinicId)
            ->first();
    }
}
