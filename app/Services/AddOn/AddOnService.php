<?php

namespace App\Services\AddOn;

use App\Enums\IngredientUnit;
use App\Models\AddOn;
use App\Models\AddOnRecipeLine;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductAddOn;
use App\Models\ProductAddOnRecipeLine;
use App\Models\ProductVariant;
use App\Models\ProductVariantAddOnRecipeLine;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Support\PublicMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
            ->withCount('products')
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
     *     image_path?: ?string,
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
                'image_path' => $data['image_path'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 10),
            ]);

            $this->catalog->flushPublicCache();

            return $addOn->fresh();
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: ?string,
     *     default_price: string|float,
     *     is_active?: bool,
     *     sort_order?: int,
     *     image_path?: ?string,
     * }  $data
     */
    public function update(AddOn $addOn, array $data): AddOn
    {
        return DB::transaction(function () use ($addOn, $data): AddOn {
            $name = trim((string) $data['name']);
            $payload = [
                'name' => $name,
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'default_price' => $this->money((string) $data['default_price']),
                'is_active' => (bool) ($data['is_active'] ?? $addOn->is_active),
                'sort_order' => (int) ($data['sort_order'] ?? $addOn->sort_order),
            ];

            if (array_key_exists('image_path', $data)) {
                $payload['image_path'] = $data['image_path'];
            }

            $addOn->fill($payload)->save();

            $this->catalog->flushPublicCache();

            return $addOn->fresh();
        });
    }

    public function syncImage(AddOn $addOn, ?UploadedFile $image, bool $remove): AddOn
    {
        $previous = $addOn->image_path;

        if ($image !== null) {
            $path = PublicMedia::store($image, PublicMedia::DIRECTORY_ADDONS);
            $addOn->forceFill(['image_path' => $path])->save();
            PublicMedia::deleteManaged($previous);
            $this->catalog->flushPublicCache();

            return $addOn->fresh();
        }

        if ($remove) {
            $addOn->forceFill(['image_path' => null])->save();
            PublicMedia::deleteManaged($previous);
            $this->catalog->flushPublicCache();
        }

        return $addOn->fresh();
    }

    public function toggleActive(AddOn $addOn): AddOn
    {
        $addOn->forceFill(['is_active' => ! $addOn->is_active])->save();
        $this->catalog->flushPublicCache();

        return $addOn->fresh();
    }

    /**
     * @param  list<array{
     *     add_on_id: int,
     *     price_override?: ?string,
     *     max_quantity?: ?int,
     *     is_active?: bool,
     *     sort_order?: int,
     *     lines?: list<array<string, mixed>>,
     *     variant_overrides?: list<array{product_variant_id: int, lines: list<array<string, mixed>>}>
     * }>  $assignments
     */
    public function syncProductAssignments(Product $product, array $assignments): void
    {
        DB::transaction(function () use ($product, $assignments): void {
            $keepAddOnIds = [];
            $seen = [];

            foreach (array_values($assignments) as $index => $row) {
                $addOnId = (int) ($row['add_on_id'] ?? 0);
                if ($addOnId <= 0) {
                    continue;
                }

                if (isset($seen[$addOnId])) {
                    throw ValidationException::withMessages([
                        "add_ons.$index.add_on_id" => 'Each add-on can only be assigned once per product.',
                    ]);
                }
                $seen[$addOnId] = true;

                if (! AddOn::query()->whereKey($addOnId)->exists()) {
                    throw ValidationException::withMessages([
                        "add_ons.$index.add_on_id" => 'Selected add-on does not exist.',
                    ]);
                }

                $override = filled($row['price_override'] ?? null)
                    ? $this->money((string) $row['price_override'])
                    : null;
                $max = filled($row['max_quantity'] ?? null) ? max(1, (int) $row['max_quantity']) : null;

                $assignment = ProductAddOn::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'add_on_id' => $addOnId,
                    ],
                    [
                        'price_override' => $override,
                        'max_quantity' => $max,
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
                    ],
                );

                $this->syncProductAddOnRecipeLines(
                    $assignment,
                    $row['lines'] ?? [],
                    "add_ons.$index.lines",
                );

                $this->syncVariantRecipeOverrides(
                    $product,
                    $assignment,
                    $row['variant_overrides'] ?? [],
                    "add_ons.$index.variant_overrides",
                );

                $keepAddOnIds[] = $addOnId;
            }

            ProductAddOn::query()
                ->where('product_id', $product->id)
                ->when(
                    $keepAddOnIds !== [],
                    fn ($q) => $q->whereNotIn('add_on_id', $keepAddOnIds),
                )
                ->when($keepAddOnIds === [], fn ($q) => $q)
                ->get()
                ->each(fn (ProductAddOn $assignment) => $assignment->delete());
        });

        $this->catalog->flushPublicCache();
    }

    /**
     * Resolve ingredient recipe lines for inventory consumption.
     *
     * Resolution: variant-specific override lines when present, else product-add-on recipe.
     * Global add_on_recipe_lines are never used for consumption.
     *
     * @return Collection<int, ProductAddOnRecipeLine|ProductVariantAddOnRecipeLine>
     */
    public function resolveRecipeLinesForConsumption(Product $product, ?ProductVariant $variant, AddOn $addOn): Collection
    {
        $assignment = ProductAddOn::query()
            ->where('product_id', $product->id)
            ->where('add_on_id', $addOn->id)
            ->first();

        if ($assignment === null) {
            return collect();
        }

        if ($variant !== null) {
            $overrideLines = ProductVariantAddOnRecipeLine::query()
                ->with('ingredient')
                ->where('product_variant_id', $variant->id)
                ->where('product_add_on_id', $assignment->id)
                ->orderBy('sort_order')
                ->get();

            if ($overrideLines->isNotEmpty()) {
                return $overrideLines;
            }
        }

        return ProductAddOnRecipeLine::query()
            ->with('ingredient')
            ->where('product_add_on_id', $assignment->id)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Admin-only cost for a product add-on assignment (optionally variant-scoped).
     *
     * @return array{cost: string, selling_price: string, margin: string}
     */
    public function calculateAssignmentEconomics(ProductAddOn $assignment, ?ProductVariant $variant = null): array
    {
        $assignment->loadMissing(['addOn', 'recipeLines.ingredient']);

        $lines = $variant !== null
            ? $this->resolveRecipeLinesForConsumption($assignment->product ?? Product::query()->findOrFail($assignment->product_id), $variant, $assignment->addOn)
            : $assignment->recipeLines;

        $cost = '0.0000';
        foreach ($lines as $line) {
            $unitCost = $line->ingredient
                ? bcdiv((string) $line->ingredient->cost_per_unit, '1', 4)
                : '0.0000';
            $cost = bcadd($cost, bcmul($unitCost, (string) $line->base_quantity, 4), 4);
        }

        $selling = $assignment->addOn->resolvedPrice(
            $assignment->price_override !== null ? (string) $assignment->price_override : null,
        );

        return [
            'cost' => $cost,
            'selling_price' => $selling,
            'margin' => bcsub($selling, $cost, 4),
        ];
    }

    /**
     * @return list<array{id: int, name: string, description: ?string, price: string, max_quantity: int}>
     */
    public function catalogAddOnsForProduct(Product $product): array
    {
        $product->loadMissing(['addOns' => fn ($q) => $q
            ->where('add_ons.is_active', true)
            ->wherePivot('is_active', true)
            ->orderByPivot('sort_order')]);

        $rows = [];
        foreach ($product->addOns as $addOn) {
            if (! $addOn->is_active || ! (bool) ($addOn->pivot->is_active ?? true)) {
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
            ->wherePivot('is_active', true)
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

            if ($addOn === null || ! $addOn->is_active || ! (bool) ($addOn->pivot->is_active ?? true)) {
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
     * Legacy global add-on recipe sync (kept for historical table maintenance only).
     * New Admin catalog create/edit does not write these lines.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncRecipeLines(AddOn $addOn, array $lines): void
    {
        $prepared = $this->prepareRecipeLines($lines, 'lines');
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
     */
    protected function syncProductAddOnRecipeLines(ProductAddOn $assignment, array $lines, string $errorPrefix): void
    {
        $prepared = $this->prepareRecipeLines($lines, $errorPrefix);
        $keepIds = [];

        foreach ($prepared as $row) {
            if (! empty($row['id'])) {
                $line = ProductAddOnRecipeLine::query()
                    ->where('product_add_on_id', $assignment->id)
                    ->whereKey($row['id'])
                    ->first();
                if ($line) {
                    $line->fill(collect($row)->except('id')->all())->save();
                    $keepIds[] = $line->id;

                    continue;
                }
            }

            $created = $assignment->recipeLines()->create(collect($row)->except('id')->all());
            $keepIds[] = $created->id;
        }

        $assignment->recipeLines()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($q) => $q)
            ->delete();
    }

    /**
     * @param  list<array{product_variant_id: int, lines?: list<array<string, mixed>>}>  $overrides
     */
    protected function syncVariantRecipeOverrides(
        Product $product,
        ProductAddOn $assignment,
        array $overrides,
        string $errorPrefix,
    ): void {
        $keepPairs = [];

        foreach (array_values($overrides) as $index => $override) {
            $variantId = (int) ($override['product_variant_id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }

            if (! ProductVariant::query()->where('product_id', $product->id)->whereKey($variantId)->exists()) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.product_variant_id" => 'Variant does not belong to this product.',
                ]);
            }

            $prepared = $this->prepareRecipeLines(
                $override['lines'] ?? [],
                "{$errorPrefix}.{$index}.lines",
            );

            $keepIngredientIds = [];
            foreach ($prepared as $row) {
                $line = ProductVariantAddOnRecipeLine::query()->updateOrCreate(
                    [
                        'product_variant_id' => $variantId,
                        'product_add_on_id' => $assignment->id,
                        'ingredient_id' => $row['ingredient_id'],
                    ],
                    collect($row)->except(['id', 'ingredient_id'])->all(),
                );
                $keepIngredientIds[] = $line->ingredient_id;
                $keepPairs[] = [$variantId, $line->ingredient_id];
            }

            ProductVariantAddOnRecipeLine::query()
                ->where('product_variant_id', $variantId)
                ->where('product_add_on_id', $assignment->id)
                ->when(
                    $keepIngredientIds !== [],
                    fn ($q) => $q->whereNotIn('ingredient_id', $keepIngredientIds),
                )
                ->when($keepIngredientIds === [], fn ($q) => $q)
                ->delete();
        }

        if ($overrides === []) {
            ProductVariantAddOnRecipeLine::query()
                ->where('product_add_on_id', $assignment->id)
                ->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function prepareRecipeLines(array $lines, string $errorPrefix): array
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
                    "{$errorPrefix}.{$index}.ingredient_id" => 'Only active ingredients can be used.',
                ]);
            }

            if (in_array($ingredientId, $ingredientIds, true)) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.ingredient_id" => 'Duplicate ingredients are not allowed.',
                ]);
            }
            $ingredientIds[] = $ingredientId;

            $measurementUnit = IngredientUnit::from((string) $line['measurement_unit']);
            $baseUnit = $ingredient->base_measurement_unit;
            if (! $baseUnit instanceof IngredientUnit || ! $measurementUnit->supportsBaseUnit($baseUnit)) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.measurement_unit" => 'Selected unit is not compatible with this ingredient.',
                ]);
            }

            $quantity = bcdiv((string) $line['quantity'], '1', 3);
            if (bccomp($quantity, '0', 3) <= 0) {
                throw ValidationException::withMessages([
                    "{$errorPrefix}.{$index}.quantity" => 'Quantity must be greater than zero.',
                ]);
            }

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
