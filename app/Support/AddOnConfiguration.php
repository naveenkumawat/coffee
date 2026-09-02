<?php

namespace App\Support;

final class AddOnConfiguration
{
    /**
     * Canonicalize selected add-ons for identity hashing.
     *
     * @param  array<int|string, int|array{add_on_id?: int, id?: int, quantity?: int}>  $addOns
     * @return list<array{add_on_id: int, quantity: int}>
     */
    public static function canonicalize(array $addOns): array
    {
        $normalized = [];

        foreach ($addOns as $key => $value) {
            if (is_array($value)) {
                $id = (int) ($value['add_on_id'] ?? $value['id'] ?? 0);
                $qty = (int) ($value['quantity'] ?? 0);
            } else {
                $id = (int) $key;
                $qty = (int) $value;
            }

            if ($id <= 0 || $qty <= 0) {
                continue;
            }

            $normalized[$id] = ($normalized[$id] ?? 0) + $qty;
        }

        ksort($normalized);

        $rows = [];
        foreach ($normalized as $id => $qty) {
            $rows[] = [
                'add_on_id' => (int) $id,
                'quantity' => (int) $qty,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{add_on_id: int, quantity: int}>|array<int|string, mixed>  $addOns
     */
    public static function hash(int $productVariantId, array $addOns): string
    {
        $canonical = self::canonicalize($addOns);

        return hash('sha256', json_encode([
            'product_variant_id' => $productVariantId,
            'add_ons' => $canonical,
        ], JSON_THROW_ON_ERROR));
    }
}
