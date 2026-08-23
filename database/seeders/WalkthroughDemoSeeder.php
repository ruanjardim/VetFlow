<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Implementation\Models\ImplementationImport;
use App\Modules\Implementation\Models\ImplementationPilotCheck;
use App\Modules\Implementation\Models\ImplementationPilotRelease;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Patients\Models\Patient;
use App\Modules\ProductIntelligence\Models\GlobalProduct;
use App\Modules\ProductIntelligence\Models\GlobalProductSource;
use App\Modules\ProductIntelligence\Models\GlobalProductSuggestion;
use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tutors\Models\Tutor;
use App\Support\Demo\WalkthroughDemoFixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WalkthroughDemoSeeder extends Seeder
{
    public const DEMO_EMAIL = WalkthroughDemoFixture::USER_EMAIL;

    public const DEMO_PASSWORD = 'VetFlowDemo123!';

    public function run(): void
    {
        $this->call(AuthorizationSeeder::class);

        DB::transaction(function (): void {
            $clinic = $this->seedClinic();
            $user = $this->seedUser($clinic);
            $this->seedTutorJourney($clinic);
            $this->seedSupplier($clinic);
            $products = $this->seedProductIntelligence($clinic);
            $this->seedInventory($clinic, $products);
            $this->seedCommercialFlow($clinic, $user, $products);
            $this->seedImplementationPilot($clinic, $user);
        });
    }

    private function seedClinic(): Clinic
    {
        return Clinic::query()->updateOrCreate(
            ['cnpj' => WalkthroughDemoFixture::CLINIC_CNPJ],
            [
                'corporate_name' => 'VetFlow Demo Clinica Veterinaria LTDA',
                'trade_name' => 'VetFlow Demo Clinic',
                'crmv' => 'CRMV-SP 12345',
                'technical_manager' => 'Dra. Helena Prado',
                'email' => 'demo@vetflow.local',
                'phone' => '(11) 4002-8922',
                'whatsapp' => '(11) 94002-8922',
                'website' => 'https://vetflow.local',
                'zip_code' => '01001-000',
                'state' => 'SP',
                'city' => 'Sao Paulo',
                'district' => 'Centro',
                'street' => 'Rua Demo VetFlow',
                'number' => '100',
                'timezone' => 'America/Sao_Paulo',
                'currency' => 'BRL',
                'language' => 'pt_BR',
                'active' => true,
            ]
        );
    }

    private function seedUser(Clinic $clinic): User
    {
        $user = User::query()
            ->withTrashed()
            ->firstOrNew(['email' => self::DEMO_EMAIL]);

        $user->fill([
            'clinic_id' => $clinic->id,
            'name' => 'Admin Walkthrough',
            'email' => self::DEMO_EMAIL,
            'position' => 'Administracao',
            'password' => Hash::make(self::DEMO_PASSWORD),
            'active' => true,
        ]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $adminRole = Role::query()
            ->whereNull('clinic_id')
            ->where('slug', 'administrador')
            ->firstOrFail();

        $existingRole = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $adminRole->id)
            ->first();

        if ($existingRole) {
            DB::table('user_roles')
                ->where('id', $existingRole->id)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('user_roles')->insert([
                'ulid' => (string) Str::ulid(),
                'user_id' => $user->id,
                'role_id' => $adminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function seedTutorJourney(Clinic $clinic): void
    {
        $tutor = Tutor::query()->updateOrCreate(
            ['email' => WalkthroughDemoFixture::TUTOR_EMAIL],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Mariana Alves',
                'cpf' => '12345678909',
                'phone' => '(11) 98888-0001',
                'phone_secondary' => '(11) 97777-0001',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'notes' => 'Tutor ficticio para walkthrough publico.',
                'active' => true,
            ]
        );

        $patient = Patient::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'name' => WalkthroughDemoFixture::PATIENT_NAME,
            ],
            [
                'tutor_id' => $tutor->id,
                'species' => 'Canino',
                'breed' => 'Spitz Alemao',
                'gender' => 'Femea',
                'birth_date' => today()->subYears(3)->subMonths(2),
                'weight' => 4.80,
                'notes' => 'Paciente ficticio para demonstracao.',
            ]
        );

        Appointment::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'title' => WalkthroughDemoFixture::APPOINTMENT_TITLE,
            ],
            [
                'patient_id' => $patient->id,
                'tutor_id' => $tutor->id,
                'description' => 'Revisao pos-vacina e orientacao nutricional.',
                'scheduled_at' => now()->addHours(3)->minute(0),
                'status' => 'scheduled',
            ]
        );
    }

    private function seedSupplier(Clinic $clinic): void
    {
        Supplier::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'document' => WalkthroughDemoFixture::SUPPLIER_DOCUMENT,
            ],
            [
                'name' => 'Distribuidora Saude Animal Demo',
                'contact_name' => 'Paula Ribeiro',
                'email' => 'fornecedor.demo@vetflow.local',
                'phone' => '(11) 4002-8901',
                'whatsapp' => '(11) 94002-8901',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'notes' => 'Fornecedor ficticio para validacao do piloto.',
                'active' => true,
            ]
        );
    }

    /**
     * @return array<string, Product>
     */
    private function seedProductIntelligence(Clinic $clinic): array
    {
        $globalFood = GlobalProduct::query()->updateOrCreate(
            ['gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0]],
            [
                'ean' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0],
                'barcode' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0],
                'name' => 'Racao Premium Filhotes 3kg',
                'brand' => 'VetNutrition',
                'manufacturer' => 'VetNutrition Brasil',
                'category' => 'Alimentos',
                'subcategory' => 'Racoes',
                'description' => 'Produto ficticio para demonstracao do Catalogo Global.',
                'weight' => '3kg',
                'unit' => 'un',
                'species' => 'Canino',
                'api_source' => WalkthroughDemoFixture::SOURCE,
                'source_confidence' => 94,
                'status' => GlobalProduct::STATUS_VERIFIED,
                'metadata' => ['demo' => true],
                'last_lookup_at' => now()->subDay(),
            ]
        );

        GlobalProductSource::query()->updateOrCreate(
            [
                'global_product_id' => $globalFood->id,
                'source_name' => WalkthroughDemoFixture::SOURCE,
            ],
            [
                'source_label' => 'Base demonstrativa',
                'source_type' => 'internal',
                'confidence' => 94,
                'status' => GlobalProduct::STATUS_VERIFIED,
                'queried_at' => now()->subDay(),
                'payload' => ['demo' => true],
            ]
        );

        $globalMedicine = GlobalProduct::query()->updateOrCreate(
            ['gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1]],
            [
                'ean' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1],
                'barcode' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1],
                'name' => 'Vermifugo Pet 10kg',
                'brand' => 'SaudeVet',
                'manufacturer' => 'SaudeVet Laboratorios',
                'category' => 'Medicamentos',
                'subcategory' => 'Vermifugos',
                'description' => 'Produto ficticio pendente de validacao.',
                'species' => 'Canino',
                'active_ingredient' => 'Praziquantel demo',
                'prescription_required' => false,
                'api_source' => WalkthroughDemoFixture::SOURCE,
                'source_confidence' => 72,
                'status' => GlobalProduct::STATUS_PENDING,
                'metadata' => ['demo' => true],
                'last_lookup_at' => now()->subDays(3),
            ]
        );

        GlobalProductSuggestion::query()->updateOrCreate(
            [
                'gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1],
                'suggestion_type' => 'enrichment',
            ],
            [
                'suggested_name' => 'Vermifugo Pet 10kg - sugestao de enriquecimento',
                'source_name' => WalkthroughDemoFixture::SOURCE,
                'status' => GlobalProduct::STATUS_PENDING,
                'confidence' => 72,
                'payload' => ['demo' => true],
            ]
        );

        $food = Product::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'sku' => WalkthroughDemoFixture::PRODUCT_SKUS[0],
            ],
            [
                'global_product_id' => $globalFood->id,
                'name' => 'Racao Premium Filhotes 3kg',
                'category' => 'Alimentos',
                'brand' => 'VetNutrition',
                'manufacturer' => 'VetNutrition Brasil',
                'barcode' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0],
                'gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[0],
                'description' => 'Item demonstrativo vinculado ao Catalogo Global.',
                'cost_price' => 62.50,
                'sale_price' => 99.90,
                'stock_quantity' => 3,
                'minimum_stock' => 8,
                'unit' => 'un',
                'lookup_source' => WalkthroughDemoFixture::SOURCE,
                'lookup_metadata' => ['confidence' => 94],
                'looked_up_at' => now()->subDay(),
                'active' => true,
            ]
        );

        $medicine = Product::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'sku' => WalkthroughDemoFixture::PRODUCT_SKUS[1],
            ],
            [
                'global_product_id' => $globalMedicine->id,
                'name' => 'Vermifugo Pet 10kg',
                'category' => 'Medicamentos',
                'brand' => 'SaudeVet',
                'manufacturer' => 'SaudeVet Laboratorios',
                'barcode' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1],
                'gtin' => WalkthroughDemoFixture::GLOBAL_PRODUCT_GTINS[1],
                'cost_price' => 18.40,
                'sale_price' => 34.90,
                'stock_quantity' => 14,
                'minimum_stock' => 5,
                'unit' => 'cx',
                'lookup_source' => WalkthroughDemoFixture::SOURCE,
                'lookup_metadata' => ['confidence' => 72],
                'looked_up_at' => now()->subDays(3),
                'active' => true,
            ]
        );

        $shampoo = Product::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'sku' => WalkthroughDemoFixture::PRODUCT_SKUS[2],
            ],
            [
                'name' => 'Shampoo Neutro Pet 500ml',
                'category' => 'Higiene',
                'brand' => 'BanhoBom',
                'manufacturer' => 'BanhoBom Pet Care',
                'barcode' => '7891000300305',
                'gtin' => '7891000300305',
                'cost_price' => 9.90,
                'sale_price' => 0,
                'stock_quantity' => 12,
                'minimum_stock' => 4,
                'unit' => 'un',
                'active' => true,
            ]
        );

        return [
            'food' => $food,
            'medicine' => $medicine,
            'shampoo' => $shampoo,
        ];
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedInventory(Clinic $clinic, array $products): void
    {
        $this->movement($clinic, $products['food'], 'entry', 3, 'DEMO-FOOD-EXP', today()->subDays(5), 'Lote vencido para alerta critico.');
        $this->movement($clinic, $products['medicine'], 'entry', 8, 'DEMO-MED-30D', today()->addDays(12), 'Lote proximo de vencer para alerta de atencao.');
        $this->movement($clinic, $products['shampoo'], 'entry', 5, null, null, 'Estoque sem lote para demonstracao.');
    }

    private function movement(Clinic $clinic, Product $product, string $type, float $quantity, ?string $lotNumber, mixed $expiresAt, string $notes): void
    {
        InventoryMovement::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'product_id' => $product->id,
                'lot_number' => $lotNumber,
                'reason' => 'Walkthrough demo',
            ],
            [
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $product->cost_price,
                'balance_before' => 0,
                'balance_after' => $product->stock_quantity,
                'expires_at' => $expiresAt,
                'occurred_at' => now()->subHours(5),
                'source' => WalkthroughDemoFixture::SOURCE,
                'notes' => $notes,
                'metadata' => ['demo' => true],
            ]
        );
    }

    /**
     * @param  array<string, Product>  $products
     */
    private function seedCommercialFlow(Clinic $clinic, User $user, array $products): void
    {
        $tutor = Tutor::query()->where('email', WalkthroughDemoFixture::TUTOR_EMAIL)->firstOrFail();
        $patient = Patient::query()
            ->where('clinic_id', $clinic->id)
            ->where('name', WalkthroughDemoFixture::PATIENT_NAME)
            ->firstOrFail();

        $financial = FinancialTransaction::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'reference' => WalkthroughDemoFixture::FINANCIAL_REFERENCES[0],
            ],
            [
                'type' => 'income',
                'description' => 'Venda PDV walkthrough',
                'amount' => 134.80,
                'due_date' => today(),
                'paid_at' => now()->subHours(1),
                'status' => 'paid',
                'payment_method' => 'pix',
                'installment_number' => 1,
                'installment_total' => 1,
                'notes' => 'Lancamento ficticio criado pelo WalkthroughDemoSeeder.',
            ]
        );

        $sale = Sale::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'code' => WalkthroughDemoFixture::SALE_CODE,
            ],
            [
                'tutor_id' => $tutor->id,
                'patient_id' => $patient->id,
                'financial_transaction_id' => $financial->id,
                'seller_user_id' => $user->id,
                'source' => 'pdv',
                'status' => 'completed',
                'payment_status' => 'paid',
                'sold_at' => now()->subHours(1),
                'completed_at' => now()->subHours(1),
                'subtotal' => 134.80,
                'discount_total' => 0,
                'additions_total' => 0,
                'total' => 134.80,
                'paid_total' => 134.80,
                'change_total' => 0,
                'cost_total' => 80.90,
                'gross_profit_total' => 53.90,
                'gross_margin_percent' => 39.99,
                'stock_applied' => true,
                'financial_applied' => true,
                'notes' => 'Venda ficticia para walkthrough publico.',
                'metadata' => ['demo' => true],
            ]
        );

        SaleItem::query()->updateOrCreate(
            [
                'sale_id' => $sale->id,
                'product_id' => $products['food']->id,
            ],
            [
                'type' => 'product',
                'description' => $products['food']->name,
                'barcode' => $products['food']->barcode,
                'sku' => $products['food']->sku,
                'product_name_snapshot' => $products['food']->name,
                'brand_snapshot' => $products['food']->brand,
                'category_snapshot' => $products['food']->category,
                'manufacturer_snapshot' => $products['food']->manufacturer,
                'unit_snapshot' => $products['food']->unit,
                'quantity' => 1,
                'unit_price' => 99.90,
                'cost_unit_price' => 62.50,
                'original_unit_price' => 99.90,
                'discount_total' => 0,
                'gross_total' => 99.90,
                'net_total' => 99.90,
                'gross_profit_total' => 37.40,
                'gross_margin_percent' => 37.44,
                'total' => 99.90,
                'metadata' => ['demo' => true],
            ]
        );

        SaleItem::query()->updateOrCreate(
            [
                'sale_id' => $sale->id,
                'product_id' => $products['medicine']->id,
            ],
            [
                'type' => 'product',
                'description' => $products['medicine']->name,
                'barcode' => $products['medicine']->barcode,
                'sku' => $products['medicine']->sku,
                'product_name_snapshot' => $products['medicine']->name,
                'brand_snapshot' => $products['medicine']->brand,
                'category_snapshot' => $products['medicine']->category,
                'manufacturer_snapshot' => $products['medicine']->manufacturer,
                'unit_snapshot' => $products['medicine']->unit,
                'quantity' => 1,
                'unit_price' => 34.90,
                'cost_unit_price' => 18.40,
                'original_unit_price' => 34.90,
                'discount_total' => 0,
                'gross_total' => 34.90,
                'net_total' => 34.90,
                'gross_profit_total' => 16.50,
                'gross_margin_percent' => 47.28,
                'total' => 34.90,
                'metadata' => ['demo' => true],
            ]
        );

        SalePayment::query()->updateOrCreate(
            [
                'sale_id' => $sale->id,
                'reference' => WalkthroughDemoFixture::SALE_PAYMENT_REFERENCE,
            ],
            [
                'method' => 'pix',
                'amount' => 134.80,
                'installments' => 1,
                'paid_at' => now()->subHours(1),
                'transaction_reference' => 'DEMO-TX-0001',
                'status' => 'paid',
                'notes' => 'Pagamento ficticio.',
            ]
        );

        FinancialTransaction::query()->updateOrCreate(
            [
                'clinic_id' => $clinic->id,
                'reference' => WalkthroughDemoFixture::FINANCIAL_REFERENCES[1],
            ],
            [
                'type' => 'expense',
                'description' => 'Compra de insumos veterinarios',
                'amount' => 420.00,
                'due_date' => today()->addDays(3),
                'status' => 'pending',
                'payment_method' => 'bank_slip',
                'installment_number' => 1,
                'installment_total' => 1,
                'notes' => 'Conta a pagar ficticia para fluxo de caixa.',
            ]
        );
    }

    private function seedImplementationPilot(Clinic $clinic, User $user): void
    {
        $imports = [
            'tutors' => ['Responsáveis', 1],
            'patients' => ['Pacientes', 1],
            'suppliers' => ['Fornecedores', 1],
            'products' => ['Produtos', 3],
            'stock' => ['Estoque inicial', 3],
            'financial' => ['Financeiro', 2],
        ];

        foreach ($imports as $type => [$label, $count]) {
            ImplementationImport::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'entity_type' => $type,
                    'file_name' => WalkthroughDemoFixture::IMPLEMENTATION_IMPORT_FILES[$type],
                ],
                [
                    'user_id' => $user->id,
                    'clinic_name' => $clinic->trade_name,
                    'user_name' => $user->name,
                    'entity_label' => $label,
                    'data_source' => 'csv',
                    'total_rows' => $count,
                    'imported_count' => $count,
                    'invalid_rows' => 0,
                    'completed_at' => now()->subDay(),
                ]
            );
        }

        $checks = [
            'data_reviewed' => 'Dados importados revisados',
            'access_validated' => 'Acessos da equipe validados',
            'training_completed' => 'Treinamento operacional realizado',
        ];

        foreach ($checks as $key => $label) {
            ImplementationPilotCheck::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'check_key' => $key,
                    'notes' => WalkthroughDemoFixture::IMPLEMENTATION_NOTE,
                ],
                [
                    'user_id' => $user->id,
                    'clinic_name' => $clinic->trade_name,
                    'user_name' => $user->name,
                    'check_label' => $label,
                    'completed' => true,
                    'decided_at' => now()->subHours(12),
                ]
            );
        }

        $release = ImplementationPilotRelease::query()->firstOrNew([
            'clinic_id' => $clinic->id,
            'release_notes' => WalkthroughDemoFixture::PILOT_RELEASE_NOTES,
        ]);

        if (! $release->exists) {
            $release->revision = ((int) ImplementationPilotRelease::query()
                ->where('clinic_id', $clinic->id)
                ->max('revision')) + 1;
        }

        $release->fill([
            'user_id' => $user->id,
            'clinic_name' => $clinic->trade_name,
            'user_name' => $user->name,
            'release_owner' => 'Admin Walkthrough',
            'support_owner' => 'Suporte VetFlow Demo',
            'planned_start_date' => today()->addWeek(),
            'scope' => 'Cadastros, agenda, estoque, venda e financeiro com dados ficticios.',
            'recorded_at' => now()->subHours(6),
        ])->save();
    }
}
