<?php

namespace App\Services\Product;

use App\Enums\IngredientUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Support\PublicMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReadinessService implements ProductReadinessServiceInterface
{
    public function evaluate(Product $product): ProductReadinessReport
    {
        $product->loadMissing([
            'category',
            'variants.recipe.lines.ingredient',
        ]);

        $missing = [];
        $inventoryNotes = [];

        if (! filled($product->name)) {
            $missing[] = 'Product name';
        }

        if (! $product->product_category_id || ! $product->category) {
            $missing[] = 'Category';
        } elseif (! $product->category->is_active) {
            $missing[] = 'Category is inactive';
        }

        $activeVariants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => (bool) $variant->is_active)
            ->values();

        if ($activeVariants->isEmpty()) {
            $missing[] = 'At least one active variant';
        }

        foreach ($activeVariants as $variant) {
            $label = filled($variant->name) ? (string) $variant->name : 'Unnamed variant';

            if (! filled($variant->name)) {
                $missing[] = "{$label}: missing label/name";
            }

            if (! $this->hasValidPrice($variant)) {
                $missing[] = "{$label}: selling price missing or invalid";
            }

            $recipeIssues = $this->recipeIssuesForVariant($variant);
            foreach ($recipeIssues as $issue) {
                $missing[] = "{$label}: {$issue}";
            }

            foreach ($this->inventoryNotesForVariant($variant) as $note) {
                $inventoryNotes[] = "{$label}: {$note}";
            }
        }

        if (! $this->hasUsableImage($product)) {
            $missing[] = 'Product image';
        }

        return new ProductReadinessReport(
            missing: array_values(array_unique($missing)),
            inventoryNotes: array_values(array_unique($inventoryNotes)),
        );
    }

    public function evaluateMany(Collection $products): Collection
    {
        return $products->mapWithKeys(
            fn (Product $product): array => [(int) $product->getKey() => $this->evaluate($product)],
        );
    }

    public function assertCanBeActive(Product $product): void
    {
        if (! $product->is_active) {
            return;
        }

        $report = $this->evaluate($product);

        if ($report->isConfigurationComplete()) {
            return;
        }

        $lines = collect($report->missing)
            ->map(fn (string $item): string => "- {$item}")
            ->implode("\n");

        throw ValidationException::withMessages([
            'is_active' => "Cannot activate product:\n{$lines}",
        ]);
    }

    public function incompleteProducts(): array
    {
        $items = [];

        Product::query()
            ->with(['category', 'variants.recipe.lines.ingredient'])
            ->orderBy('name')
            ->each(function (Product $product) use (&$items): void {
                $report = $this->evaluate($product);

                if ($report->isConfigurationComplete()) {
                    return;
                }

                $items[] = [
                    'product' => $product,
                    'report' => $report,
                ];
            });

        return $items;
    }

    public function catalogSummary(): array
    {
        $total = 0;
        $ready = 0;
        $incompleteItems = [];

        Product::query()
            ->with(['category', 'variants.recipe.lines.ingredient'])
            ->orderBy('name')
            ->each(function (Product $product) use (&$total, &$ready, &$incompleteItems): void {
                $total++;
                $report = $this->evaluate($product);

                if ($report->isConfigurationComplete()) {
                    $ready++;

                    return;
                }

                $incompleteItems[] = [
                    'name' => (string) $product->name,
                    'missing' => $report->missing,
                ];
            });

        return [
            'total' => $total,
            'ready' => $ready,
            'incomplete' => $total - $ready,
            'items' => $incompleteItems,
        ];
    }

    protected function hasValidPrice(ProductVariant $variant): bool
    {
        if ($variant->price === null || $variant->price === '') {
            return false;
        }

        return bccomp((string) $variant->price, '0', 2) > 0;
    }

    /**
     * @return list<string>
     */
    protected function recipeIssuesForVariant(ProductVariant $variant): array
    {
        $recipe = $variant->recipe;

        if (! $recipe instanceof Recipe || ! $recipe->is_active) {
            return ['recipe missing'];
        }

        $lines = $recipe->lines;

        if ($lines->isEmpty()) {
            return ['recipe has no ingredients'];
        }

        $issues = [];
        $seenIngredientIds = [];

        foreach ($lines as $index => $line) {
            /** @var RecipeLine $line */
            $ingredient = $line->ingredient;

            if (! $ingredient) {
                $issues[] = 'recipe references a missing ingredient';

                continue;
            }

            if (! $ingredient->is_active) {
                $issues[] = "ingredient \"{$ingredient->name}\" is inactive";
            }

            $ingredientId = (int) $ingredient->getKey();

            if (in_array($ingredientId, $seenIngredientIds, true)) {
                $issues[] = "duplicate ingredient \"{$ingredient->name}\"";
            }

            $seenIngredientIds[] = $ingredientId;

            if (bccomp((string) $line->quantity, '0', 3) <= 0) {
                $issues[] = "ingredient \"{$ingredient->name}\" quantity must be greater than 0";
            }

            $measurementUnit = $line->measurement_unit instanceof IngredientUnit
                ? $line->measurement_unit
                : IngredientUnit::tryFrom((string) $line->measurement_unit);

            $baseUnit = $ingredient->base_measurement_unit instanceof IngredientUnit
                ? $ingredient->base_measurement_unit
                : IngredientUnit::tryFrom((string) $ingredient->base_measurement_unit);

            if (! $measurementUnit instanceof IngredientUnit || ! $baseUnit instanceof IngredientUnit) {
                $issues[] = "ingredient \"{$ingredient->name}\" has an unsupported unit";
            } elseif (! $measurementUnit->supportsBaseUnit($baseUnit)) {
                $issues[] = "ingredient \"{$ingredient->name}\" unit is incompatible";
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * Informational only — does not invent new sellability rules.
     *
     * @return list<string>
     */
    protected function inventoryNotesForVariant(ProductVariant $variant): array
    {
        $recipe = $variant->recipe;

        if (! $recipe instanceof Recipe) {
            return [];
        }

        $notes = [];

        foreach ($recipe->lines as $line) {
            $ingredient = $line->ingredient;

            if (! $ingredient) {
                continue;
            }

            if (bccomp((string) $ingredient->current_stock, '0', 3) <= 0) {
                $notes[] = "ingredient \"{$ingredient->name}\" is out of stock";
            }
        }

        return $notes;
    }

    protected function hasUsableImage(Product $product): bool
    {
        $path = filled($product->image_path) ? trim((string) $product->image_path) : '';

        if ($path === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return true;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (PublicMedia::isManagedRelativePath($normalized)) {
            return Storage::disk(PublicMedia::disk())->exists($normalized);
        }

        // Site-relative or external-style paths are accepted when non-empty.
        return true;
    }
}
