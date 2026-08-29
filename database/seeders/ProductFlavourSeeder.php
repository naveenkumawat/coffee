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
            ['name' => 'Vanilla', 'description' => 'Classic sweet vanilla pairing.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
            ['name' => 'Hazelnut', 'description' => 'Nutty syrup profile for espresso and iced drinks.', 'categories' => ['Hot Coffee', 'Cold Coffee']],
            ['name' => 'Caramel', 'description' => 'Rich caramel finish for signature beverages.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
            ['name' => 'Mocha', 'description' => 'Chocolate-driven profile for dessert coffee drinks.', 'categories' => ['Hot Coffee', 'Cold Coffee', 'Frappes']],
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
