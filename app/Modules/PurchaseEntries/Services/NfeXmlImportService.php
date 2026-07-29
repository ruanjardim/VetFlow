<?php

namespace App\Modules\PurchaseEntries\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductService;
use App\Modules\Products\Support\Gtin;
use App\Modules\Suppliers\Models\Supplier;
use Carbon\Carbon;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NfeXmlImportService
{
    public function __construct(private readonly ProductService $productService) {}

    public function import(
        string $xml,
        bool $createMissingProducts = true,
        bool $createMissingSupplier = true,
        ?int $clinicId = null,
        ?string $expectedAccessKey = null
    ): array {
        $document = $this->loadXml($xml);
        $xpath = new DOMXPath($document);
        $infNfe = $this->first($xpath, '//*[local-name()="infNFe"]');

        if (! $infNfe) {
            throw new InvalidArgumentException('XML da NF-e invalido ou sem bloco infNFe.');
        }

        $itemsCount = $this->nodes($xpath, './/*[local-name()="det"]', $infNfe)->length;
        $maxItems = max(1, (int) config('nfe_import.max_items', 500));

        if ($itemsCount > $maxItems) {
            throw new InvalidArgumentException("A NF-e excede o limite de {$maxItems} itens por importacao.");
        }

        $expectedAccessKey = Gtin::normalize($expectedAccessKey);

        return DB::transaction(function () use ($xpath, $infNfe, $createMissingProducts, $createMissingSupplier, $clinicId, $expectedAccessKey) {
            $invoice = $this->invoicePayload($xpath, $infNfe);

            if ($expectedAccessKey !== null && $invoice['access_key'] !== $expectedAccessKey) {
                throw new InvalidArgumentException('O XML encontrado nao corresponde a chave de acesso informada.');
            }

            $supplierPayload = $this->supplierPayload($xpath, $infNfe);
            $supplier = $this->resolveSupplier($supplierPayload, $createMissingSupplier, $clinicId);
            $items = $this->itemsPayload($xpath, $infNfe, $supplierPayload, $createMissingProducts, $clinicId);

            $summary = [
                'items_count' => count($items),
                'matched_products' => count(array_filter($items, fn (array $item) => ($item['matched_by'] ?? null) !== null)),
                'created_products' => count(array_filter($items, fn (array $item) => (bool) ($item['product_created'] ?? false))),
                'unmatched_products' => count(array_filter($items, fn (array $item) => empty($item['product_id']))),
                'invoice_total' => (float) ($invoice['total'] ?? 0),
            ];

            return [
                'found' => true,
                'message' => $this->successMessage($summary),
                'invoice' => $invoice,
                'supplier' => $supplier ? [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'document' => $supplier->document,
                    'created' => (bool) ($supplier->wasRecentlyCreated ?? false),
                ] : array_merge($supplierPayload, ['id' => null, 'created' => false]),
                'items' => $items,
                'summary' => $summary,
                'warnings' => $this->warnings($invoice, $supplierPayload, $supplier, $summary),
            ];
        });
    }

    private function loadXml(string $xml): DOMDocument
    {
        $maxBytes = max(1, (int) config('nfe_import.max_xml_bytes', 5 * 1024 * 1024));

        if (strlen($xml) > $maxBytes) {
            throw new InvalidArgumentException('O XML da NF-e excede o limite permitido.');
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) === 1) {
            throw new InvalidArgumentException('XML com declaracao de entidade ou DOCTYPE nao e permitido.');
        }

        $xml = trim(preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml);

        if ($xml === '') {
            throw new InvalidArgumentException('Arquivo XML vazio.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $detail = $errors[0]->message ?? 'conteudo nao reconhecido';
            throw new InvalidArgumentException('Nao consegui ler este XML: '.trim($detail));
        }

        return $document;
    }

    private function invoicePayload(DOMXPath $xpath, DOMNode $infNfe): array
    {
        $accessKey = $this->accessKey($xpath, $infNfe);
        $number = $this->text($xpath, './/*[local-name()="ide"]/*[local-name()="nNF"]', $infNfe);
        $issuedAt = $this->dateTimeLocal(
            $this->text($xpath, './/*[local-name()="ide"]/*[local-name()="dhEmi"]', $infNfe)
            ?: $this->text($xpath, './/*[local-name()="ide"]/*[local-name()="dEmi"]', $infNfe)
        );
        $firstDueDate = $this->dateOnly($this->text($xpath, './/*[local-name()="cobr"]/*[local-name()="dup"]/*[local-name()="dVenc"]', $infNfe));
        $installments = $this->nodes($xpath, './/*[local-name()="cobr"]/*[local-name()="dup"]', $infNfe)->length;

        return [
            'access_key' => $accessKey,
            'number' => $number,
            'series' => $this->text($xpath, './/*[local-name()="ide"]/*[local-name()="serie"]', $infNfe),
            'model' => $this->text($xpath, './/*[local-name()="ide"]/*[local-name()="mod"]', $infNfe),
            'purchased_at' => $issuedAt,
            'received_at' => now()->format('Y-m-d\TH:i'),
            'payment_due_date' => $firstDueDate ?: ($issuedAt ? substr($issuedAt, 0, 10) : null),
            'installments_count' => max(1, $installments),
            'payment_reference' => $number,
            'total' => $this->invoiceTotal($xpath, $infNfe),
        ];
    }

    private function supplierPayload(DOMXPath $xpath, DOMNode $infNfe): array
    {
        $emit = $this->first($xpath, './/*[local-name()="emit"]', $infNfe);

        if (! $emit) {
            return [
                'name' => null,
                'document' => null,
                'phone' => null,
                'city' => null,
                'state' => null,
            ];
        }

        return [
            'name' => $this->text($xpath, './*[local-name()="xNome"]', $emit),
            'document' => $this->normalizeDocument(
                $this->text($xpath, './*[local-name()="CNPJ"]', $emit)
                ?: $this->text($xpath, './*[local-name()="CPF"]', $emit)
            ),
            'phone' => $this->text($xpath, './/*[local-name()="enderEmit"]/*[local-name()="fone"]', $emit),
            'city' => $this->text($xpath, './/*[local-name()="enderEmit"]/*[local-name()="xMun"]', $emit),
            'state' => $this->text($xpath, './/*[local-name()="enderEmit"]/*[local-name()="UF"]', $emit),
        ];
    }

    private function itemsPayload(
        DOMXPath $xpath,
        DOMNode $infNfe,
        array $supplier,
        bool $createMissingProducts,
        ?int $clinicId
    ): array {
        $items = [];

        foreach ($this->nodes($xpath, './/*[local-name()="det"]', $infNfe) as $det) {
            $prod = $this->first($xpath, './*[local-name()="prod"]', $det);

            if (! $prod) {
                continue;
            }

            $gtin = $this->normalizeGtin(
                $this->text($xpath, './*[local-name()="cEAN"]', $prod)
                ?: $this->text($xpath, './*[local-name()="cEANTrib"]', $prod)
            );
            $name = $this->text($xpath, './*[local-name()="xProd"]', $prod);
            $supplierSku = $this->text($xpath, './*[local-name()="cProd"]', $prod);
            $unit = $this->unit($this->text($xpath, './*[local-name()="uCom"]', $prod));
            $quantity = $this->decimal($this->text($xpath, './*[local-name()="qCom"]', $prod));
            $unitCost = $this->decimal($this->text($xpath, './*[local-name()="vUnCom"]', $prod));
            $total = $this->decimal($this->text($xpath, './*[local-name()="vProd"]', $prod));
            $ncm = $this->text($xpath, './*[local-name()="NCM"]', $prod);
            $cfop = $this->text($xpath, './*[local-name()="CFOP"]', $prod);
            $product = $this->resolveProduct($gtin, $name, $supplierSku, $unit, $unitCost, $ncm, $cfop, $supplier, $createMissingProducts, $clinicId);
            $salePrice = $this->salePriceFor($product, $unitCost);

            $items[] = [
                'product_id' => $product['model']?->id,
                'product_name' => $product['model']?->name,
                'product_created' => $product['created'],
                'matched_by' => $product['matched_by'],
                'product_edit_url' => $product['model'] ? route('products.edit', $product['model']->id) : null,
                'product_create_url' => $gtin ? route('products.create').'?'.http_build_query([
                    'gtin' => $gtin,
                    'clinic_id' => $clinicId,
                    'from' => 'purchase',
                    'return_to' => 'purchase',
                ]) : null,
                'name' => $name,
                'description' => $name,
                'gtin' => $gtin,
                'barcode' => $gtin,
                'supplier_sku' => $supplierSku,
                'unit' => $unit,
                'suggested_quantity' => $quantity,
                'cost_price' => $unitCost,
                'sale_price' => $product['model'] ? (float) $product['model']->sale_price : 0,
                'suggested_sale_price' => $salePrice,
                'margin_percent' => $this->marginPercent($unitCost, $salePrice),
                'minimum_stock' => $product['model'] ? (float) $product['model']->minimum_stock : null,
                'update_sale_price' => $product['created'] && $salePrice > 0,
                'intelligence_status' => $product['matched_by'] ?: 'nfe_xml_unmatched',
                'intelligence_metadata' => [
                    'source' => 'nfe_xml',
                    'nfe_item_number' => $det->attributes?->getNamedItem('nItem')?->nodeValue,
                    'supplier_sku' => $supplierSku,
                    'ncm' => $ncm,
                    'cfop' => $cfop,
                    'unit' => $unit,
                    'xml_quantity' => $quantity,
                    'xml_unit_cost' => $unitCost,
                    'xml_total' => $total,
                    'supplier_document' => $supplier['document'] ?? null,
                    'matched_by' => $product['matched_by'],
                    'product_created' => $product['created'],
                ],
                'warnings' => $this->itemWarnings($gtin, $product['model']),
            ];
        }

        return $items;
    }

    private function resolveSupplier(array $payload, bool $createMissingSupplier, ?int $clinicId): ?Supplier
    {
        $document = $payload['document'] ?? null;
        $name = trim((string) ($payload['name'] ?? ''));

        if ($document) {
            $supplier = $this->supplierQuery($clinicId)
                ->get()
                ->first(fn (Supplier $supplier) => $this->normalizeDocument($supplier->document) === $document);

            if ($supplier) {
                return $supplier;
            }
        }

        if ($name !== '') {
            $supplier = $this->supplierQuery($clinicId)
                ->where('name', $name)
                ->first();

            if ($supplier) {
                return $supplier;
            }
        }

        if (! $createMissingSupplier || $name === '') {
            return null;
        }

        return Supplier::query()->create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'document' => $document,
            'phone' => $payload['phone'] ?? null,
            'city' => $payload['city'] ?? null,
            'state' => $payload['state'] ?? null,
            'notes' => 'Fornecedor criado a partir de XML de NF-e.',
            'active' => true,
        ]);
    }

    private function resolveProduct(
        ?string $gtin,
        ?string $name,
        ?string $supplierSku,
        ?string $unit,
        float $unitCost,
        ?string $ncm,
        ?string $cfop,
        array $supplier,
        bool $createMissingProducts,
        ?int $clinicId
    ): array {
        if ($product = $this->findProduct($gtin, $name, $clinicId)) {
            return [
                'model' => $product,
                'matched_by' => $gtin ? 'nfe_xml_gtin' : 'nfe_xml_name',
                'created' => false,
            ];
        }

        if (! $createMissingProducts || ! Gtin::looksValid($gtin) || trim((string) $name) === '') {
            return [
                'model' => null,
                'matched_by' => null,
                'created' => false,
            ];
        }

        /** @var Product $product */
        $product = $this->productService->create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'category' => $this->inferCategory($name),
            'sku' => $supplierSku,
            'barcode' => $gtin,
            'gtin' => $gtin,
            'description' => $name,
            'cost_price' => $unitCost,
            'sale_price' => 0,
            'stock_quantity' => 0,
            'minimum_stock' => 0,
            'unit' => $unit ?: 'un',
            'lookup_source' => 'nfe_xml',
            'lookup_metadata' => [
                'source' => 'nfe_xml',
                'supplier_sku' => $supplierSku,
                'supplier_name' => $supplier['name'] ?? null,
                'supplier_document' => $supplier['document'] ?? null,
                'ncm' => $ncm,
                'cfop' => $cfop,
                'imported_at' => now()->toDateTimeString(),
            ],
            'active' => true,
        ]);

        return [
            'model' => $product->refresh(),
            'matched_by' => 'nfe_xml_created',
            'created' => true,
        ];
    }

    private function findProduct(?string $gtin, ?string $name, ?int $clinicId): ?Product
    {
        if (Gtin::looksValid($gtin)) {
            $variants = Gtin::variants($gtin);

            return $this->productQuery($clinicId)
                ->where(function ($query) use ($variants) {
                    $query
                        ->whereIn('gtin', $variants)
                        ->orWhereIn('barcode', $variants);
                })
                ->orderByDesc('updated_at')
                ->first();
        }

        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $this->productQuery($clinicId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function supplierQuery(?int $clinicId)
    {
        $query = Supplier::query()->active();

        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }

        return $query;
    }

    private function productQuery(?int $clinicId)
    {
        $query = Product::query()->active();

        if ($clinicId !== null) {
            $query->where('clinic_id', $clinicId);
        }

        return $query;
    }

    private function successMessage(array $summary): string
    {
        $message = 'XML importado: '.$summary['items_count'].' item(ns) lido(s).';

        if ($summary['created_products'] > 0) {
            $message .= ' '.$summary['created_products'].' produto(s) novo(s) cadastrado(s).';
        }

        if ($summary['unmatched_products'] > 0) {
            $message .= ' '.$summary['unmatched_products'].' item(ns) ainda precisam de produto vinculado.';
        }

        return $message;
    }

    private function warnings(array $invoice, array $supplierPayload, ?Supplier $supplier, array $summary): array
    {
        return array_values(array_filter([
            empty($invoice['access_key']) ? 'Nao encontrei a chave de acesso no XML.' : null,
            empty($invoice['number']) ? 'Nao encontrei o numero da NF no XML.' : null,
            empty($supplierPayload['name']) ? 'Nao encontrei o fornecedor emitente no XML.' : null,
            ! $supplier && ! empty($supplierPayload['name']) ? 'Fornecedor nao cadastrado automaticamente.' : null,
            $summary['unmatched_products'] > 0 ? 'Existem itens sem EAN/produto. Vincule antes de salvar a entrada.' : null,
        ]));
    }

    private function itemWarnings(?string $gtin, ?Product $product): array
    {
        return array_values(array_filter([
            ! Gtin::looksValid($gtin) ? 'Item sem EAN valido no XML.' : null,
            ! $product ? 'Produto nao encontrado no VetFlow.' : null,
        ]));
    }

    private function accessKey(DOMXPath $xpath, DOMNode $infNfe): ?string
    {
        $id = $infNfe->attributes?->getNamedItem('Id')?->nodeValue;
        $digits = Gtin::normalize($id);

        if ($digits && strlen($digits) === 44) {
            return $digits;
        }

        $digits = Gtin::normalize($this->text($xpath, '//*[local-name()="protNFe"]//*[local-name()="chNFe"]'));

        return $digits && strlen($digits) === 44 ? $digits : null;
    }

    private function first(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $this->nodes($xpath, $query, $context);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function nodes(DOMXPath $xpath, string $query, ?DOMNode $context = null)
    {
        return $context ? $xpath->query($query, $context) : $xpath->query($query);
    }

    private function text(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?string
    {
        $node = $this->first($xpath, $query, $context);
        $value = trim((string) ($node?->textContent ?? ''));

        return $value !== '' ? $value : null;
    }

    private function decimal(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        return (float) str_replace(',', '.', trim($value));
    }

    private function invoiceTotal(DOMXPath $xpath, DOMNode $infNfe): float
    {
        $total = $this->decimal($this->text($xpath, './/*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vNF"]', $infNfe));

        if ($total > 0) {
            return $total;
        }

        $total = $this->decimal($this->text($xpath, './/*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vProd"]', $infNfe));

        if ($total > 0) {
            return $total;
        }

        $sum = 0;

        foreach ($this->nodes($xpath, './/*[local-name()="det"]/*[local-name()="prod"]/*[local-name()="vProd"]', $infNfe) as $node) {
            $sum += $this->decimal($node->textContent);
        }

        return round($sum, 2);
    }

    private function dateTimeLocal(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateOnly(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    private function normalizeGtin(?string $value): ?string
    {
        if (! $value || str_contains(mb_strtoupper($value), 'SEM GTIN')) {
            return null;
        }

        $gtin = Gtin::normalize($value);

        return Gtin::looksValid($gtin) ? $gtin : null;
    }

    private function unit(?string $value): string
    {
        $unit = mb_strtolower(trim((string) $value));

        return $unit !== '' ? mb_substr($unit, 0, 20) : 'un';
    }

    private function salePriceFor(array $product, float $unitCost): float
    {
        $salePrice = (float) ($product['model']?->sale_price ?? 0);

        if ($salePrice > 0) {
            return $salePrice;
        }

        return $unitCost > 0 ? round($unitCost * 1.45, 2) : 0;
    }

    private function marginPercent(float $unitCost, float $salePrice): ?float
    {
        if ($salePrice <= 0) {
            return null;
        }

        return round((($salePrice - $unitCost) / $salePrice) * 100, 2);
    }

    private function inferCategory(?string $name): ?string
    {
        $normalized = mb_strtolower((string) $name);

        return match (true) {
            str_contains($normalized, 'racao'), str_contains($normalized, 'ração') => 'Racoes',
            str_contains($normalized, 'vacina') => 'Vacinas',
            str_contains($normalized, 'antipulgas'), str_contains($normalized, 'vermifugo'), str_contains($normalized, 'vermífugo') => 'Antiparasitarios',
            str_contains($normalized, 'shampoo'), str_contains($normalized, 'higiene') => 'Higiene',
            str_contains($normalized, 'brinquedo') => 'Brinquedos',
            str_contains($normalized, 'coleira'), str_contains($normalized, 'guia') => 'Acessorios',
            default => null,
        };
    }
}
