<?php

namespace App\Modules\Implementation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Implementation\Contracts\CsvImportService;
use App\Modules\Implementation\Requests\SelectClinicRequest;
use App\Modules\Implementation\Requests\SelectSourceRequest;
use App\Modules\Implementation\Requests\UploadImplementationFileRequest;
use App\Modules\Implementation\Services\ExcelTemplateService;
use App\Modules\Implementation\Services\FinancialCsvImportService;
use App\Modules\Implementation\Services\ImplementationFileAnalyzer;
use App\Modules\Implementation\Services\ImplementationImportService;
use App\Modules\Implementation\Services\ImplementationWorkflowService;
use App\Modules\Implementation\Services\PatientCsvImportService;
use App\Modules\Implementation\Services\ProductCsvImportService;
use App\Modules\Implementation\Services\StockCsvImportService;
use App\Modules\Implementation\Services\SupplierCsvImportService;
use App\Modules\Implementation\Services\TutorCsvImportService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        'suppliers' => [
            'label' => 'Fornecedores',
            'singular' => 'Fornecedor',
            'template' => 'suppliers',
            'template_label' => 'Fornecedores CSV',
            'upload_route' => 'implementation.suppliers.upload',
            'import_route' => 'implementation.suppliers.import',
            'input_name' => 'suppliers_file',
            'input_id' => 'suppliers-file',
            'default_file' => 'fornecedores.csv',
            'expected_columns' => 'nome, cpf_cnpj, telefone, email, cidade, estado e observacoes',
            'preview_columns' => [
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'document', 'label' => 'CPF/CNPJ'],
                ['key' => 'phone', 'label' => 'Telefone'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'city', 'label' => 'Cidade'],
                ['key' => 'state', 'label' => 'UF'],
            ],
        ],
        'products' => [
            'label' => 'Produtos',
            'singular' => 'Produto',
            'template' => 'products',
            'template_label' => 'Produtos CSV',
            'upload_route' => 'implementation.products.upload',
            'import_route' => 'implementation.products.import',
            'input_name' => 'products_file',
            'input_id' => 'products-file',
            'default_file' => 'produtos.csv',
            'expected_columns' => 'nome, ean_gtin, sku, categoria, fornecedor_documento, custo, preco_venda, estoque_atual e estoque_minimo',
            'preview_columns' => [
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'gtin', 'label' => 'EAN/GTIN'],
                ['key' => 'sku', 'label' => 'SKU'],
                ['key' => 'supplier_name', 'label' => 'Fornecedor'],
                ['key' => 'cost_price', 'label' => 'Custo'],
                ['key' => 'sale_price', 'label' => 'Venda'],
                ['key' => 'initial_stock', 'label' => 'Estoque inicial'],
            ],
        ],
        'stock' => [
            'label' => 'Entradas de estoque',
            'singular' => 'Movimento',
            'template' => 'stock',
            'template_label' => 'Estoque CSV',
            'upload_route' => 'implementation.stock.upload',
            'import_route' => 'implementation.stock.import',
            'input_name' => 'stock_file',
            'input_id' => 'stock-file',
            'default_file' => 'estoque.csv',
            'expected_columns' => 'ean_gtin_ou_sku, quantidade, custo_unitario, lote, validade e observacoes',
            'preview_columns' => [
                ['key' => 'product_name', 'label' => 'Produto'],
                ['key' => 'identifier', 'label' => 'EAN/GTIN ou SKU'],
                ['key' => 'quantity', 'label' => 'Quantidade'],
                ['key' => 'unit_cost', 'label' => 'Custo unitário'],
                ['key' => 'lot_number', 'label' => 'Lote'],
                ['key' => 'expires_at', 'label' => 'Validade'],
            ],
        ],
        'financial' => [
            'label' => 'Financeiro',
            'singular' => 'Lançamento',
            'template' => 'financial',
            'template_label' => 'Financeiro CSV',
            'upload_route' => 'implementation.financial.upload',
            'import_route' => 'implementation.financial.import',
            'input_name' => 'financial_file',
            'input_id' => 'financial-file',
            'default_file' => 'financeiro.csv',
            'expected_columns' => 'tipo, descricao, pessoa_documento, valor, vencimento, status, forma_pagamento, data_pagamento, referencia e observacoes',
            'preview_columns' => [
                ['key' => 'description', 'label' => 'Descrição'],
                ['key' => 'type_label', 'label' => 'Tipo'],
                ['key' => 'supplier_name', 'label' => 'Fornecedor'],
                ['key' => 'amount', 'label' => 'Valor'],
                ['key' => 'due_date', 'label' => 'Vencimento'],
                ['key' => 'status_label', 'label' => 'Status'],
                ['key' => 'payment_method_label', 'label' => 'Pagamento'],
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
            'data_pagamento',
            'referencia',
            'observacoes',
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
            'description' => 'Confira como as colunas do arquivo serão gravadas no VetFlow.',
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
        private readonly PatientCsvImportService $patientCsvImporter,
        private readonly SupplierCsvImportService $supplierCsvImporter,
        private readonly ProductCsvImportService $productCsvImporter,
        private readonly StockCsvImportService $stockCsvImporter,
        private readonly FinancialCsvImportService $financialCsvImporter,
        private readonly ImplementationImportService $implementationImporter,
        private readonly ImplementationFileAnalyzer $fileAnalyzer,
        private readonly ExcelTemplateService $excelTemplates
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
        $importer = $this->importerFor($entityType);
        $dataSource = in_array($state['data_source'] ?? null, ['csv', 'excel'], true)
            ? $state['data_source']
            : 'csv';
        /** @var User $user */
        $user = $request->user();

        return view('implementation.index', [
            'clinics' => $clinics,
            'clinicsCount' => $clinics->count(),
            'selectedClinic' => $selectedClinic,
            'availableImports' => self::IMPORTS,
            'entityType' => $entityType,
            'activeImport' => $activeImport,
            'dataSource' => $dataSource,
            'sourceLabel' => $dataSource === 'excel' ? 'Excel' : 'CSV',
            'defaultFile' => $this->defaultFile($entityType, $dataSource),
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
                ['label' => 'Fornecedores', 'available' => true],
                ['label' => 'Produtos', 'available' => true],
                ['label' => 'Estoque inicial', 'available' => true],
                ['label' => 'Financeiro inicial e contas abertas', 'available' => true],
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
            'recentImports' => $this->implementationImporter->recentFor($user),
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

        $dataSource = $request->validated('data_source');
        $this->workflow->selectSource($dataSource);

        return redirect()
            ->route('implementation.index', ['step' => 3])
            ->with(
                'success',
                sprintf('Origem %s selecionada.', $dataSource === 'excel' ? 'Excel' : 'CSV')
            );
    }

    public function uploadTutors(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'tutors', 'tutors_file');
    }

    public function importTutors(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'tutors');
    }

    public function uploadPatients(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'patients', 'patients_file');
    }

    public function importPatients(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'patients');
    }

    public function uploadSuppliers(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'suppliers', 'suppliers_file');
    }

    public function importSuppliers(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'suppliers');
    }

    public function uploadProducts(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'products', 'products_file');
    }

    public function importProducts(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'products');
    }

    public function uploadStock(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'stock', 'stock_file');
    }

    public function importStock(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'stock');
    }

    public function uploadFinancial(UploadImplementationFileRequest $request): RedirectResponse
    {
        return $this->uploadFile($request, 'financial', 'financial_file');
    }

    public function importFinancial(Request $request): RedirectResponse
    {
        return $this->importFile($request, 'financial');
    }

    private function uploadFile(
        Request $request,
        string $entityType,
        string $inputName
    ): RedirectResponse {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);

        $dataSource = $state['data_source'] ?? null;

        if (
            $clinic === null
            || ! in_array($dataSource, ['csv', 'excel'], true)
        ) {
            return redirect()
                ->route('implementation.index', ['step' => $clinic === null ? 1 : 2])
                ->with('warning', 'Conclua a configuração da importação antes de enviar o arquivo.');
        }

        $file = $request->file($inputName);

        if (! $file instanceof UploadedFile) {
            return redirect()
                ->route('implementation.index', ['step' => 3])
                ->with('warning', 'Selecione um arquivo válido para analisar.');
        }

        try {
            $analysis = $this->fileAnalyzer->analyze(
                $this->importerFor($entityType),
                $file,
                $clinic->id,
                $dataSource
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('implementation.index', ['step' => 3])
                ->with('error', $exception->getMessage());
        }

        $this->workflow->storeAnalysis(
            $analysis,
            $file->getClientOriginalName(),
            $entityType,
            $dataSource
        );

        $response = redirect()->route('implementation.index', ['step' => 4]);

        $sourceLabel = $dataSource === 'excel' ? 'Planilha Excel' : 'CSV';

        return $analysis['can_import']
            ? $response->with(
                'success',
                "{$sourceLabel} analisado. Confira o mapeamento e a prévia antes de importar."
            )
            : $response->with(
                'warning',
                "{$sourceLabel} analisado com pendências. Revise os erros encontrados."
            );
    }

    private function importFile(
        Request $request,
        string $entityType
    ): RedirectResponse {
        $state = $this->workflow->state();
        $clinic = $this->selectedClinic($request, $state);
        $analysis = $this->workflow->analysis();

        if (
            $clinic === null
            || $analysis === null
            || ($state['entity_type'] ?? null) !== $entityType
            || ! ($analysis['can_import'] ?? false)
        ) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('warning', 'Corrija as pendências do arquivo antes de importar.');
        }

        $config = self::IMPORTS[$entityType];
        $completedAt = now();
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->implementationImporter->import(
                $this->importerFor($entityType),
                $analysis,
                $clinic,
                $user,
                $entityType,
                $config['label'],
                $state['data_source'] ?? 'csv',
                $state['file_name'] ?? $this->defaultFile($entityType, $state['data_source'] ?? 'csv'),
                $completedAt
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('implementation.index', ['step' => 5])
                ->with('error', $exception->getMessage());
        }

        $this->workflow->complete([
            'entity_type' => $entityType,
            'entity_label' => $config['label'],
            'clinic_name' => $clinic->trade_name,
            'file_name' => $state['file_name'] ?? $this->defaultFile($entityType, $state['data_source'] ?? 'csv'),
            'imported_count' => $result['imported_count'],
            'completed_at' => $completedAt->format('d/m/Y H:i'),
        ]);

        return redirect()
            ->route('implementation.index', ['step' => 8])
            ->with(
                'success',
                'Importação de '.mb_strtolower($config['label']).' concluída com sucesso.'
            );
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

    public function excelTemplate(string $template): StreamedResponse
    {
        abort_unless(isset(self::TEMPLATES[$template], self::IMPORTS[$template]), 404);

        return response()->streamDownload(
            fn () => $this->excelTemplates->write(
                self::TEMPLATES[$template],
                self::IMPORTS[$template]['label']
            ),
            "vetflow-migracao-{$template}.xlsx",
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

    private function importerFor(string $entityType): CsvImportService
    {
        return match ($entityType) {
            'patients' => $this->patientCsvImporter,
            'suppliers' => $this->supplierCsvImporter,
            'products' => $this->productCsvImporter,
            'stock' => $this->stockCsvImporter,
            'financial' => $this->financialCsvImporter,
            default => $this->tutorCsvImporter,
        };
    }

    private function defaultFile(string $entityType, string $dataSource): string
    {
        $csvFile = self::IMPORTS[$entityType]['default_file'];

        return $dataSource === 'excel'
            ? preg_replace('/\.csv$/', '.xlsx', $csvFile) ?? $csvFile
            : $csvFile;
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
