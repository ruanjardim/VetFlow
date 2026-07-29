<?php

namespace Tests\Feature;

use App\Modules\Products\Controllers\ProductLookupController;
use App\Modules\Products\Data\ProductLookupOutcome;
use App\Modules\Products\Services\ProductLookupService;
use App\Modules\Products\Support\ProductLookupImageDownloader;
use App\Modules\PurchaseEntries\Services\NfeXmlImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ExternalIntegrationResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'product_lookup.enabled' => true,
            'product_lookup.attempts' => 1,
            'product_lookup.negative_cache_days' => 7,
            'product_lookup.providers' => [[
                'name' => 'resilience_test',
                'label' => 'Provedor de teste',
                'driver' => 'open_food_facts_family',
                'base_url' => 'https://products.example.test',
                'enabled' => true,
                'tier' => 'free',
                'priority' => 10,
                'confidence' => 65,
            ]],
        ]);
    }

    public function test_provider_outage_is_retryable_and_is_not_recorded_as_product_miss(): void
    {
        Http::fake([
            'products.example.test/*' => Http::response(['error' => 'temporarily unavailable'], 503),
        ]);

        $response = (new ProductLookupController)->show(
            '7891000315507',
            app(ProductLookupService::class)
        );

        $this->assertSame(503, $response->status());
        $this->assertSame(ProductLookupOutcome::UNAVAILABLE, $response->getData(true)['lookup_status']);
        $this->assertTrue($response->getData(true)['retryable']);
        $this->assertDatabaseMissing('product_lookup_catalogs', [
            'gtin' => '7891000315507',
            'lookup_status' => ProductLookupOutcome::NOT_FOUND,
        ]);
        $this->assertDatabaseMissing('global_product_suggestions', [
            'gtin' => '7891000315507',
            'suggestion_type' => 'not_found',
        ]);
    }

    public function test_confirmed_product_miss_is_cached_without_repeating_external_calls(): void
    {
        Http::fake([
            'products.example.test/*' => Http::response(['status' => 0], 404),
        ]);
        $service = app(ProductLookupService::class);

        $first = $service->lookupOutcome('7891000315507');
        $second = $service->lookupOutcome('7891000315507');

        $this->assertSame(ProductLookupOutcome::NOT_FOUND, $first->status);
        $this->assertFalse($first->cached);
        $this->assertSame(ProductLookupOutcome::NOT_FOUND, $second->status);
        $this->assertTrue($second->cached);
        $this->assertDatabaseHas('product_lookup_catalogs', [
            'gtin' => '7891000315507',
            'lookup_status' => ProductLookupOutcome::NOT_FOUND,
        ]);
        $this->assertDatabaseHas('global_product_suggestions', [
            'gtin' => '7891000315507',
            'suggestion_type' => 'not_found',
        ]);
        Http::assertSentCount(2);
    }

    public function test_available_provider_still_returns_and_persists_useful_product_data(): void
    {
        Http::fake([
            'products.example.test/*' => Http::response([
                'status' => 1,
                'product' => [
                    'product_name' => 'Racao teste resiliencia',
                    'brands' => 'VetFlow',
                    'categories' => 'Pet food',
                ],
            ]),
        ]);

        $outcome = app(ProductLookupService::class)->lookupOutcome('7891000315507');

        $this->assertTrue($outcome->found());
        $this->assertSame(ProductLookupOutcome::FOUND, $outcome->status);
        $this->assertSame('Racao teste resiliencia', $outcome->result?->name);
        $this->assertSame('VetFlow', $outcome->result?->brand);
        $this->assertDatabaseHas('global_products', [
            'gtin' => '7891000315507',
            'name' => 'Racao teste resiliencia',
        ]);
        $this->assertDatabaseHas('product_lookup_catalogs', [
            'gtin' => '7891000315507',
            'lookup_status' => ProductLookupOutcome::FOUND,
        ]);
    }

    public function test_product_image_download_rejects_unlisted_hosts_and_oversized_content(): void
    {
        Storage::fake('public');
        config([
            'product_lookup.image_allowed_hosts' => ['images.example.test', '127.0.0.1'],
            'product_lookup.max_image_bytes' => 4,
        ]);
        Http::fake([
            'images.example.test/*' => Http::response('12345', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '5',
            ]),
        ]);
        $downloader = app(ProductLookupImageDownloader::class);

        $this->assertNull($downloader->download(
            'http://127.0.0.1/internal.jpg',
            '7891000315507',
            'blocked'
        ));
        $this->assertNull($downloader->download(
            'https://images.example.test/large.jpg',
            '7891000315507',
            'oversized'
        ));
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSentCount(1);
    }

    public function test_nfe_rejects_doctype_before_creating_records(): void
    {
        $xml = str_replace(
            '<NFe>',
            '<!DOCTYPE NFe [<!ENTITY probe "blocked">]><NFe>',
            $this->nfeXml('12345678901234567890123456789012345678901234')
        );

        try {
            app(NfeXmlImportService::class)->import($xml);
            $this->fail('A NF-e com DOCTYPE deveria ser rejeitada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('DOCTYPE', $exception->getMessage());
        }

        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_nfe_rejects_xml_above_configured_size_before_creating_records(): void
    {
        config(['nfe_import.max_xml_bytes' => 100]);

        try {
            app(NfeXmlImportService::class)->import($this->nfeXml(
                '17345678901234567890123456789012345678901234'
            ));
            $this->fail('A NF-e acima do limite deveria ser rejeitada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('excede o limite', $exception->getMessage());
        }

        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_nfe_rejects_item_limit_before_creating_records(): void
    {
        config(['nfe_import.max_items' => 1]);
        $secondItem = <<<'XML'
<det nItem="2">
  <prod>
    <cProd>SKU-2</cProd>
    <cEAN>7891000315514</cEAN>
    <xProd>Produto dois</xProd>
    <uCom>UN</uCom>
    <qCom>1</qCom>
    <vUnCom>20.00</vUnCom>
    <vProd>20.00</vProd>
  </prod>
</det>
XML;
        $xml = str_replace('</infNFe>', $secondItem.'</infNFe>', $this->nfeXml(
            '22345678901234567890123456789012345678901234'
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('excede o limite de 1 itens');

        try {
            app(NfeXmlImportService::class)->import($xml);
        } finally {
            $this->assertDatabaseCount('suppliers', 0);
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_nfe_access_key_must_match_before_creating_records(): void
    {
        $expected = '32345678901234567890123456789012345678901234';
        $actual = '42345678901234567890123456789012345678901234';
        $xml = str_replace('</infNFe>', "<infAdic>{$expected}</infAdic></infNFe>", $this->nfeXml($actual));

        try {
            app(NfeXmlImportService::class)->import($xml, true, true, null, $expected);
            $this->fail('O XML com chave divergente deveria ser rejeitado.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('nao corresponde', $exception->getMessage());
        }

        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('products', 0);
    }

    private function nfeXml(string $accessKey): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<NFe>
  <infNFe Id="NFe{$accessKey}">
    <ide>
      <nNF>100</nNF>
      <serie>1</serie>
      <mod>55</mod>
      <dhEmi>2026-07-29T10:00:00-03:00</dhEmi>
    </ide>
    <emit>
      <CNPJ>11222333000144</CNPJ>
      <xNome>Fornecedor resiliencia</xNome>
    </emit>
    <det nItem="1">
      <prod>
        <cProd>SKU-1</cProd>
        <cEAN>7891000315507</cEAN>
        <xProd>Produto um</xProd>
        <uCom>UN</uCom>
        <qCom>1</qCom>
        <vUnCom>10.00</vUnCom>
        <vProd>10.00</vProd>
      </prod>
    </det>
    <total>
      <ICMSTot>
        <vNF>10.00</vNF>
      </ICMSTot>
    </total>
  </infNFe>
</NFe>
XML;
    }
}
