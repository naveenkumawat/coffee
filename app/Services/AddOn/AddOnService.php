<?php

namespace App\Services\AddOn;

use App\Enums\IngredientUnit;
use App\Models\AddOn;
use App\Models\AddOnRecipeLine;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddOnService implements AddOnServiceInterface
{
    public function __construct(
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function paginateForAdmin(?string $search = null): LengthAwarePaginator
    {
        return AddOn::query()
            ->withCount('recipeLines')
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: ?string,
     *     default_price: string|float,
     *     is_active?: bool,
     *     sort_order?: int,
     *     lines?: list<array<string, mixed>>,
     * }  $data
     */
    public function store(array $data): AddOn
    {
        return DB::transaction(function () use ($data): AddOn {
            $addOn = AddOn::query()->create([
                'name' => trim((string) $data['name']),
                'slug' => $this->uniqueSlug((string) $data['name']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'default_price' => $this->money((string) $data['default_price']),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 10),
            ]);

            $this->syncRecipeLines($addOn, $data['lines'] ?? []);
            $this->catalog->flushPublicCache();

            return $addOn->fresh(['recipeLines.ingredient']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: ?string,
     *     default_price: string|float,
     *     is_active?: bool,
     *     sort_order?: int,
     *     lines?: list<array<string, mixed>>,
     * }  $data
     */
    public function update(AddOn $addOn, array $data): AddOn
    {
        return DB::transaction(function () use ($addOn, $data): AddOn {
            $name = trim((string) $data['name']);
            $addOn->fill([
                'name' => $name,
                'slug' => $addOn->name === $name ? $addOn->slug : $this->uniqueSlug($name, $addOn->id),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'default_price' => $this->money((string) $data['default_price']),
                'is_active' => (bool) ($data['is_active'] ?? $addOn->is_active),
                'sort_order' => (int) ($data['sort_order'] ?? $addOn->sort_order),
            ])->save();

            if (array_key_exists('lines', $data)) {
                $this->syncRecipeLines($addOn, $data['lines'] ?? []);
            }

            $this->catalog->flushPublicCache();

            return $addOn->fresh(['recipeLines.ingredient']);
        });
    }

    public function toggleActive(AddOn $addOn): AddOn
    {
        $addOn->forceFill(['is_active' => ! $addOn->is_active])->save();
        $this->catalog->flushPublicCache();

        return $addOn->fresh();
    }

    /**
     * @param  list<array{add_on_id: int, price_override?: ?string, max_quantity?: ?int, sort_order?: int}>  $assignments
     */
    public function syncProductAssignments(Product $product, array $assignments): void
    {
        $sync = [];

        foreach (array_values($assignments) as $index => $row) {
            $addOnId = (int) ($row['add_on_id'] ?? 0);
            if ($addOnId <= 0) {
                continue;
            }

            if (! AddOn::query()->whereKey($addOnId)->exists()) {
                throw ValidationException::withMessages([
                    "add_ons.$index.add_on_id" => 'Selected add-on does not exist.',
                ]);
            }

            $override = filled($row['price_override'] ?? null)
                ? $this->money((string) $row['price_override'])
                : null;
            $max = filled($row['max_quantity'] ?? null) ? max(1, (int) $row['max_quantity']) : null;

            $sync[$addOnId] = [
                'price_override' => $override,
                'max_quantity' => $max,
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
            ];
        }

        $product->addOns()->sync($sync);
        $this->catalog->flushPublicCache();
    }

    /**
     * @return list<array{id: int, name: string, description: ?string, price: string, max_quantity: int}>
     */
    public function catalogAddOnsForProduct(Product $product): array
    {
        $product->loadMissing(['addOns' => fn ($q) => $q->where('add_ons.is_active', true)->orderByPivot('sort_order')]);

        $rows = [];
        foreach ($product->addOns as $addOn) {
            if (! $addOn->is_active) {
                continue;
            }

            $rows[] = [
                'id' => (int) $addOn->id,
                'name' => (string) $addOn->name,
                'description' => $addOn->description,
                'price' => $addOn->resolvedPrice($addOn->pivot->price_override !== null ? (string) $addOn->pivot->price_override : null),
                'max_quantity' => max(1, (int) ($addOn->pivot->max_quantity ?: 1)),
            ];
        }

        return $rows;
    }

    /**
     * Validate and resolve selected add-ons for a product.
     *
     * @param  list<array{add_on_id: int, quantity: int}>  $selected
     * @return list<array{add_on_id: int, name: string, quantity: int, unit_price: string, line_total: string}>
     */
    public function resolveSelectionForProduct(Product $product, array $selected): array
    {
        $allowed = $product->addOns()
            ->where('add_ons.is_active', true)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($selected as $index => $row) {
            $addOnId = (int) ($row['add_on_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($addOnId <= 0 || $quantity <= 0) {
                continue;
            }

            /** @var AddOn|null $addOn */
            $addOn = $allowed->get($addOnId);

            if ($addOn === null || ! $addOn->is_active) {
                throw ValidationException::withMessages([
                    "add_ons.$index.add_on_id" => 'This add-on is not available for the selected product.',
                ]);
            }

            $max = max(1, (int) ($addOn->pivot->max_quantity ?: 1));
            if ($quantity > $max) {
                throw ValidationException::withMessages([
                    "add_ons.$index.quantity" => sprintf('Maximum quantity for %s is %d.', $addOn->name, $max),
                ]);
            }

            $unitPrice = $addOn->resolvedPrice(
                $addOn->pivot->price_override !== null ? (string) $addOn->pivot->price_override : null,
            );

            $resolved[] = [
                'add_on_id' => $addOnId,
                'name' => (string) $addOn->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($unitPrice, (string) $quantity, 2),
            ];
        }

        usort($resolved, fn (array $a, array $b): int => $a['add_on_id'] <=> $b['add_on_id']);

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncRecipeLines(AddOn $addOn, array $lines): void
    {
        $prepared = $this->prepareRecipeLines($lines);
        $keepIds = [];

        foreach ($prepared as $row) {
            if (! empty($row['id'])) {
                $line = AddOnRecipeLine::query()
                    ->where('add_on_id', $addOn->id)
                    ->whereKey($row['id'])
                    ->first();
                if ($line) {
                    $line->fill($row)->save();
                    $keepIds[] = $line->id;

                    continue;
                }
            }

            $created = $addOn->recipeLines()->create($row);
            $keepIds[] = $created->id;
        }

        $addOn->recipeLines()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($q) => $q)
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function prepareRecipeLines(array $lines): array
    {
        $prepared = [];
        $ingredientIds = [];

        foreach (array_values($lines) as $index => $line) {
            $ingredientId = (int) ($line['ingredient_id'] ?? 0);
            if ($ingredientId <= 0) {
                continue;
            }

            $ingredient = Ingredient::query()->whereKey($ingredientId)->where('is_active', true)->first();
            if (! $ingredient) {
                throw ValidationException::withMessages([
                    "lines.$index.ingredient_id" => 'Only active ingredients can be used.',
                ]);
            }

            if (in_array($ingredientId, $ingredientIds, true)) {
                throw ValidationException::withMessages([
                    "lines.$index.ingredient_id" => 'Duplicate ingredients are not allowed.',
                ]);
            }
            $ingredientIds[] = $ingredientId;

            $measurementUnit = IngredientUnit::from((string) $line['measurement_unit']);
            $baseUnit = $ingredient->base_measurement_unit;
            if (! $baseUnit instanceof IngredientUnit || ! $measurementUnit->supportsBaseUnit($baseUnit)) {
                throw ValidationException::withMessages([
                    "lines.$index.measurement_unit" => 'Selected unit is not compatible with this ingredient.',
                ]);
            }

            $quantity = bcdiv((string) $line['quantity'], '1', 3);
            $prepared[] = [
                'id' => $line['id'] ?? null,
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'measurement_unit' => $measurementUnit->value,
                'base_quantity' => $measurementUnit->normalize($quantity, 3),
                'base_measurement_unit' => $baseUnit->value,
                'sort_order' => (int) ($line['sort_order'] ?? ($index + 1)),
            ];
        }

        return $prepared;
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'add-on';
        $slug = $base;
        $i = 2;

        while (
            AddOn::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    protected function money(string $value): string
    {
        return bcdiv($value, '1', 2);
    }
}
