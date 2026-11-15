<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Audit\Services\AuditTrailService;
use App\Modules\Fiscal\Models\ProductFiscalProfile;
use App\Modules\Fiscal\Models\ServiceFiscalProfile;
use App\Modules\PetShopServices\Models\PetShopService;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FiscalTaxProfileService
{
    public const PRODUCT_REQUIRED = [
        'ncm' => 'NCM',
        'origin_code' => 'Origem da mercadoria',
        'default_cfop' => 'CFOP padrão',
        'icms_code' => 'CST/CSOSN do ICMS',
        'pis_code' => 'CST do PIS',
        'cofins_code' => 'CST da COFINS',
        'fiscal_unit' => 'Unidade tributável',
        'ibs_cbs_tax_status' => 'CST do IBS/CBS',
        'ibs_cbs_classification_code' => 'Classificação tributária do IBS/CBS',
    ];

    public const SERVICE_REQUIRED = [
        'national_taxation_code' => 'Código de tributação nacional',
        'service_list_item' => 'Item da lista de serviços',
        'cnae' => 'CNAE',
        'municipality_code' => 'Município de incidência',
        'iss_rate' => 'Alíquota de ISS',
        'iss_taxation' => 'Tributação do ISS',
        'ibs_cbs_tax_status' => 'CST do IBS/CBS',
        'ibs_cbs_classification_code' => 'Classificação tributária do IBS/CBS',
    ];

    public function __construct(private readonly AuditTrailService $audit) {}

    public function productProfile(Product $product): ProductFiscalProfile
    {
        return $product->fiscalProfile ?: new ProductFiscalProfile([
            'clinic_id' => $product->clinic_id,
            'product_id' => $product->id,
            'ready' => false,
            'missing_fields' => array_keys(self::PRODUCT_REQUIRED),
            'configuration_version' => 1,
        ]);
    }

    public function serviceProfile(PetShopService $service): ServiceFiscalProfile
    {
        return $service->fiscalProfile ?: new ServiceFiscalProfile([
            'clinic_id' => $service->clinic_id,
            'petshop_service_id' => $service->id,
            'ready' => false,
            'missing_fields' => array_keys(self::SERVICE_REQUIRED),
            'configuration_version' => 1,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateProduct(Product $product, array $data): ProductFiscalProfile
    {
        return DB::transaction(function () use ($product, $data): ProductFiscalProfile {
            $profile = $this->productProfile($product);
            $before = $profile->exists ? $this->snapshot($profile) : [];
            $data = $this->normalize($data, array_keys(self::PRODUCT_REQUIRED));
            $data['missing_fields'] = $this->missing($data, self::PRODUCT_REQUIRED);
            $data['ready'] = $data['missing_fields'] === [];
            $data['configuration_version'] = $profile->exists && $before !== $this->comparable($profile, $data)
                ? $profile->configuration_version + 1
                : ($profile->configuration_version ?: 1);

            $profile->fill($data + [
                'clinic_id' => $product->clinic_id,
                'product_id' => $product->id,
            ])->save();

            $this->recordAudit('fiscal.product-profile.updated', $product, $profile, $before);

            return $profile;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateService(PetShopService $service, array $data): ServiceFiscalProfile
    {
        return DB::transaction(function () use ($service, $data): ServiceFiscalProfile {
            $profile = $this->serviceProfile($service);
            $before = $profile->exists ? $this->snapshot($profile) : [];
            $data = $this->normalize($data, array_keys(self::SERVICE_REQUIRED));
            $data['iss_withheld'] = (bool) ($data['iss_withheld'] ?? false);
            $data['iss_rate'] = $data['iss_rate'] === null
                ? null
                : number_format((float) $data['iss_rate'], 4, '.', '');
            $data['missing_fields'] = $this->missing($data, self::SERVICE_REQUIRED);
            $data['ready'] = $data['missing_fields'] === [];
            $data['configuration_version'] = $profile->exists && $before !== $this->comparable($profile, $data)
                ? $profile->configuration_version + 1
                : ($profile->configuration_version ?: 1);

            $profile->fill($data + [
                'clinic_id' => $service->clinic_id,
                'petshop_service_id' => $service->id,
            ])->save();

            $this->recordAudit('fiscal.service-profile.updated', $service, $profile, $before);

            return $profile;
        });
    }

    /** @return array<string, string> */
    public static function missingLabels(?Model $profile, string $type): array
    {
        $required = $type === 'product' ? self::PRODUCT_REQUIRED : self::SERVICE_REQUIRED;
        $missing = $profile === null
            ? array_keys($required)
            : ($profile->missing_fields ?? array_keys($required));

        return collect($missing)
            ->mapWithKeys(fn (string $field): array => [$field => $required[$field] ?? $field])
            ->all();
    }

    /** @param array<string, mixed> $data @param array<int, string> $fields */
    private function normalize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = trim($value) === '' ? null : trim($value);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, string> $required @return array<int, string> */
    private function missing(array $data, array $required): array
    {
        return collect(array_keys($required))
            ->filter(fn (string $field): bool => ! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $profile): array
    {
        return collect($profile->toArray())
            ->except(['id', 'ulid', 'clinic_id', 'product_id', 'petshop_service_id', 'created_at', 'updated_at'])
            ->all();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function comparable(Model $profile, array $data): array
    {
        $current = $this->snapshot($profile);

        return array_replace($current, $data, ['configuration_version' => $profile->configuration_version]);
    }

    private function recordAudit(string $event, Model $subject, Model $profile, array $before): void
    {
        $this->audit->record(
            $event,
            $subject,
            $before,
            $this->snapshot($profile),
            subjectLabel: (string) $subject->name,
            clinicId: (int) $profile->clinic_id
        );
    }
}
