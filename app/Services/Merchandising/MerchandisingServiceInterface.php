<?php

namespace App\Services\Merchandising;

use App\Models\User;

interface MerchandisingServiceInterface
{
    /**
     * @param  array{
     *     placement?: string,
     *     visitor_key?: string|null,
     *     session_key?: string|null,
     *     fulfilment_method?: string|null,
     *     cart_product_ids?: list<int>
     * }  $input
     * @return array{
     *     placement: string,
     *     sections: list<array<string, mixed>>,
     *     campaigns: array{banner: array<string, mixed>|null, inline: array<string, mixed>|null}
     * }
     */
    public function landingPayload(array $input = [], ?User $customer = null): array;

    public function flushConfigCache(): void;
}
