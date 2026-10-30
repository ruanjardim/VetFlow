<?php

namespace App\Modules\Sales\Services;

use App\Modules\Products\Models\Product;
use App\Modules\Sales\Models\PdvScaleSetting;

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

    public function exampleLabel(PdvScaleSetting $setting, string $productCode = '1', float $weight = 1.25): string
    {
        $valueDigits = max(3, $setting->valueDigits());
        $body = (string) $setting->label_prefix
            .str_pad(substr(preg_replace('/\D+/', '', $productCode) ?: '1', -(int) $setting->product_code_digits), (int) $setting->product_code_digits, '0', STR_PAD_LEFT)
            .str_pad((string) min((10 ** $valueDigits) - 1, (int) round($weight * (10 ** (int) $setting->weight_decimals))), $valueDigits, '0', STR_PAD_LEFT);

        $body = substr($body, 0, 12);
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $body[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.(string) ((10 - ($sum % 10)) % 10);
    }
}
