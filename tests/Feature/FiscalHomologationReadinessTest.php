<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalDocumentEvent;
use App\Modules\Fiscal\Models\FiscalHomologationReview;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Models\ProductFiscalProfile;
use App\Modules\Fiscal\Services\FiscalHomologationReadinessService;
use App\Modules\Fiscal\Services\FocusNfeFiscalProvider;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalHomologationReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_homologation_accepts_follow_up_but_rejects_approval(): void
    {
        [$clinic, $user] = $this->context();

        $this->actingAs($user)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Gate seguro de homologação')
            ->assertSee('Produção bloqueada')
            ->assertSee('Homologação incompleta');

        $this->get(route('fiscal.homologation-report'))
            ->assertOk()
            ->assertSee('Dossiê de homologação fiscal')
            ->assertSee('Ative somente os tipos usados pela clínica')
            ->assertSee('Produção bloqueada');

        $this->from(route('fiscal.index'))->post(route('fiscal.homologation-reviews.store'), [
            'decision' => FiscalHomologationReadinessService::APPROVED,
            'reviewer_name' => 'Contabilidade Teste',
            'current_password' => 'password',
            'confirm_review' => '1',
        ])->assertRedirect(route('fiscal.index'))
            ->assertSessionHasErrors('decision');

        $this->post(route('fiscal.homologation-reviews.store'), [
            'decision' => FiscalHomologationReadinessService::FOLLOW_UP,
            'reviewer_name' => 'Contabilidade Teste',
            'notes' => 'Completar a configuração e executar os cenários reais na Focus.',
            'current_password' => 'password',
            'confirm_review' => '1',
        ])->assertRedirect(route('fiscal.index'))
            ->assertSessionDoesntHaveErrors();

        $review = FiscalHomologationReview::query()->firstOrFail();
        $this->assertSame($clinic->id, $review->clinic_id);
        $this->assertSame($user->id, $review->reviewed_by_user_id);
        $this->assertSame(FiscalHomologationReadinessService::FOLLOW_UP, $review->decision);
        $this->assertFalse((bool) data_get($review->evidence_snapshot, 'checks.0.ready'));
        $storedSnapshot = json_encode($review->evidence_snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('password', $storedSnapshot);
        $this->assertStringNotContainsString('token', Str::lower($storedSnapshot));
        $this->assertDatabaseHas('audit_events', [
            'clinic_id' => $clinic->id,
            'event' => 'fiscal.homologation-review.recorded',
        ]);
    }

    public function test_complete_nfce_evidence_can_be_approved_without_enabling_production_and_becomes_stale(): void
    {
        [$clinic, $user] = $this->context();
        $profile = $this->readyNfceProfile($clinic);
        $taxProfile = $this->readyProduct($clinic);
        $this->readyNfceSequence($clinic);
        $this->recordNfceEvidence($clinic, $user);
        $this->enableRuntime();

        $this->actingAs($user)
            ->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Revisão humana pendente')
            ->assertSee('Aprovar para análise técnica de produção')
            ->assertSee('Produção bloqueada');

        $this->post(route('fiscal.homologation-reviews.store'), [
            'decision' => FiscalHomologationReadinessService::APPROVED,
            'reviewer_name' => 'CRC Responsável',
            'notes' => 'Cenários de homologação conferidos.',
            'current_password' => 'password',
            'confirm_review' => '1',
        ])->assertRedirect(route('fiscal.index'))
            ->assertSessionDoesntHaveErrors();

        $evaluation = app(FiscalHomologationReadinessService::class)->evaluate($clinic, $profile->fresh());
        $this->assertTrue($evaluation['technical_ready']);
        $this->assertTrue($evaluation['eligible_for_production_review']);
        $this->assertFalse($evaluation['production_enabled']);

        $this->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('Apta para análise técnica')
            ->assertSee('Produção bloqueada');

        $jsonResponse = $this->get(route('fiscal.homologation-report.json'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $jsonResponse->headers->get('cache-control'));
        $json = $jsonResponse->streamedContent();
        $report = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('fiscal-homologation-report-v1', $report['schema_version']);
        $this->assertTrue($report['status']['eligible_for_production_review']);
        $this->assertFalse($report['status']['production_enabled']);
        $this->assertSame(64, strlen($report['report_hash']));
        $this->assertStringNotContainsString('focus-db://clinic/test', $json);
        $this->assertStringNotContainsString('focus://certificate/homologation', $json);
        $this->assertStringNotContainsString('evidence_fingerprint', $json);

        $taxProfile->update(['configuration_version' => 2]);
        $stale = app(FiscalHomologationReadinessService::class)->evaluate($clinic, $profile->fresh());
        $this->assertFalse($stale['latest_review_current']);
        $this->assertFalse($stale['eligible_for_production_review']);
        $this->assertFalse($stale['production_enabled']);

        $this->get(route('fiscal.index'))
            ->assertOk()
            ->assertSee('desatualizada');
    }

    /** @return array{Clinic, User} */
    private function context(): array
    {
        $clinic = Clinic::query()->create([
            'corporate_name' => 'Clínica Homologação Niterói',
            'trade_name' => 'Clínica Homologação',
            'cnpj' => '00000000000981',
            'city' => 'Niterói',
            'state' => 'RJ',
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal Homologação',
            'slug' => 'fiscal-homologacao-'.Str::lower(Str::random(8)),
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

    private function readyNfceProfile(Clinic $clinic): FiscalProfile
    {
        return FiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'nfce_enabled' => true,
            'nfse_enabled' => false,
            'state_registration' => '12345678',
            'tax_regime' => 'simple_national',
            'certificate_reference' => 'focus://certificate/homologation',
            'certificate_expires_at' => now()->addYear(),
            'configuration_version' => 4,
            'metadata' => [
                'provider_credential_reference' => 'focus-db://clinic/test',
                'focus_nfce' => [
                    'series' => '0',
                    'csc_id' => '000001',
                    'csc_reference' => 'focus://csc/homologation',
                    'sefaz_credentialed' => true,
                ],
            ],
        ]);
    }

    private function readyProduct(Clinic $clinic): ProductFiscalProfile
    {
        $product = Product::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Produto homologado',
            'sale_price' => 10,
            'active' => true,
        ]);

        return ProductFiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'product_id' => $product->id,
            'ncm' => '30049099',
            'origin_code' => '0',
            'default_cfop' => '5102',
            'icms_code' => '102',
            'pis_code' => '49',
            'cofins_code' => '49',
            'fiscal_unit' => 'UN',
            'ibs_cbs_tax_status' => '000',
            'ibs_cbs_classification_code' => '000001',
            'ready' => true,
            'missing_fields' => [],
            'configuration_version' => 1,
        ]);
    }

    private function readyNfceSequence(Clinic $clinic): void
    {
        FiscalNfceSequence::query()->create([
            'clinic_id' => $clinic->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'series' => 0,
            'next_number' => 100,
        ]);
    }

    private function recordNfceEvidence(Clinic $clinic, User $user): void
    {
        $authorized = $this->document($clinic, $user, 'authorized', [
            'authorized_at' => now()->subMinutes(20),
        ]);
        $this->event($authorized, 'provider_response');
        $this->event($authorized, 'status_synchronized');
        $this->event($authorized, 'artifact_downloaded', ['artifact' => 'xml', 'size_bytes' => 100]);
        $this->event($authorized, 'artifact_downloaded', ['artifact' => 'danfe', 'size_bytes' => 200]);

        $rejected = $this->document($clinic, $user, 'rejected', [
            'last_error_code' => 'rejeicao_homologacao',
            'last_error_message' => 'Rejeição controlada de homologação.',
        ]);
        $this->event($rejected, 'provider_response');

        $cancelled = $this->document($clinic, $user, 'cancelled', [
            'authorized_at' => now()->subMinutes(15),
            'cancellation_requested_at' => now()->subMinutes(10),
            'cancelled_at' => now()->subMinutes(5),
        ]);
        $this->event($cancelled, 'cancellation_succeeded');
    }

    /** @param array<string, mixed> $overrides */
    private function document(Clinic $clinic, User $user, string $status, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::query()->create(array_replace([
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'type' => 'nfce',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'status' => $status,
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 4,
            'schema_version' => 3,
            'issuer_snapshot' => ['document' => $clinic->cnpj, 'address' => ['state' => 'RJ']],
            'recipient_snapshot' => null,
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => '10.00'],
            'provider_reference' => 'vf-'.Str::ulid(),
        ], $overrides));
    }

    /** @param array<string, mixed> $metadata */
    private function event(FiscalDocument $document, string $event, array $metadata = []): void
    {
        FiscalDocumentEvent::query()->create([
            'clinic_id' => $document->clinic_id,
            'fiscal_document_id' => $document->id,
            'event' => $event,
            'from_status' => $document->status,
            'to_status' => $document->status,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function enableRuntime(): void
    {
        config()->set('fiscal.provider_adapter', FiscalProviderCatalog::FOCUS_NFE);
        config()->set('fiscal.credentials.driver', 'encrypted_database');
        config()->set('fiscal.queue.connection', 'database');
        config()->set('fiscal.queue.name', 'fiscal');
        config()->set('fiscal.focus_nfe.webhook.enabled', true);
        config()->set('fiscal.focus_nfe.webhook.authorization_header', 'X-VetFlow-Focus-Webhook');
        config()->set('fiscal.focus_nfe.webhook.authorization_token', str_repeat('w', 40));
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.submission_enabled', true);

        $credentials = new FiscalHomologationFakeCredentialResolver;
        $this->app->instance(FiscalCredentialResolver::class, $credentials);
        $this->app->instance(FiscalProvider::class, new FocusNfeFiscalProvider($credentials));
    }
}

class FiscalHomologationFakeCredentialResolver implements FiscalCredentialResolver
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function canResolve(FiscalDocument $document): bool
    {
        return true;
    }

    public function tokenFor(FiscalDocument $document): string
    {
        return str_repeat('t', 32);
    }
}
