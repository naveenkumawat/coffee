<?php

namespace Database\Seeders;

use App\Models\IngredientBrand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IngredientBrandSeeder extends Seeder
{
    public const BRANDS = [
        'Nescafé' => 'Instant coffee brand used for core quick-service beverages.',
        'Davidoff' => 'Premium coffee brand used for signature espresso-forward drinks.',
        'Amul' => 'Primary dairy brand for milk-based beverage production.',
        'Vadilal' => 'Frozen dessert brand used for shake and frappe preparations.',
        'Monin' => 'Flavor syrup brand used across hot and cold beverage builds.',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach (self::BRANDS as $name => $description) {
            $brand = IngredientBrand::query()->withTrashed()->firstOrNew([
                'slug' => Str::slug($name),
            ]);

            $brand->fill([
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);
            $brand->deleted_at = null;
            $brand->save();
        }
    }
}
