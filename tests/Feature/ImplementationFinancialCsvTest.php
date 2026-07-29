<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Financial\Models\FinancialTransaction;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImplementationFinancialCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_import_paid_and_pending_financial_transactions(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Central', '12345678000190');
        $supplier = $this->supplier(
            $clinic,
            'Distribuidora Vet',
            '52998224725'
        );
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tipo;descricao;pessoa_documento;valor;vencimento;status;forma_pagamento;data_pagamento;referencia;observacoes',
            'saida;Compra mensal;529.982.247-25;150,75;31/07/2026;pago;pix;29/07/2026;NF-100;Quitada',
            'receita;Mensalidade;;2000,00;10/08/2026;pendente;boleto;;;',
        ]);

        $this->post(route('implementation.financial.upload'), [
            'financial_file' => UploadedFile::fake()
                ->createWithContent('financeiro.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 4]))
            ->assertOk()
            ->assertSee('Data de pagamento')
            ->assertSee('CPF/CNPJ do fornecedor')
            ->assertSee('Detectada');

        $this->get(route('implementation.index', ['step' => 6]))
            ->assertOk()
            ->assertSee('Compra mensal')
            ->assertSee('Distribuidora Vet')
            ->assertSee('Saída')
            ->assertSee('Mensalidade')
            ->assertSee('Entrada')
            ->assertSee('Pendente');

        $this->post(route('implementation.financial.import'))
            ->assertRedirect(route('implementation.index', ['step' => 8]));

        $expense = FinancialTransaction::query()
            ->where('description', 'Compra mensal')
            ->sole();
        $this->assertSame($clinic->id, $expense->clinic_id);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame('expense', $expense->type);
        $this->assertSame('paid', $expense->status);
        $this->assertSame(150.75, (float) $expense->amount);
        $this->assertSame('2026-07-31', $expense->due_date?->toDateString());
        $this->assertSame('2026-07-29', $expense->paid_at?->toDateString());
        $this->assertSame('pix', $expense->payment_method);
        $this->assertSame('NF-100', $expense->reference);
        $this->assertSame('Quitada', $expense->notes);

        $income = FinancialTransaction::query()
            ->where('description', 'Mensalidade')
            ->sole();
        $this->assertNull($income->supplier_id);
        $this->assertSame('income', $income->type);
        $this->assertSame('pending', $income->status);
        $this->assertSame(2000.0, (float) $income->amount);
        $this->assertNull($income->paid_at);
        $this->assertSame('bank_slip', $income->payment_method);

        $this->get(route('implementation.index', ['step' => 8]))
            ->assertOk()
            ->assertSee('Importação concluída')
            ->assertSee('Financeiro')
            ->assertSee('2');
    }

    public function test_financial_csv_blocks_invalid_values_and_paid_without_payment_date(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Norte', '12345678000191');
        $user = $this->authorizedUser();
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tipo;descricao;pessoa_documento;valor;vencimento;status;forma_pagamento;data_pagamento;referencia;observacoes',
            'desconhecido;;00000000000;-1;31/02/2026;qualquer;cheque;data-invalida;;',
            'entrada;Recebimento;;100;01/08/2026;pago;pix;;;',
            'despesa;Conta pendente;;50;01/08/2026;pendente;dinheiro;29/07/2026;;',
        ]);

        $this->post(route('implementation.financial.upload'), [
            'financial_file' => UploadedFile::fake()
                ->createWithContent('financeiro.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Use entrada/receita ou saída/despesa no tipo.')
            ->assertSee('Informe a descrição do lançamento.')
            ->assertSee('Informe um CPF ou CNPJ válido')
            ->assertSee('O valor não pode ser negativo.')
            ->assertSee('Informe o vencimento em DD/MM/AAAA ou AAAA-MM-DD.')
            ->assertSee('Use pendente, pago, cancelado ou vencido no status.')
            ->assertSee('Informe uma forma de pagamento válida.')
            ->assertSee('Informe a data de pagamento em DD/MM/AAAA ou AAAA-MM-DD.')
            ->assertSee('Informe a data de pagamento para lançamentos pagos.')
            ->assertSee('A data de pagamento deve ficar vazia para lançamentos não pagos.');

        $this->post(route('implementation.financial.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_financial_csv_cannot_link_supplier_from_another_clinic(): void
    {
        Storage::fake('local');

        $clinic = $this->clinic('Clínica Própria', '12345678000192');
        $otherClinic = $this->clinic('Clínica Externa', '12345678000193');
        $this->supplier($otherClinic, 'Fornecedor externo', '52998224725');
        $user = $this->authorizedUser($clinic);
        $this->configureCsvImport($user, $clinic);

        $csv = implode("\n", [
            'tipo,descricao,pessoa_documento,valor,vencimento,status,forma_pagamento,data_pagamento,referencia,observacoes',
            'despesa,Conta externa,52998224725,100,2026-08-01,pendente,boleto,,,',
        ]);

        $this->post(route('implementation.financial.upload'), [
            'financial_file' => UploadedFile::fake()
                ->createWithContent('financeiro.csv', $csv),
        ])->assertRedirect(route('implementation.index', ['step' => 4]));

        $this->get(route('implementation.index', ['step' => 5]))
            ->assertOk()
            ->assertSee('Nenhum fornecedor com este CPF/CNPJ foi encontrado');

        $this->post(route('implementation.financial.import'))
            ->assertRedirect(route('implementation.index', ['step' => 5]));

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_financial_template_is_available(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->get(route('implementation.templates', 'financial'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee(
                'tipo,descricao,pessoa_documento,valor,vencimento,status,forma_pagamento,data_pagamento,referencia,observacoes',
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

    private function supplier(Clinic $clinic, string $name, string $document): Supplier
    {
        return Supplier::query()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'document' => $document,
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
