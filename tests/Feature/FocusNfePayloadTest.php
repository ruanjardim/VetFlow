<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDpsSequence;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FocusNfePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_focus_payload_is_generated_once_and_reserves_the_dps_number_without_transmission(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        Http::preventStrayRequests();
        $sequence = $this->sequence($clinic, 500);
        $document = $this->document($clinic, $user);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $document->refresh();
        $payload = data_get($document->request_payload, 'focus_nfe.payload');
        $this->assertSame('1', $document->series);
        $this->assertSame('500', $document->document_number);
        $this->assertSame('vf-'.$document->ulid, $document->provider_reference);
        $this->assertSame(1, $payload['serie_dps']);
        $this->assertSame(500, $payload['numero_dps']);
        $this->assertSame('00000000000911', $payload['cnpj_prestador']);
        $this->assertSame('12345678909', $payload['cpf_tomador']);
        $this->assertSame('050101', $payload['codigo_tributacao_nacional_iss']);
        $this->assertSame('100.00', $payload['valor_servico']);
        $this->assertSame('10.00', $payload['desconto_incondicionado']);
        $this->assertSame('1', $payload['tributacao_iss']);
        $this->assertSame('1', $payload['consumidor_final']);
        $this->assertSame('000', $payload['ibs_cbs_situacao_tributaria']);
        $this->assertSame('000001', $payload['ibs_cbs_classificacao_tributaria']);
        $this->assertSame('0', $payload['finalidade_emissao']);
        $this->assertSame('0', $payload['indicador_destinatario']);
        $this->assertSame(501, $sequence->fresh()->next_number);
        $this->assertSame(500, $sequence->fresh()->last_reserved_number);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'event' => 'payload_generated',
            'from_status' => 'draft',
            'to_status' => 'draft',
        ]);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(501, $sequence->fresh()->next_number);
        $this->assertDatabaseCount('fiscal_document_events', 1);
        Http::assertNothingSent();
        $this->get(route('fiscal.documents.show', $document->id))
            ->assertOk()
            ->assertSee('Payload Focus NFe')
            ->assertSee('focus-nfsen-2026-10')
            ->assertSee('Nenhuma transmissão externa', false);
    }

    public function test_payload_generation_fails_closed_before_reserving_a_number_when_data_is_missing(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $sequence = $this->sequence($clinic, 700);
        $document = $this->document($clinic, $user, [
            'recipient_snapshot' => null,
        ]);

        $this->from(route('fiscal.documents.show', $document->id))
            ->post(route('fiscal.documents.payload', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHasErrors('payload');

        $document->refresh();
        $this->assertNull($document->request_payload);
        $this->assertNull($document->document_number);
        $this->assertSame(700, $sequence->fresh()->next_number);
        $this->assertDatabaseCount('fiscal_document_events', 0);
    }

    public function test_nfse_payload_consolidates_approved_addition_and_discount_allocation(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $this->sequence($clinic, 800);
        $document = $this->document($clinic, $user, [
            'items_snapshot' => [[
                'source_id' => 1,
                'type' => 'service',
                'service_id' => 1,
                'description' => 'Consulta veterinária',
                'quantity' => '1.000',
                'unit_price' => '100.00',
                'line_discount_total' => '10.00',
                'allocated_sale_discount' => '5.00',
                'allocated_additions_total' => '2.00',
                'allocated_delivery_fee' => '0.00',
                'discount_total' => '15.00',
                'total' => '87.00',
                'fiscal_profile_version' => 1,
                'fiscal' => [
                    'national_taxation_code' => '050101',
                    'municipal_taxation_code' => null,
                    'service_list_item' => '05.01',
                    'cnae' => '7500100',
                    'municipality_code' => '3303302',
                    'iss_rate' => '2.0000',
                    'iss_withheld' => false,
                    'iss_taxation' => '1',
                    'ibs_cbs_tax_status' => '000',
                    'ibs_cbs_classification_code' => '000001',
                    'operation_indicator' => null,
                ],
            ]],
            'totals_snapshot' => [
                'total' => '87.00',
                'additions_total' => '2.00',
                'delivery_fee' => '0.00',
                'competence_date' => '2026-10-09',
                'requires_allocation' => 'no',
            ],
        ]);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertSessionDoesntHaveErrors();

        $payload = data_get($document->fresh()->request_payload, 'focus_nfe.payload');
        $this->assertSame('102.00', $payload['valor_servico']);
        $this->assertSame('15.00', $payload['desconto_incondicionado']);
        $this->assertStringContainsString('acréscimos rateados R$ 2,00', $payload['descricao_servico']);
    }

    /** @return array{Clinic, User} */
    private function context(): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Payload Niterói',
            'trade_name' => 'Clínica Payload',
            'cnpj' => '00000000000911',
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal Payload',
            'slug' => 'fiscal-payload-'.Str::lower(Str::random(8)),
            'description' => 'Teste',
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

        return [$clinic, $user];
    }

    private function sequence(Clinic $clinic, int $nextNumber): FiscalDpsSequence
    {
        return FiscalDpsSequence::query()->create([
            'clinic_id' => $clinic->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'document_type' => 'nfse',
            'environment' => 'homologation',
            'series' => '1',
            'next_number' => $nextNumber,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function document(Clinic $clinic, User $user, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::query()->create(array_replace([
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'type' => 'nfse',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'status' => 'draft',
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 2,
            'issuer_snapshot' => [
                'corporate_name' => 'Clínica Payload Niterói',
                'document' => '00000000000911',
                'municipal_registration' => '12345',
                'municipality_code' => '3303302',
                'focus_nfe' => [
                    'dps_series' => '1',
                    'simple_national_option' => '3',
                    'simple_national_tax_regime' => '1',
                    'special_tax_regime' => '0',
                ],
            ],
            'recipient_snapshot' => [
                'name' => 'Responsável Teste',
                'document' => '12345678909',
                'phone' => '(21) 99999-0000',
                'email' => 'responsavel@example.test',
                'consumer_final' => true,
                'sale_type' => 'in_store',
                'address' => [
                    'zip_code' => '24000-000',
                    'street' => 'Rua de Teste',
                    'number' => '100',
                    'district' => 'Centro',
                ],
            ],
            'items_snapshot' => [[
                'source_id' => 1,
                'type' => 'service',
                'service_id' => 1,
                'description' => 'Consulta veterinária',
                'quantity' => '1.000',
                'unit_price' => '100.00',
                'discount_total' => '10.00',
                'total' => '90.00',
                'fiscal_profile_version' => 1,
                'fiscal' => [
                    'national_taxation_code' => '050101',
                    'municipal_taxation_code' => null,
                    'service_list_item' => '05.01',
                    'cnae' => '7500100',
                    'municipality_code' => '3303302',
                    'iss_rate' => '2.0000',
                    'iss_withheld' => false,
                    'iss_taxation' => '1',
                    'ibs_cbs_tax_status' => '000',
                    'ibs_cbs_classification_code' => '000001',
                    'operation_indicator' => null,
                ],
            ]],
            'payments_snapshot' => [],
            'totals_snapshot' => [
                'total' => '90.00',
                'competence_date' => '2026-10-09',
                'requires_allocation' => 'no',
            ],
        ], $overrides));
    }
}
