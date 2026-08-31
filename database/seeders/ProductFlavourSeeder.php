<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductFlavourSeeder extends Seeder
{
    public function run(): void
    {
        $flavours = [
            ['name' => 'Vanilla', 'description' => 'Classic sweet vanilla pairing.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Cold Brew', 'Frappes', 'Tea & Matcha']],
            ['name' => 'Hazelnut', 'description' => 'Nutty syrup profile for espresso and iced drinks.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
            ['name' => 'Caramel', 'description' => 'Rich caramel finish for signature beverages.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
            ['name' => 'Mocha', 'description' => 'Chocolate-driven profile for dessert coffee drinks.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
            ['name' => 'Irish', 'description' => 'Warm Irish-style syrup profile (non-alcoholic).', 'categories' => ['Hot Coffee', 'Cold Coffee']],
            ['name' => 'Strawberry', 'description' => 'Bright berry syrup for frappes and refreshers.', 'categories' => ['Frappes', 'Mojitos / Refreshers']],
            ['name' => 'Blueberry', 'description' => 'Berry profile for coolers and frappes.', 'categories' => ['Frappes', 'Mojitos / Refreshers']],
            ['name' => 'Rose', 'description' => 'Floral syrup finish for specialty drinks.', 'categories' => ['Tea & Matcha', 'Frappes']],
            ['name' => 'Honey', 'description' => 'Light honey sweetness for tea, matcha, and cold brew.', 'categories' => ['Tea & Matcha', 'Cold Brew']],
            ['name' => 'Classic', 'description' => 'House classic profile with no extra syrup.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Cold Brew', 'Mojitos / Refreshers']],
        ];

        foreach ($flavours as $flavourData) {
            $flavour = ProductFlavour::query()->updateOrCreate(
                ['slug' => Str::slug($flavourData['name'])],
                [
                    'name' => $flavourData['name'],
                    'description' => $flavourData['description'],
                    'image_path' => null,
                    'is_active' => true,
                ],
            );

            $categoryIds = ProductCategory::query()
                ->whereIn('name', $flavourData['categories'])
                ->pluck('id')
                ->all();

            $flavour->categories()->sync($categoryIds);
        }
    }
}
