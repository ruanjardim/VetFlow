<?php

namespace Tests\Unit;

use App\Modules\Clinics\Models\Clinic;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalProviderCredential;
use App\Modules\Fiscal\Services\EncryptedDatabaseFiscalCredentialResolver;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class EncryptedDatabaseFiscalCredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_only_the_exact_active_credential_and_keeps_plaintext_out_of_storage(): void
    {
        $clinic = $this->clinic('Clínica Credencial', '00000000000701');
        $otherClinic = $this->clinic('Outra Clínica', '00000000000702');
        $token = 'homologation-'.str_repeat('x', 24);
        $credential = FiscalProviderCredential::query()->create([
            'clinic_id' => $clinic->id,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'reference' => 'focus-db://homologation/test-reference',
            'token' => $token,
            'token_last_four' => substr($token, -4),
            'rotated_at' => now(),
        ]);
        $resolver = new EncryptedDatabaseFiscalCredentialResolver;
        $document = $this->document($clinic->id, $credential->reference);

        $stored = (string) DB::table('fiscal_provider_credentials')->whereKey($credential->id)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertStringNotContainsString($token, $stored);
        $this->assertTrue($resolver->isConfigured());
        $this->assertTrue($resolver->canResolve($document));
        $this->assertSame($token, $resolver->tokenFor($document));
        $this->assertFalse($resolver->canResolve($this->document($otherClinic->id, $credential->reference)));
        $this->assertFalse($resolver->canResolve($this->document($clinic->id, 'focus-db://homologation/other')));

        $credential->update(['token' => null, 'revoked_at' => now()]);

        $this->assertFalse($resolver->isConfigured());
        $this->assertFalse($resolver->canResolve($document));
        $this->expectException(LogicException::class);
        $resolver->tokenFor($document);
    }

    private function document(int $clinicId, string $reference): FiscalDocument
    {
        return new FiscalDocument([
            'clinic_id' => $clinicId,
            'provider' => FiscalProviderCatalog::FOCUS_NFE,
            'environment' => 'homologation',
            'issuer_snapshot' => ['provider_credential_reference' => $reference],
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
}
