<?php

namespace App\Services\CustomerDeliveryAddress;

use App\Models\CustomerDeliveryAddress;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerDeliveryAddressService implements CustomerDeliveryAddressServiceInterface
{
    public function listForCustomer(User $customer): Collection
    {
        return CustomerDeliveryAddress::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->get();
    }

    public function store(User $customer, array $data): CustomerDeliveryAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerDeliveryAddress {
            /** @var User $locked */
            $locked = User::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            $makeDefault = (bool) ($data['is_default'] ?? false);

            if ($makeDefault) {
                $this->clearDefault($locked);
            } elseif (! $this->listForCustomer($locked)->contains(fn (CustomerDeliveryAddress $row): bool => $row->is_default)) {
                // First saved address becomes default for convenience.
                $makeDefault = true;
            }

            return CustomerDeliveryAddress::query()->create([
                'customer_id' => $locked->getKey(),
                'label' => $this->nullableString($data['label'] ?? null),
                'recipient_name' => trim((string) $data['recipient_name']),
                'phone' => trim((string) $data['phone']),
                'address_line_1' => trim((string) $data['address_line_1']),
                'address_line_2' => $this->nullableString($data['address_line_2'] ?? null),
                'landmark' => $this->nullableString($data['landmark'] ?? null),
                'city' => trim((string) $data['city']),
                'state' => trim((string) $data['state']),
                'postal_code' => trim((string) $data['postal_code']),
                'is_default' => $makeDefault,
            ])->fresh();
        });
    }

    public function update(User $customer, CustomerDeliveryAddress $address, array $data): CustomerDeliveryAddress
    {
        $this->assertOwned($customer, $address);

        return DB::transaction(function () use ($customer, $address, $data): CustomerDeliveryAddress {
            /** @var CustomerDeliveryAddress $locked */
            $locked = CustomerDeliveryAddress::query()->whereKey($address->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOwned($customer, $locked);

            $makeDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : (bool) $locked->is_default;

            if ($makeDefault && ! $locked->is_default) {
                $this->clearDefault($customer);
            }

            $locked->fill([
                'label' => array_key_exists('label', $data) ? $this->nullableString($data['label']) : $locked->label,
                'recipient_name' => trim((string) ($data['recipient_name'] ?? $locked->recipient_name)),
                'phone' => trim((string) ($data['phone'] ?? $locked->phone)),
                'address_line_1' => trim((string) ($data['address_line_1'] ?? $locked->address_line_1)),
                'address_line_2' => array_key_exists('address_line_2', $data) ? $this->nullableString($data['address_line_2']) : $locked->address_line_2,
                'landmark' => array_key_exists('landmark', $data) ? $this->nullableString($data['landmark']) : $locked->landmark,
                'city' => trim((string) ($data['city'] ?? $locked->city)),
                'state' => trim((string) ($data['state'] ?? $locked->state)),
                'postal_code' => trim((string) ($data['postal_code'] ?? $locked->postal_code)),
                'is_default' => $makeDefault,
            ])->save();

            return $locked->fresh();
        });
    }

    public function delete(User $customer, CustomerDeliveryAddress $address): void
    {
        $this->assertOwned($customer, $address);

        DB::transaction(function () use ($customer, $address): void {
            /** @var CustomerDeliveryAddress $locked */
            $locked = CustomerDeliveryAddress::query()->whereKey($address->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOwned($customer, $locked);

            // Deleting the default clears default; remaining addresses stay non-default.
            $locked->delete();
        });
    }

    public function makeDefault(User $customer, CustomerDeliveryAddress $address): CustomerDeliveryAddress
    {
        $this->assertOwned($customer, $address);

        return DB::transaction(function () use ($customer, $address): CustomerDeliveryAddress {
            /** @var CustomerDeliveryAddress $locked */
            $locked = CustomerDeliveryAddress::query()->whereKey($address->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOwned($customer, $locked);

            $this->clearDefault($customer);
            $locked->forceFill(['is_default' => true])->save();

            return $locked->fresh();
        });
    }

    public function findOwned(User $customer, int $addressId): CustomerDeliveryAddress
    {
        $address = CustomerDeliveryAddress::query()
            ->whereKey($addressId)
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($address === null) {
            throw ValidationException::withMessages([
                'delivery_address_id' => 'Selected delivery address was not found.',
            ]);
        }

        return $address;
    }

    public function formatSnapshot(array $data): string
    {
        $lines = array_values(array_filter([
            trim((string) ($data['address_line_1'] ?? '')),
            filled($data['address_line_2'] ?? null) ? trim((string) $data['address_line_2']) : null,
            filled($data['landmark'] ?? null) ? 'Near '.trim((string) $data['landmark']) : null,
            trim(implode(', ', array_filter([
                trim((string) ($data['city'] ?? '')),
                trim((string) ($data['state'] ?? '')),
                trim((string) ($data['postal_code'] ?? '')),
            ]))),
        ]));

        return implode("\n", $lines);
    }

    protected function clearDefault(User $customer): void
    {
        CustomerDeliveryAddress::query()
            ->where('customer_id', $customer->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    protected function assertOwned(User $customer, CustomerDeliveryAddress $address): void
    {
        if ((int) $address->customer_id !== (int) $customer->getKey()) {
            abort(404);
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }
}
