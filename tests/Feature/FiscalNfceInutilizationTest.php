<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Contracts\FiscalCredentialResolver;
use App\Modules\Fiscal\Contracts\FiscalProvider;
use App\Modules\Fiscal\Jobs\ProcessNfceInutilizationJob;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalNfceInutilization;
use App\Modules\Fiscal\Models\FiscalNfceSequence;
use App\Modules\Fiscal\Models\FiscalProfile;
use App\Modules\Fiscal\Services\FiscalNfceInutilizationService;
use App\Modules\Fiscal\Services\FiscalNfceSequenceService;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class FiscalNfceInutilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inutilization_fails_closed_while_external_operations_are_disabled(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $this->profile($clinic);
        $this->sequence($clinic, 20);

        $this->actingAs($user)
            ->post(route('fiscal.nfce.inutilizations.store'), $this->payload(10, 12))
            ->assertSessionHasErrors('number_start');

        $this->assertDatabaseCount('fiscal_nfce_inutilizations', 0);
        Queue::assertNothingPushed();
    }

    public function test_reviewed_past_range_is_queued_and_authorized_with_auditable_response(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $profile = $this->profile($clinic);
        $this->sequence($clinic, 20);
        $provider = new FiscalNfceInutilizationFakeProvider;
        $this->enableOperations($provider);

        $this->actingAs($user)
            ->post(route('fiscal.nfce.inutilizations.store'), $this->payload(10, 12))
            ->assertSessionDoesntHaveErrors();

        $inutilization = FiscalNfceInutilization::query()->sole();
        $this->assertSame('queued', $inutilization->status);
        $this->assertSame('10', data_get($inutilization->request_payload, 'numero_inicial'));
        $this->assertSame('12', data_get($inutilization->request_payload, 'numero_final'));
        Queue::assertPushed(ProcessNfceInutilizationJob::class, 1);

        $authorized = app(FiscalNfceInutilizationService::class)->process($inutilization->id);

        $this->assertSame('authorized', $authorized?->status);
        $this->assertSame('PROTO-INUTILIZACAO-TESTE', $authorized?->protocol);
        $this->assertNotNull($authorized?->processed_at);
        $this->assertSame(1, $provider->inutilizationCalls);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'fiscal.nfce-inutilization.authorized',
            'subject_id' => (string) $inutilization->id,
            'actor_user_id' => $user->id,
        ]);

        try {
            app(FiscalNfceSequenceService::class)->configure($profile, [
                'nfce_series' => 0,
                'nfce_next_number' => 12,
            ]);
            $this->fail('A sequência não pode voltar para uma faixa inutilizada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nfce_next_number', $exception->errors());
        }

        FiscalNfceSequence::withoutGlobalScopes()
            ->where('clinic_id', $clinic->id)
            ->where('series', 0)
            ->update(['next_number' => 11]);
        $draft = $this->document($clinic, $user, 99);
        $draft->update(['document_number' => null]);

        try {
            app(FiscalNfceSequenceService::class)->reserve($draft, 0);
            $this->fail('Um número inutilizado não pode voltar a ser reservado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payload', $exception->errors());
        }
    }

    public function test_range_guard_rejects_future_used_and_overlapping_numbers(): void
    {
        Queue::fake();
        [$clinic, $user] = $this->context();
        $profile = $this->profile($clinic);
        $this->sequence($clinic, 20);
        $this->enableOperations(new FiscalNfceInutilizationFakeProvider);
        $this->document($clinic, $user, 11);

        $this->actingAs($user)
            ->post(route('fiscal.nfce.inutilizations.store'), $this->payload(10, 12))
            ->assertSessionHasErrors('number_start');

        FiscalNfceInutilization::withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'requested_by_user_id' => $user->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'status' => 'authorized',
            'issuer_document' => '00000000000911',
            'credential_reference' => 'vault://focus/niteroi',
            'series' => 0,
            'number_start' => 5,
            'number_end' => 8,
            'justification' => 'Faixa anterior confirmada pela contabilidade.',
            'request_payload' => [],
            'queued_at' => now(),
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('fiscal.nfce.inutilizations.store'), $this->payload(8, 9))
            ->assertSessionHasErrors('number_start');

        $this->actingAs($user)
            ->post(route('fiscal.nfce.inutilizations.store'), $this->payload(18, 20))
            ->assertSessionHasErrors('number_end');

        $this->assertSame(1, FiscalNfceInutilization::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
        $this->assertTrue(app(FiscalNfceInutilizationService::class)->isAvailable($profile));
    }

    public function test_provider_response_must_match_the_exact_range_before_rejection_or_authorization_is_saved(): void
    {
        [$clinic, $user] = $this->context();
        $this->actingAs($user);
        $inutilization = FiscalNfceInutilization::withoutGlobalScopes()->create([
            'clinic_id' => $clinic->id,
            'requested_by_user_id' => $user->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'status' => 'processing',
            'issuer_document' => '00000000000911',
            'credential_reference' => 'vault://focus/niteroi',
            'series' => 0,
            'number_start' => 10,
            'number_end' => 12,
            'justification' => 'Faixa revisada e não utilizada pela clínica.',
            'request_payload' => [],
            'queued_at' => now(),
        ]);
        $service = app(FiscalNfceInutilizationService::class);

        try {
            $service->applyResponse($inutilization->id, [
                'status' => 'autorizado',
                'cnpj' => '11111111111111',
                'modelo' => '65',
                'serie' => '0',
                'numero_inicial' => '10',
                'numero_final' => '12',
            ]);
            $this->fail('A resposta de outro emitente deveria ser recusada.');
        } catch (LogicException) {
            $this->assertSame('processing', $inutilization->fresh()->status);
        }

        $rejected = $service->applyResponse($inutilization->id, [
            'status' => 'erro_autorizacao',
            'status_sefaz' => '204',
            'mensagem_sefaz' => 'Faixa já utilizada.',
            'cnpj' => '00000000000911',
            'modelo' => '65',
            'serie' => '0',
            'numero_inicial' => '10',
            'numero_final' => '12',
            'segredo' => 'não persistir',
        ]);

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('204', $rejected->last_error_code);
        $this->assertSame('Faixa já utilizada.', $rejected->last_error_message);
        $this->assertArrayNotHasKey('segredo', $rejected->response_payload);
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
            'active' => true,
        ]);
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'active' => true]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'fiscal.manage'],
            ['name' => 'Fiscal', 'description' => 'Fiscal', 'group' => 'Fiscal', 'active' => true]
        );
        $role = Role::query()->create([
            'name' => 'Fiscal Inutilização',
            'slug' => 'fiscal-inutilization-'.Str::lower(Str::random(8)),
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

    private function profile(Clinic $clinic): FiscalProfile
    {
        return FiscalProfile::query()->create([
            'clinic_id' => $clinic->id,
            'enabled' => true,
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'nfce_enabled' => true,
            'nfse_enabled' => false,
            'configuration_version' => 1,
            'metadata' => [
                'provider_credential_reference' => 'vault://focus/niteroi',
                'focus_nfce' => [
                    'series' => 0,
                    'csc_id' => '000001',
                    'csc_reference' => 'vault://focus/niteroi/csc',
                    'sefaz_credentialed' => true,
                ],
            ],
        ]);
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

    private function document(Clinic $clinic, User $user, int $number): FiscalDocument
    {
        return FiscalDocument::query()->create([
            'clinic_id' => $clinic->id,
            'created_by_user_id' => $user->id,
            'type' => 'nfce',
            'environment' => 'homologation',
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'status' => 'draft',
            'idempotency_key' => hash('sha256', Str::random(40)),
            'profile_version' => 1,
            'schema_version' => 2,
            'issuer_snapshot' => ['document' => '00000000000911', 'address' => ['state' => 'RJ']],
            'recipient_snapshot' => [],
            'items_snapshot' => [],
            'payments_snapshot' => [],
            'totals_snapshot' => ['total' => '0.00'],
            'series' => '0',
            'document_number' => (string) $number,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(int $start, int $end): array
    {
        return [
            'number_start' => $start,
            'number_end' => $end,
            'justification' => 'Faixa não utilizada após ajuste confirmado da sequência.',
            'confirm_inutilization' => '1',
        ];
    }

    private function enableOperations(FiscalProvider $provider): void
    {
        config()->set('fiscal.external_http_enabled', true);
        config()->set('fiscal.submission_enabled', true);
        config()->set('fiscal.queue.connection', 'database');
        config()->set('fiscal.queue.name', 'fiscal');
        $this->app->instance(FiscalProvider::class, $provider);
        $this->app->instance(FiscalCredentialResolver::class, new FiscalNfceInutilizationFakeCredentialResolver);
    }
}

class FiscalNfceInutilizationFakeProvider implements FiscalProvider
{
    public int $inutilizationCalls = 0;

    public function submit(FiscalDocument $document): array
    {
        throw new RuntimeException('Não utilizado.');
    }

    public function status(FiscalDocument $document): array
    {
        throw new RuntimeException('Não utilizado.');
    }

    public function cancel(FiscalDocument $document, string $reason): array
    {
        throw new RuntimeException('Não utilizado.');
    }

    public function inutilizeNfce(FiscalNfceInutilization $inutilization): array
    {
        $this->inutilizationCalls++;

        return [
            'status' => 'autorizado',
            'status_sefaz' => '102',
            'mensagem_sefaz' => 'Inutilização homologada.',
            'cnpj' => $inutilization->issuer_document,
            'modelo' => '65',
            'serie' => (string) $inutilization->series,
            'numero_inicial' => (string) $inutilization->number_start,
            'numero_final' => (string) $inutilization->number_end,
            'protocolo_sefaz' => 'PROTO-INUTILIZACAO-TESTE',
            'caminho_xml' => '/arquivos/inutilizacao.xml',
        ];
    }

    public function resendWebhook(FiscalDocument $document): void
    {
        throw new RuntimeException('Não utilizado.');
    }

    public function downloadArtifact(FiscalDocument $document, string $artifact): array
    {
        throw new RuntimeException('Não utilizado.');
    }
}

class FiscalNfceInutilizationFakeCredentialResolver implements FiscalCredentialResolver
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
        return 'fake-inutilization-token';
    }
}
