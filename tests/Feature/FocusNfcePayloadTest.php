<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FocusNfcePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_nfce_homologation_profile_stores_only_external_csc_reference_and_its_own_sequence(): void
    {
        [$clinic, $user] = $this->context();

        $this->actingAs($user)->put(route('fiscal.update'), [
            'enabled' => '1',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'provider_credential_reference' => 'vault://focus/token',
            'nfce_enabled' => '1',
            'nfse_enabled' => '0',
            'nfce_series' => '0',
            'nfce_next_number' => '10',
            'nfce_csc_id' => '000001',
            'nfce_csc_reference' => 'focus://empresa/nfce/homologacao/csc',
            'nfce_pix_payment_code' => '17',
            'nfce_sefaz_credentialed' => '1',
            'state_registration' => '12345678',
            'municipal_registration' => '',
            'tax_regime' => 'simple_national',
            'municipality_code' => '3303302',
            'cnae' => '7500100',
            'certificate_reference' => 'focus://empresa/certificado',
            'certificate_fingerprint' => 'AA:BB:CC',
            'certificate_expires_at' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('fiscal.index'))->assertSessionDoesntHaveErrors();

        $profile = FiscalProfile::query()->where('clinic_id', $clinic->id)->firstOrFail();
        $this->assertSame('000001', data_get($profile->metadata, 'focus_nfce.csc_id'));
        $this->assertSame('focus://empresa/nfce/homologacao/csc', data_get($profile->metadata, 'focus_nfce.csc_reference'));
        $this->assertTrue(data_get($profile->metadata, 'focus_nfce.sefaz_credentialed'));
        $this->assertDatabaseHas('fiscal_nfce_sequences', [
            'clinic_id' => $clinic->id,
            'series' => 0,
            'next_number' => 10,
        ]);

        $this->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Preparação NFC-e do RJ')
            ->assertSee('Configuração completa');
    }

    public function test_nfce_payload_is_generated_once_with_its_own_sequence_and_no_transmission(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        Http::preventStrayRequests();
        $sequence = $this->sequence($clinic, 100);
        $document = $this->document($clinic, $user);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $document->refresh();
        $payload = data_get($document->request_payload, 'focus_nfce.payload');
        $this->assertSame('0', $document->series);
        $this->assertSame('100', $document->document_number);
        $this->assertSame('vf-'.$document->ulid, $document->provider_reference);
        $this->assertSame('0', $payload['serie']);
        $this->assertSame('100', $payload['numero']);
        $this->assertSame('00000000000911', $payload['cnpj_emitente']);
        $this->assertSame('1', $payload['regime_tributario_emitente']);
        $this->assertSame('1', $payload['consumidor_final']);
        $this->assertSame('1', $payload['presenca_comprador']);
        $this->assertSame('100.00', $payload['valor_total']);
        $this->assertSame('110.00', $payload['valor_produtos']);
        $this->assertSame('10.00', $payload['valor_desconto']);
        $this->assertSame('30049099', $payload['items'][0]['codigo_ncm']);
        $this->assertSame('102', $payload['items'][0]['icms_situacao_tributaria']);
        $this->assertSame('000', $payload['items'][0]['ibs_cbs_situacao_tributaria']);
        $this->assertSame('000001', $payload['items'][0]['ibs_cbs_classificacao_tributaria']);
        $this->assertSame('01', $payload['formas_pagamento'][0]['forma_pagamento']);
        $this->assertSame('17', $payload['formas_pagamento'][1]['forma_pagamento']);
        $this->assertSame(101, $sequence->fresh()->next_number);
        $this->assertSame(100, $sequence->fresh()->last_reserved_number);
        $this->assertDatabaseHas('fiscal_document_events', [
            'fiscal_document_id' => $document->id,
            'event' => 'payload_generated',
            'from_status' => 'draft',
            'to_status' => 'draft',
        ]);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(101, $sequence->fresh()->next_number);
        $this->assertDatabaseCount('fiscal_document_events', 1);
        Http::assertNothingSent();
        $this->get(route('fiscal.documents.show', $document->id))
            ->assertOk()
            ->assertSee('Payload NFC-e Focus NFe')
            ->assertSee('focus-nfce-rj-2026-10')
            ->assertSee('Transmissão bloqueada');
    }

    public function test_nfce_payload_keeps_approved_discount_addition_and_delivery_allocation(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $this->sequence($clinic, 150);
        $document = $this->document($clinic, $user, [
            'recipient_snapshot' => [
                'name' => 'Consumidor Entrega',
                'document' => '12345678909',
                'email' => 'entrega@example.test',
                'consumer_final' => true,
                'sale_type' => 'delivery',
                'address' => [
                    'zip_code' => '24000000',
                    'state' => 'RJ',
                    'city' => 'Niterói',
                    'district' => 'Centro',
                    'street' => 'Rua da Entrega',
                    'number' => '20',
                ],
            ],
            'items_snapshot' => [[
                'source_id' => 1,
                'type' => 'product',
                'product_id' => 10,
                'description' => 'Produto veterinário',
                'quantity' => '2.000',
                'unit_price' => '55.00',
                'line_discount_total' => '10.00',
                'allocated_sale_discount' => '5.00',
                'allocated_additions_total' => '2.00',
                'allocated_delivery_fee' => '3.00',
                'discount_total' => '15.00',
                'total' => '100.00',
                'fiscal_profile_version' => 1,
                'fiscal' => [
                    'ncm' => '30049099',
                    'cest' => null,
                    'origin_code' => '0',
                    'default_cfop' => '5102',
                    'icms_code' => '102',
                    'pis_code' => '07',
                    'cofins_code' => '07',
                    'fiscal_unit' => 'UN',
                    'tax_benefit_code' => null,
                    'ibs_cbs_tax_status' => '000',
                    'ibs_cbs_classification_code' => '000001',
                ],
            ]],
            'totals_snapshot' => [
                'total' => '100.00',
                'paid_total' => '100.00',
                'change_total' => '0.00',
                'additions_total' => '2.00',
                'delivery_fee' => '3.00',
                'competence_date' => '2026-10-09',
                'sale_type' => 'delivery',
                'delivery' => 'yes',
                'requires_allocation' => 'no',
            ],
        ]);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertSessionDoesntHaveErrors();

        $payload = data_get($document->fresh()->request_payload, 'focus_nfce.payload');
        $this->assertSame('3.00', $payload['valor_frete']);
        $this->assertSame('2.00', $payload['valor_outras_despesas']);
        $this->assertSame('3.00', $payload['items'][0]['valor_frete']);
        $this->assertSame('2.00', $payload['items'][0]['valor_outras_despesas']);
        $this->assertSame('15.00', $payload['items'][0]['valor_desconto']);
        $this->assertSame('100.00', $payload['valor_total']);
    }

    public function test_nfce_payload_fails_closed_before_number_reservation_when_csc_reference_is_missing(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $sequence = $this->sequence($clinic, 200);
        $document = $this->document($clinic, $user);
        $issuer = $document->issuer_snapshot;
        $issuer['certificate_expires_at'] = 'invalid-date';
        $issuer['focus_nfce']['csc_reference'] = null;
        $document->update(['issuer_snapshot' => $issuer]);

        $this->from(route('fiscal.documents.show', $document->id))
            ->post(route('fiscal.documents.payload', $document->id))
            ->assertRedirect(route('fiscal.documents.show', $document->id))
            ->assertSessionHasErrors('payload');

        $this->assertNull($document->fresh()->request_payload);
        $this->assertNull($document->fresh()->document_number);
        $this->assertSame(200, $sequence->fresh()->next_number);
        $this->assertDatabaseCount('fiscal_document_events', 0);
    }

    public function test_nfce_partial_payment_adds_payment_later_without_guessing_a_method(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $this->sequence($clinic, 300);
        $document = $this->document($clinic, $user, [
            'payments_snapshot' => [[
                'source_id' => 1,
                'method' => 'cash',
                'method_name' => 'Dinheiro',
                'amount' => '30.00',
                'installments' => 1,
                'allocation' => 'full_sale',
            ]],
        ]);

        $this->post(route('fiscal.documents.payload', $document->id))
            ->assertSessionDoesntHaveErrors();

        $forms = data_get($document->fresh()->request_payload, 'focus_nfce.payload.formas_pagamento');
        $this->assertSame('01', $forms[0]['forma_pagamento']);
        $this->assertSame('30.00', $forms[0]['valor_pagamento']);
        $this->assertSame('91', $forms[1]['forma_pagamento']);
        $this->assertSame('70.00', $forms[1]['valor_pagamento']);
    }

    /** @return array{Clinic, User} */
    private function context(): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica NFC-e Niterói',
            'trade_name' => 'Clínica NFC-e',
            'cnpj' => '00000000000911',
            'state' => 'RJ',
            'city' => 'Niterói',
            'district' => 'Centro',
            'street' => 'Rua de Teste',
            'number' => '100',
            'zip_code' => '24000000',
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal NFC-e',
            'slug' => 'fiscal-nfce-'.Str::lower(Str::random(8)),
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

    private function sequence(Clinic $clinic, int $nextNumber): FiscalNfceSequence
    {
        return FiscalNfceSequence::query()->create([
            'clinic_id' => $clinic->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'series' => 0,
            'next_number' => $nextNumber,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function document(Clinic $clinic, User $user, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::query()->create(array_replace([
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'type' => 'nfce',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'status' => 'draft',
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 2,
            'issuer_snapshot' => [
                'corporate_name' => 'Clínica NFC-e Niterói',
                'trade_name' => 'Clínica NFC-e',
                'document' => '00000000000911',
                'state_registration' => '12345678',
                'tax_regime' => 'simple_national',
                'municipality_code' => '3303302',
                'certificate_reference' => 'focus://empresa/certificado',
                'certificate_expires_at' => now()->addYear()->toDateString(),
                'focus_nfce' => [
                    'series' => '0',
                    'csc_id' => '000001',
                    'csc_reference' => 'focus://empresa/nfce/homologacao/csc',
                    'pix_payment_code' => '17',
                    'sefaz_credentialed' => true,
                ],
                'address' => [
                    'zip_code' => '24000000',
                    'state' => 'RJ',
                    'city' => 'Niterói',
                    'district' => 'Centro',
                    'street' => 'Rua de Teste',
                    'number' => '100',
                ],
            ],
            'recipient_snapshot' => [
                'name' => 'Consumidor Teste',
                'document' => '12345678909',
                'email' => 'consumidor@example.test',
                'consumer_final' => true,
                'sale_type' => 'in_store',
                'address' => [],
            ],
            'items_snapshot' => [[
                'source_id' => 1,
                'type' => 'product',
                'product_id' => 10,
                'description' => 'Produto veterinário',
                'quantity' => '2.000',
                'unit_price' => '55.00',
                'discount_total' => '10.00',
                'total' => '100.00',
                'fiscal_profile_version' => 1,
                'fiscal' => [
                    'ncm' => '30049099',
                    'cest' => null,
                    'origin_code' => '0',
                    'default_cfop' => '5102',
                    'icms_code' => '102',
                    'pis_code' => '07',
                    'cofins_code' => '07',
                    'fiscal_unit' => 'UN',
                    'tax_benefit_code' => null,
                    'ibs_cbs_tax_status' => '000',
                    'ibs_cbs_classification_code' => '000001',
                ],
            ]],
            'payments_snapshot' => [
                [
                    'source_id' => 1,
                    'method' => 'cash',
                    'method_name' => 'Dinheiro',
                    'amount' => '60.00',
                    'installments' => 1,
                    'allocation' => 'full_sale',
                ],
                [
                    'source_id' => 2,
                    'method' => 'pix',
                    'method_name' => 'Pix',
                    'amount' => '40.00',
                    'installments' => 1,
                    'allocation' => 'full_sale',
                ],
            ],
            'totals_snapshot' => [
                'total' => '100.00',
                'paid_total' => '100.00',
                'change_total' => '0.00',
                'competence_date' => '2026-10-09',
                'sale_type' => 'in_store',
                'delivery' => 'no',
                'requires_allocation' => 'no',
            ],
        ], $overrides));
    }
}
