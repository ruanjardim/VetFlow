<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Products\Models\Product;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImplementationCatalogStockCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_suppliers_products_and_initial_stock_in_sequence(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Central', '12345678000190');
        $user = $this->authorizedUser();

        $this->configureCsvImport($user, $clinic);
        $supplierCsv = implode("\n", [
            'nome;cpf_cnpj;telefone;email;cidade;estado;observacoes',
            'Distribuidora Vet;529.982.247-25;2133334444;contato@vet.test;Niterói;rj;Preferencial',
        ]);

        $this->post(route('implementation.suppliers.upload'), [
            'suppliers_file' => UploadedFile::fake()
                ->createWithContent('fornecedores.csv', $supplierCsv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Distribuidora Vet')
            ->assertSee('52998224725');

        $this->post(route('implementation.suppliers.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $supplier = Supplier::query()->sole();
        $this->assertSame($clinic->id, $supplier->clinic_id);
        $this->assertSame('52998224725', $supplier->document);
        $this->assertSame('RJ', $supplier->state);

        $this->configureCsvImport($user, $clinic);
        $productCsv = implode("\n", [
            'nome;ean_gtin;sku;categoria;fornecedor_documento;custo;preco_venda;estoque_atual;estoque_minimo',
            'Ração Premium;7891000100103;RACAO-001;Alimentos;529.982.247-25;12,50;20,90;5,500;2',
        ]);

        $this->post(route('implementation.products.upload'), [
            'products_file' => UploadedFile::fake()
                ->createWithContent('produtos.csv', $productCsv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Ração Premium')
            ->assertSee('Distribuidora Vet')
            ->assertSee('7891000100103');

        $this->post(route('implementation.products.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $product = Product::query()->sole();
        $this->assertSame($clinic->id, $product->clinic_id);
        $this->assertSame('RACAO-001', $product->sku);
        $this->assertSame(5.5, (float) $product->stock_quantity);
        $this->assertSame(
            '52998224725',
            $product->lookup_metadata['supplier_document']
        );
        $this->assertSame(
            'Distribuidora Vet',
            $product->lookup_metadata['supplier_name']
        );
        $this->assertSame('implementation_csv', $product->lookup_source);

        $initialMovement = InventoryMovement::query()->sole();
        $this->assertSame('entry', $initialMovement->type);
        $this->assertSame('implementation_csv', $initialMovement->source);
        $this->assertSame(0.0, (float) $initialMovement->balance_before);
        $this->assertSame(5.5, (float) $initialMovement->balance_after);

        $this->configureCsvImport($user, $clinic);
        $stockCsv = implode("\n", [
            'ean_gtin_ou_sku;quantidade;custo_unitario;lote;validade;observacoes',
            'RACAO-001;2,250;12,75;LOTE-2027;31/12/2027;Conferido',
        ]);

        $this->post(route('implementation.stock.upload'), [
            'stock_file' => UploadedFile::fake()
                ->createWithContent('estoque.csv', $stockCsv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Ração Premium')
            ->assertSee('LOTE-2027')
            ->assertSee('2.250');

        $this->post(route('implementation.stock.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $this->assertSame(7.75, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);

        $stockMovement = InventoryMovement::query()
            ->where('lot_number', 'LOTE-2027')
            ->sole();
        $this->assertSame(2.25, (float) $stockMovement->quantity);
        $this->assertSame(12.75, (float) $stockMovement->unit_cost);
        $this->assertSame('2027-12-31', $stockMovement->expires_at?->toDateString());
        $this->assertSame(5.5, (float) $stockMovement->balance_before);
        $this->assertSame(7.75, (float) $stockMovement->balance_after);
    }

    public function test_supplier_csv_blocks_invalid_document_email_and_state(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Norte', '12345678000191');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'nome,cpf_cnpj,telefone,email,cidade,estado,observacoes',
            ',00000000000,,email-invalido,Niterói,RIO,',
        ]);

        $this->post(route('implementation.suppliers.upload'), [
            'suppliers_file' => UploadedFile::fake()
                ->createWithContent('fornecedores.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Informe o nome do fornecedor.')
            ->assertSee('Informe um CPF ou CNPJ válido')
            ->assertSee('Informe um e-mail válido.')
            ->assertSee('Informe a UF com duas letras.');

        $this->post(route('implementation.suppliers.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_product_csv_blocks_cross_clinic_supplier_and_existing_identifier(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Sul', '12345678000192');
        $otherClinic = $this->clinic('Clínica Fornecedora', '12345678000195');
        Supplier::query()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Fornecedor externo',
            'document' => '52998224725',
            'active' => true,
        ]);
        Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto existente',
            'gtin' => '7891000100103',
            'barcode' => '7891000100103',
            'sku' => 'SKU-EXISTENTE',
            'active' => true,
        ]);
        $user = $this->authorizedUser($clinic);
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'nome;ean_gtin;sku;categoria;fornecedor_documento;custo;preco_venda;estoque_atual;estoque_minimo',
            'Duplicado;7891000100103;NOVO;Categoria;52998224725;custo;10;0;0',
        ]);

        $this->post(route('implementation.products.upload'), [
            'products_file' => UploadedFile::fake()
                ->createWithContent('produtos.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Já existe um produto com este EAN/GTIN')
            ->assertSee('Nenhum fornecedor com este CPF/CNPJ')
            ->assertSee('Informe um custo válido.');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_stock_csv_cannot_resolve_product_from_another_clinic(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Própria', '12345678000193');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000194');
        Product::query()->create([
            'clinic_id' => $otherClinic->id,
            'name' => 'Produto externo',
            'gtin' => '7891000100103',
            'barcode' => '7891000100103',
            'sku' => 'EXTERNO-001',
            'active' => true,
        ]);
        $user = $this->authorizedUser($clinic);
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'ean_gtin_ou_sku,quantidade,custo_unitario,lote,validade,observacoes',
            'EXTERNO-001,0,10,L1,data-invalida,',
        ]);

        $this->post(route('implementation.stock.upload'), [
            'stock_file' => UploadedFile::fake()
                ->createWithContent('estoque.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Nenhum produto ativo com este EAN/GTIN ou SKU')
            ->assertSee('A quantidade deve ser maior que zero.')
            ->assertSee('Informe a validade em DD/MM/AAAA ou AAAA-MM-DD.');

        $this->post(route('implementation.stock.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(0.0, (float) Product::query()
            ->withoutGlobalScopes()
            ->where('sku', 'EXTERNO-001')
            ->value('stock_quantity'));
    }

    public function test_catalog_and_stock_templates_are_available(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->get(route('implementation.templates', 'suppliers'))
            ->assertOk()
            ->assertSee('nome,cpf_cnpj,telefone,email,cidade,estado,observacoes', false);

        $this->get(route('implementation.templates', 'products'))
            ->assertOk()
            ->assertSee(
                'nome,ean_gtin,sku,categoria,fornecedor_documento,custo,preco_venda,estoque_atual,estoque_minimo',
                false
            );

        $this->get(route('implementation.templates', 'stock'))
            ->assertOk()
            ->assertSee(
                'ean_gtin_ou_sku,quantidade,custo_unitario,lote,validade,observacoes',
                false
            );
    }

    private function configureCsvImport(User $user, Clinic $clinic): void
    {
        $this->actingAs($user)->post(route('implementation.clinic'), [
            'clinic_id' => $clinic->id,
        ]);

        $this->post(route('implementation.source'), [
            'data_source' => 'csv',
        ]);
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

    private function authorizedUser(?Clinic $clinic = null): User
    {
        $user = User::factory()->create([
            'active' => true,
            'clinic_id' => $clinic?->id,
        ]);
        $permission = Permission::query()->create([
            'name' => 'Gerenciar implantação',
            'slug' => 'implementation.manage',
            'description' => 'Gerenciar implantação',
            'group' => 'Tests',
            'active' => true,
        ]);
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
