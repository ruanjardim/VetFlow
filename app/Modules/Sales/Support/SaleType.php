<?php

namespace App\Modules\Sales\Support;

/**
 * Commercial type of a sale or quote, mirroring the options offered by the
 * SimplesVet PDV (also the NF-e buyer-presence indicator). Only the delivery
 * and shipping types carry a delivery address and fee.
 */
final class SaleType
{
    public const DEFAULT = 'in_store';

    public const LABELS = [
        'in_store' => 'Presencial, para consumidor final',
        'in_store_resale' => 'Presencial, para revenda',
        'delivery' => 'Delivery ou atendimento domiciliar',
        'delivery_resale' => 'Delivery para revenda',
        'online_shipping' => 'Pedido pela internet, envio por transportadora',
        'phone_shipping' => 'Pedido por telefone, envio por transportadora',
    ];

    public const SHORT_LABELS = [
        'in_store' => 'Presencial',
        'in_store_resale' => 'Presencial (revenda)',
        'delivery' => 'Delivery',
        'delivery_resale' => 'Delivery (revenda)',
        'online_shipping' => 'Internet',
        'phone_shipping' => 'Telefone',
    ];

    private const WITHOUT_DELIVERY = ['in_store', 'in_store_resale'];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function normalize(?string $type): string
    {
        return array_key_exists((string) $type, self::LABELS) ? (string) $type : self::DEFAULT;
    }

    public static function label(?string $type): string
    {
        return self::LABELS[self::normalize($type)];
    }

    public static function shortLabel(?string $type): string
    {
        return self::SHORT_LABELS[self::normalize($type)];
    }

    public static function hasDelivery(?string $type): bool
    {
        return ! in_array(self::normalize($type), self::WITHOUT_DELIVERY, true);
    }

    /**
     * Keeps delivery data only for delivery/shipping types.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeDelivery(array $data): array
    {
        $data['sale_type'] = self::normalize($data['sale_type'] ?? null);

        if (! self::hasDelivery($data['sale_type'])) {
            $data['delivery_fee'] = 0;
            $data['delivery_address'] = null;

            return $data;
        }

        $data['delivery_fee'] = round(max(0, (float) ($data['delivery_fee'] ?? 0)), 2);
        $address = trim((string) ($data['delivery_address'] ?? ''));
        $data['delivery_address'] = $address !== '' ? $address : null;

        return $data;
    }
}
