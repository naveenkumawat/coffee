<?php

namespace App\Support;

use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use Illuminate\Support\Collection;

class CustomerVisibleIngredients
{
    /**
     * @return list<array{id: int, label: string}>
     */
    public static function forVariant(?ProductVariant $variant): array
    {
        if (! $variant) {
            return [];
        }

        $recipe = $variant->relationLoaded('recipe')
            ? $variant->recipe
            : $variant->recipe()->with(['lines' => fn ($query) => $query->where('show_to_customer', true)->orderBy('sort_order')->orderBy('id'), 'lines.ingredient'])->first();

        return self::fromRecipe($recipe);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function fromRecipe(?Recipe $recipe): array
    {
        if (! $recipe) {
            return [];
        }

        /** @var Collection<int, RecipeLine> $lines */
        $lines = $recipe->relationLoaded('lines')
            ? $recipe->lines
            : $recipe->lines()->where('show_to_customer', true)->orderBy('sort_order')->orderBy('id')->with('ingredient')->get();

        $seen = [];
        $items = [];

        foreach ($lines as $line) {
            if (! $line->show_to_customer) {
                continue;
            }

            $label = filled($line->customer_label)
                ? trim((string) $line->customer_label)
                : trim((string) ($line->ingredient?->name ?? ''));

            if ($label === '') {
                continue;
            }

            $key = mb_strtolower($label);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = [
                'id' => (int) $line->getKey(),
                'label' => $label,
            ];
        }

        return $items;
    }
}
