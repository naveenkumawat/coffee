<?php

namespace App\Services\CustomerDeliveryAddress;

use App\Models\CustomerDeliveryAddress;
use App\Models\User;
use Illuminate\Support\Collection;

interface CustomerDeliveryAddressServiceInterface
{
    /**
     * @return Collection<int, CustomerDeliveryAddress>
     */
    public function listForCustomer(User $customer): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(User $customer, array $data): CustomerDeliveryAddress;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $customer, CustomerDeliveryAddress $address, array $data): CustomerDeliveryAddress;

    public function delete(User $customer, CustomerDeliveryAddress $address): void;

    public function makeDefault(User $customer, CustomerDeliveryAddress $address): CustomerDeliveryAddress;

    public function findOwned(User $customer, int $addressId): CustomerDeliveryAddress;

    /**
     * Format structured address fields into the order delivery_address snapshot string.
     *
     * @param  array<string, mixed>  $data
     */
    public function formatSnapshot(array $data): string;
}
