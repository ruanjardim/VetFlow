<?php

namespace Tests\Unit;

use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Services\ExternalFileFiscalCredentialResolver;
use App\Modules\Fiscal\Support\FiscalProviderCatalog;
use LogicException;
use Tests\TestCase;

class ExternalFileFiscalCredentialResolverTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_it_resolves_an_exact_external_reference_and_observes_rotation(): void
    {
        $reference = 'vault://vetflow/focus/niteroi';
        $firstToken = 'fake-'.str_repeat('a', 24);
        $secondToken = 'fake-'.str_repeat('b', 24);
        $file = $this->externalStore($reference, $firstToken);
        config()->set('fiscal.credentials.external_file', $file);
        $resolver = new ExternalFileFiscalCredentialResolver;
        $document = $this->document($reference);

        $this->assertTrue($resolver->isConfigured());
        $this->assertTrue($resolver->canResolve($document));
        $this->assertSame($firstToken, $resolver->tokenFor($document));

        file_put_contents($file, $this->storeJson($reference, $secondToken));

        $this->assertSame($secondToken, $resolver->tokenFor($document));
    }

    public function test_it_fails_closed_for_a_wrong_reference_provider_or_environment(): void
    {
        $reference = 'vault://vetflow/focus/niteroi';
        $token = 'fake-'.str_repeat('c', 24);
        $file = $this->externalStore($reference, $token);
        config()->set('fiscal.credentials.external_file', $file);
        $resolver = new ExternalFileFiscalCredentialResolver;

        $this->assertFalse($resolver->canResolve($this->document('vault://vetflow/focus/other')));
        $this->assertFalse($resolver->canResolve($this->document($reference, 'production')));
        $this->assertFalse($resolver->canResolve($this->document($reference, 'homologation', 'another_provider')));

        try {
            $resolver->tokenFor($this->document('vault://vetflow/focus/other'));
            $this->fail('A referência desconhecida deveria falhar fechada.');
        } catch (LogicException $exception) {
            $this->assertSame('A credencial fiscal externa não está disponível para este documento.', $exception->getMessage());
            $this->assertStringNotContainsString($reference, $exception->getMessage());
            $this->assertStringNotContainsString($token, $exception->getMessage());
        }
    }

    public function test_it_rejects_a_store_inside_the_application_or_with_invalid_json(): void
    {
        $reference = 'vault://vetflow/focus/niteroi';
        $inside = storage_path('framework/testing/fiscal-credential-test.json');
        file_put_contents($inside, $this->storeJson($reference, 'fake-'.str_repeat('d', 24)));
        $this->temporaryFiles[] = $inside;
        config()->set('fiscal.credentials.external_file', $inside);
        $resolver = new ExternalFileFiscalCredentialResolver;

        $this->assertFalse($resolver->isConfigured());

        $invalid = $this->temporaryFile('{invalid-json');
        config()->set('fiscal.credentials.external_file', $invalid);

        $this->assertFalse($resolver->isConfigured());
        $this->assertFalse($resolver->canResolve($this->document($reference)));
    }

    private function externalStore(string $reference, string $token): string
    {
        return $this->temporaryFile($this->storeJson($reference, $token));
    }

    private function temporaryFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'vetflow-fiscal-');
        if ($file === false) {
            $this->fail('Não foi possível criar o arquivo temporário do teste.');
        }

        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;

        return $file;
    }

    private function storeJson(string $reference, string $token): string
    {
        return json_encode([
            'schema_version' => 1,
            'credentials' => [
                $reference => [
                    'provider' => FiscalProviderCatalog::FOCUS_NFE,
                    'environment' => 'homologation',
                    'token' => $token,
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    private function document(
        string $reference,
        string $environment = 'homologation',
        string $provider = FiscalProviderCatalog::FOCUS_NFE,
    ): FiscalDocument {
        return new FiscalDocument([
            'provider' => $provider,
            'environment' => $environment,
            'issuer_snapshot' => [
                'provider_credential_reference' => $reference,
            ],
        ]);
    }
}
