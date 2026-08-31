<?php

namespace Database\Seeders;

use App\Models\IngredientCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Structural inventory taxonomy — safe for every environment.
 *
 * Café-specific brands/ingredients/stock live in DemoSeeder only.
 */
class IngredientCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Coffee',
        'Milk',
        'Ice Cream',
        'Syrups',
        'Sauces',
        'Powders',
        'Fruits',
        'Toppings',
        'Sweeteners',
        'Tea',
        'Matcha',
        'Ice',
        'Carbonated Beverages',
        'Packaging',
        'Miscellaneous',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            $category = IngredientCategory::query()->withTrashed()->firstOrNew([
                'slug' => Str::slug($name),
            ]);

            $category->fill([
                'name' => $name,
                'description' => $this->defaultDescription($name),
                'is_active' => true,
            ]);
            $category->deleted_at = null;
            $category->save();
        }
    }

    protected function defaultDescription(string $name): string
    {
        return match ($name) {
            'Coffee' => 'Roasted coffee beans, instant coffee, and espresso bases.',
            'Milk' => 'Dairy and non-dairy milk used for beverages and desserts.',
            'Ice Cream' => 'Frozen dessert bases used for shakes, frappes, and specials.',
            'Syrups' => 'Sweet liquid flavoring syrups for beverages and toppings.',
            'Sauces' => 'Drizzle and flavor sauces used across drinks and desserts.',
            'Powders' => 'Powdered beverage mixes and flavor enhancers.',
            'Fruits' => 'Fresh, frozen, or preserved fruits used in recipes.',
            'Toppings' => 'Whipped toppings, chips, sprinkles, and finishing garnishes.',
            'Sweeteners' => 'Sugar, jaggery, sugar-free, and alternative sweeteners.',
            'Tea' => 'Black, green, herbal, and specialty tea ingredients.',
            'Matcha' => 'Matcha powder and closely related preparation ingredients.',
            'Ice' => 'Ice stock used for cold beverages and shakes.',
            'Carbonated Beverages' => 'Soda and sparkling beverage bases.',
            'Packaging' => 'Cups, lids, sleeves, straws, and takeaway supplies.',
            default => 'General purpose ingredient stock for café operations.',
        };
    }
}
