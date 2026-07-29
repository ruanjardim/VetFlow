<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Services\ImplementationWorkflowService;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Patients\Models\Patient;
use App\Modules\Products\Models\Product;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tutors\Models\Tutor;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImplementationExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_all_six_blocks_from_excel(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Excel', '12345678000190');
        $user = $this->authorizedUser();

        $this->importExcel(
            $user,
            $clinic,
            'tutors',
            'tutors_file',
            [
                'nome',
                'telefone',
                'whatsapp',
                'email',
                'cpf_cnpj',
                'endereco',
                'observacoes',
            ],
            [
                'Ana Excel',
                '21999990001',
                '',
                'ana@example.com',
                '52998224725',
                'Rua Excel',
                '',
            ]
        );

        $tutor = Tutor::query()->where('name', 'Ana Excel')->sole();
        $this->assertSame($clinic->id, $tutor->clinic_id);

        $this->importExcel(
            $user,
            $clinic,
            'patients',
            'patients_file',
            [
                'tutor_documento',
                'nome_pet',
                'especie',
                'raca',
                'sexo',
                'nascimento',
                'peso',
                'observacoes',
            ],
            [
                '52998224725',
                'Luna Excel',
                'Canina',
                'SRD',
                'Fêmea',
                new DateTimeImmutable('2020-05-01'),
                12.5,
                '',
            ]
        );

        $patient = Patient::query()->where('name', 'Luna Excel')->sole();
        $this->assertSame($clinic->id, $patient->clinic_id);
        $this->assertSame($tutor->id, $patient->tutor_id);
        $this->assertSame('2020-05-01', $patient->birth_date?->toDateString());

        $this->importExcel(
            $user,
            $clinic,
            'suppliers',
            'suppliers_file',
            [
                'nome',
                'cpf_cnpj',
                'telefone',
                'email',
                'cidade',
                'estado',
                'observacoes',
            ],
            [
                'Fornecedor Excel',
                '52998224725',
                '21999990002',
                'fornecedor@example.com',
                'Niterói',
                'RJ',
                '',
            ]
        );

        $supplier = Supplier::query()->where('name', 'Fornecedor Excel')->sole();
        $this->assertSame($clinic->id, $supplier->clinic_id);

        $this->importExcel(
            $user,
            $clinic,
            'products',
            'products_file',
            [
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
            [
                'Produto Excel',
                '7891000100103',
                'EXCEL-001',
                'Testes',
                '52998224725',
                12.5,
                20.9,
                0,
                2,
            ]
        );

        $product = Product::query()->where('name', 'Produto Excel')->sole();
        $this->assertSame($clinic->id, $product->clinic_id);
        $this->assertSame('implementation_excel', $product->lookup_source);

        $this->importExcel(
            $user,
            $clinic,
            'stock',
            'stock_file',
            [
                'ean_gtin_ou_sku',
                'quantidade',
                'custo_unitario',
                'lote',
                'validade',
                'observacoes',
            ],
            [
                '7891000100103',
                5.5,
                12.5,
                'LOTE-XLSX',
                new DateTimeImmutable('2027-12-31'),
                'Entrada Excel',
            ]
        );

        $movement = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->sole();
        $this->assertSame('implementation_excel', $movement->source);
        $this->assertSame('2027-12-31', $movement->expires_at?->toDateString());

        $this->importExcel(
            $user,
            $clinic,
            'financial',
            'financial_file',
            [
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
            [
                'despesa',
                'Conta Excel',
                '52998224725',
                150.75,
                new DateTimeImmutable('2026-08-15'),
                'pendente',
                'pix',
                '',
                'XLSX-001',
                '',
            ]
        );

        $financial = FinancialTransaction::query()
            ->where('description', 'Conta Excel')
            ->sole();
        $this->assertSame($clinic->id, $financial->clinic_id);
        $this->assertSame($supplier->id, $financial->supplier_id);
        $this->assertSame('2026-08-15', $financial->due_date?->toDateString());

        $this->assertSame(6, ImplementationImport::query()->count());
        $this->assertSame(
            6,
            ImplementationImport::query()
                ->where('clinic_id', $clinic->id)
                ->where('user_id', $user->id)
                ->where('data_source', 'excel')
                ->count()
        );
        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('implementation/financial-excel/'.$user->id)
        );
    }

    public function test_invalid_xlsx_is_rejected_without_creating_history(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Inválida', '12345678000191');
        $user = $this->authorizedUser();
        $this->configureImport($user, $clinic);

        $this->post(route('implementation.tutors.upload'), [
            'tutors_file' => UploadedFile::fake()
                ->createWithContent('corrompido.xlsx', 'not-an-xlsx-file'),
        ])
            ->assertRedirect(route('implementation.index', ['step' => 3]))
            ->assertSessionHas('error', 'O arquivo enviado não é uma planilha .xlsx válida.');

        $this->assertDatabaseCount('tutors', 0);
        $this->assertDatabaseCount('implementation_imports', 0);
    }

    public function test_excel_templates_are_available_for_every_import_block(): void
    {
        $user = $this->authorizedUser();

        foreach ([
            'tutors',
            'patients',
            'suppliers',
            'products',
            'stock',
            'financial',
        ] as $template) {
            $response = $this->actingAs($user)
                ->get(route('implementation.templates.excel', $template));

            $response
                ->assertOk()
                ->assertHeader(
                    'content-type',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                );

            $this->assertStringStartsWith('PK', $response->streamedContent());
        }
    }

    /**
     * @param  array<int, null|bool|DateTimeImmutable|float|int|string>  $values
     * @param  array<int, string>  $headers
     */
    private function importExcel(
        User $user,
        Clinic $clinic,
        string $entityType,
        string $inputName,
        array $headers,
        array $values
    ): void {
        $this->configureImport($user, $clinic);

        $this->post(route("implementation.{$entityType}.upload"), [
            $inputName => $this->excelUpload("{$entityType}.xlsx", $headers, $values),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 4]))
            ->assertOk()
            ->assertSee('Primeira aba lida')
            ->assertSee('Dados');

        $analysis = app(ImplementationWorkflowService::class)->analysis();
        $this->assertTrue(
            (bool) ($analysis['can_import'] ?? false),
            json_encode(
                [
                    'file_errors' => $analysis['file_errors'] ?? [],
                    'rows' => $analysis['rows'] ?? [],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        $this->post(route("implementation.{$entityType}.import"))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $this->delete(route('implementation.reset'))
            ->assertRedirect(route('implementation.index'));
    }

    private function configureImport(User $user, Clinic $clinic): void
    {
        $this->actingAs($user)->post(route('implementation.clinic'), [
            'clinic_id' => $clinic->id,
        ])->assertRedirect(route('implementation.index', ['step' => 2]));

        $this->post(route('implementation.source'), [
            'data_source' => 'excel',
        ])->assertRedirect(route('implementation.index', ['step' => 3]));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, null|bool|DateTimeImmutable|float|int|string>  $values
     */
    private function excelUpload(
        string $fileName,
        array $headers,
        array $values
    ): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'vetflow-test-xlsx-');
        $writer = new Writer;

        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Dados');
        $writer->addRow(Row::fromValues($headers));
        $dateStyles = [];

        foreach ($values as $index => $value) {
            if ($value instanceof DateTimeImmutable) {
                $dateStyles[$index] = (new Style)->setFormat('yyyy-mm-dd');
            }
        }

        $writer->addRow(Row::fromValuesWithStyles($values, null, $dateStyles));
        $writer->close();

        $contents = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent(
            $fileName,
            is_string($contents) ? $contents : ''
        );
    }

    private function clinic(string $name, string $cnpj): Clinic
    {
        return Clinic::query()->create([
            'corporate_name' => $name,
            'trade_name' => $name,
            'cnpj' => $cnpj,
            'active' => true,
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'implementation.manage'],
            [
                'name' => 'Gerenciar implantação',
                'description' => 'Gerenciar implantação',
                'group' => 'Tests',
                'active' => true,
            ]
        );
        $role = Role::query()->create([
            'name' => 'Implantação '.Str::random(6),
            'slug' => 'implementation-'.Str::lower(Str::random(8)),
            'description' => 'Test role',
            'system' => false,
            'active' => true,
        ]);

        $role->permissions()->attach($permission->id);

        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
