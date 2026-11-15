<?php

namespace App\Modules\Sales\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\PdvScaleSetting;
use DomainException;

class PdvScaleLabelService
{
    /**
     * @return array{
     *   recognized: bool,
     *   valid?: bool,
     *   message?: string,
     *   product?: Product,
     *   quantity?: float,
     *   scale_code?: string
     * }
     */
    public function inspect(string $label, int $clinicId): array
    {
        $digits = preg_replace('/\D+/', '', $label) ?? '';
        $setting = PdvScaleSetting::query()
            ->where('clinic_id', $clinicId)
            ->where('enabled', true)
            ->first();

        if (! $setting || strlen($digits) !== 13 || ! str_starts_with($digits, (string) $setting->label_prefix)) {
            return ['recognized' => false];
        }

        if (! $this->hasValidEan13Checksum($digits)) {
            return [
                'recognized' => true,
                'valid' => false,
                'message' => 'Etiqueta da balança com dígito verificador inválido. Pese novamente o produto.',
            ];
        }

        $prefixDigits = strlen((string) $setting->label_prefix);
        $productDigits = (int) $setting->product_code_digits;
        $valueDigits = $setting->valueDigits();

        if ($valueDigits < 3 || $productDigits < 1) {
            return [
                'recognized' => true,
                'valid' => false,
                'message' => 'O formato da etiqueta da balança precisa ser revisado nas configurações do PDV.',
            ];
        }

        $scaleCode = substr($digits, $prefixDigits, $productDigits);
        $rawWeight = substr($digits, $prefixDigits + $productDigits, $valueDigits);
        $quantity = (int) $rawWeight / (10 ** (int) $setting->weight_decimals);

        if ($quantity <= 0 || $quantity > 999999.999) {
            return [
                'recognized' => true,
                'valid' => false,
                'scale_code' => $scaleCode,
                'message' => 'A etiqueta da balança não contém um peso válido.',
            ];
        }

        $product = Product::query()
            ->active()
            ->where('clinic_id', $clinicId)
            ->where('scale_code', $scaleCode)
            ->first();

        if (! $product) {
            return [
                'recognized' => true,
                'valid' => false,
                'scale_code' => $scaleCode,
                'message' => "Nenhum produto ativo usa o código de balança {$scaleCode} nesta clínica.",
            ];
        }

        if (mb_strtolower(trim((string) $product->unit)) !== 'kg') {
            return [
                'recognized' => true,
                'valid' => false,
                'scale_code' => $scaleCode,
                'message' => 'O produto da etiqueta deve estar cadastrado com unidade kg.',
            ];
        }

        return [
            'recognized' => true,
            'valid' => true,
            'product' => $product,
            'quantity' => round($quantity, 3),
            'scale_code' => $scaleCode,
        ];
    }

    public function hasValidEan13Checksum(string $digits): bool
    {
        if (! preg_match('/^\d{13}$/', $digits)) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $digits[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10 === (int) $digits[12];
    }

    /**
     * Builds the same weight-encoded EAN-13 understood by the PDV scanner.
     *
     * @throws DomainException
     */
    public function encode(PdvScaleSetting $setting, Product $product, float $weight): string
    {
        if (! $setting->enabled) {
            throw new DomainException('Ative a leitura de etiquetas de peso antes de gerar uma etiqueta.');
        }

        if ((int) $setting->clinic_id !== (int) $product->clinic_id || ! $product->active) {
            throw new DomainException('O produto selecionado não está ativo neste estabelecimento.');
        }

        if (mb_strtolower(trim((string) $product->unit)) !== 'kg') {
            throw new DomainException('O produto selecionado deve usar a unidade kg.');
        }

        $productCode = preg_replace('/\D+/', '', (string) $product->scale_code) ?? '';
        $productDigits = (int) $setting->product_code_digits;

        if ($productCode === '' || strlen($productCode) !== $productDigits) {
            throw new DomainException("O código da balança deve ter exatamente {$productDigits} dígitos, incluindo os zeros à esquerda.");
        }

        if ((float) $product->sale_price <= 0) {
            throw new DomainException('Informe um preço de venda por quilo maior que zero antes de gerar a etiqueta.');
        }

        $valueDigits = $setting->valueDigits();
        if ($valueDigits < 3 || $valueDigits > 8) {
            throw new DomainException('O formato da etiqueta precisa deixar de 3 a 8 dígitos para o peso.');
        }

        if (! is_finite($weight) || $weight <= 0) {
            throw new DomainException('Informe um peso maior que zero.');
        }

        $factor = 10 ** (int) $setting->weight_decimals;
        $rawWeight = (int) round($weight * $factor);
        $decodedWeight = $rawWeight / $factor;

        if (abs($decodedWeight - $weight) >= 0.0005) {
            $decimals = (int) $setting->weight_decimals;
            throw new DomainException("O peso precisa respeitar as {$decimals} casas decimais configuradas para a etiqueta.");
        }

        if ($rawWeight <= 0 || $rawWeight > (10 ** $valueDigits) - 1) {
            $maximum = ((10 ** $valueDigits) - 1) / $factor;
            throw new DomainException('O peso excede o limite desta configuração: '.number_format($maximum, (int) $setting->weight_decimals, ',', '.').' kg.');
        }

        $body = (string) $setting->label_prefix
            .$productCode
            .str_pad((string) $rawWeight, $valueDigits, '0', STR_PAD_LEFT);

        if (! preg_match('/^\d{12}$/', $body)) {
            throw new DomainException('A configuração não produz os 12 dígitos necessários para uma etiqueta EAN-13.');
        }

        return $this->appendEan13Checksum($body);
    }

    /**
     * Returns the 95 EAN-13 modules used to render a standards-compatible SVG.
     *
     * @throws DomainException
     */
    public function barcodeModules(string $digits): string
    {
        if (! $this->hasValidEan13Checksum($digits)) {
            throw new DomainException('Não foi possível renderizar um EAN-13 inválido.');
        }

        $leftOdd = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101', '4' => '0100011',
            '5' => '0110001', '6' => '0101111', '7' => '0111011', '8' => '0110111', '9' => '0001011',
        ];
        $leftEven = [
            '0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001', '4' => '0011101',
            '5' => '0111001', '6' => '0000101', '7' => '0010001', '8' => '0001001', '9' => '0010111',
        ];
        $right = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010', '4' => '1011100',
            '5' => '1001110', '6' => '1010000', '7' => '1000100', '8' => '1001000', '9' => '1110100',
        ];
        $parity = [
            '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL', '4' => 'LGLLGG',
            '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG', '8' => 'LGLGGL', '9' => 'LGGLGL',
        ];

        $modules = '101';
        $leftParity = $parity[$digits[0]];

        for ($index = 1; $index <= 6; $index++) {
            $modules .= $leftParity[$index - 1] === 'L'
                ? $leftOdd[$digits[$index]]
                : $leftEven[$digits[$index]];
        }

        $modules .= '01010';

        for ($index = 7; $index <= 12; $index++) {
            $modules .= $right[$digits[$index]];
        }

        return $modules.'101';
    }

    public function exampleLabel(PdvScaleSetting $setting, string $productCode = '1', float $weight = 1.25): string
    {
        $valueDigits = max(3, $setting->valueDigits());
        $body = (string) $setting->label_prefix
            .str_pad(substr(preg_replace('/\D+/', '', $productCode) ?: '1', -(int) $setting->product_code_digits), (int) $setting->product_code_digits, '0', STR_PAD_LEFT)
            .str_pad((string) min((10 ** $valueDigits) - 1, (int) round($weight * (10 ** (int) $setting->weight_decimals))), $valueDigits, '0', STR_PAD_LEFT);

        return $this->appendEan13Checksum(substr($body, 0, 12));
    }

    private function appendEan13Checksum(string $body): string
    {
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $body[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.(string) ((10 - ($sum % 10)) % 10);
    }
}
