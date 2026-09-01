<?php

namespace App\Services\Promotion;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionCatalogService implements PromotionCatalogServiceInterface
{
    public function __construct(
        protected PromotionServiceInterface $promotions,
    ) {}

    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator
    {
        return Promotion::query()
            ->withCount('orderPromotions')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(array $data): Promotion
    {
        return DB::transaction(function () use ($data): Promotion {
            $promotion = Promotion::query()->create($this->prepareAttributes($data));
            $this->syncRelations($promotion, $data);

            return $promotion->fresh(['products', 'productCategories', 'customers']) ?? $promotion;
        });
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data): Promotion {
            $promotion->fill($this->prepareAttributes($data, (int) $promotion->getKey()));
            $promotion->save();
            $this->syncRelations($promotion, $data);

            return $promotion->fresh(['products', 'productCategories', 'customers']) ?? $promotion;
        });
    }

    public function delete(Promotion $promotion): void
    {
        DB::transaction(function () use ($promotion): void {
            $promotion->forceFill(['is_active' => false])->save();
            $promotion->delete();
        });
    }

    public function setActive(Promotion $promotion, bool $isActive): Promotion
    {
        $promotion->forceFill(['is_active' => $isActive])->save();

        return $promotion->fresh() ?? $promotion;
    }

    public function duplicate(Promotion $promotion): Promotion
    {
        return DB::transaction(function () use ($promotion): Promotion {
            $promotion->loadMissing(['products', 'productCategories', 'customers']);

            $copy = $promotion->replicate([
                'code',
            ]);
            $copy->name = trim($promotion->name).' (Copy)';
            $copy->code = null;
            $copy->is_active = false;
            $copy->type = PromotionType::Automatic;
            $copy->save();

            $copy->products()->sync($promotion->products->modelKeys());
            $copy->productCategories()->sync($promotion->productCategories->modelKeys());
            $copy->customers()->sync($promotion->customers->modelKeys());

            return $copy->fresh(['products', 'productCategories', 'customers']) ?? $copy;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?int $ignoreId = null): array
    {
        $type = PromotionType::from((string) ($data['type'] ?? PromotionType::Automatic->value));
        $code = $this->promotions->normalizeCode($data['code'] ?? null);

        if ($type === PromotionType::Automatic) {
            $code = null;
        } elseif ($code === null) {
            throw ValidationException::withMessages([
                'code' => 'A promo code is required for coupon promotions.',
            ]);
        } elseif ($this->codeExists($code, $ignoreId)) {
            throw ValidationException::withMessages([
                'code' => 'This promo code is already in use.',
            ]);
        }

        $weekdays = collect($data['weekdays'] ?? [])
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $appliesToAllProducts = (bool) ($data['applies_to_all_products'] ?? true);
        $appliesToAllCustomers = (bool) ($data['applies_to_all_customers'] ?? true);

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'code' => $code,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'type' => $type,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'minimum_subtotal' => $data['minimum_subtotal'] ?? null,
            'maximum_discount_amount' => $data['maximum_discount_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'stackable' => (bool) ($data['stackable'] ?? false),
            'applies_to_all_products' => $appliesToAllProducts,
            'applies_to_all_customers' => $appliesToAllCustomers,
            'first_order_only' => (bool) ($data['first_order_only'] ?? false),
            'fulfilment_scope' => $data['fulfilment_scope'],
            'weekdays' => $weekdays === [] ? null : $weekdays,
            'daily_starts_at' => $this->normalizeTime($data['daily_starts_at'] ?? null),
            'daily_ends_at' => $this->normalizeTime($data['daily_ends_at'] ?? null),
            'customer_message' => filled($data['customer_message'] ?? null) ? trim((string) $data['customer_message']) : null,
            'internal_note' => filled($data['internal_note'] ?? null) ? trim((string) $data['internal_note']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncRelations(Promotion $promotion, array $data): void
    {
        if ($promotion->applies_to_all_products) {
            $promotion->products()->detach();
            $promotion->productCategories()->detach();
        } else {
            $promotion->products()->sync($this->normalizedIds($data['product_ids'] ?? []));
            $promotion->productCategories()->sync($this->normalizedIds($data['product_category_ids'] ?? []));
        }

        if ($promotion->applies_to_all_customers) {
            $promotion->customers()->detach();
        } else {
            $promotion->customers()->sync($this->normalizedIds($data['customer_ids'] ?? []));
        }
    }

    protected function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return Promotion::query()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * @param  array<int|string, mixed>  $ids
     * @return list<int>
     */
    protected function normalizedIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $raw) === 1) {
            return $raw.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return $raw;
        }

        return $raw;
    }
}
