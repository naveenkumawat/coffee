<?php

namespace Database\Seeders;

use App\Enums\ProductServingUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductTag;
use App\Support\ProductMarketingTags;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        foreach ($this->products() as $productData) {
            $category = ProductCategory::query()->where('name', $productData['category'])->firstOrFail();

            $product = Product::query()->updateOrCreate(
                ['slug' => Str::slug($productData['name'])],
                Arr::except(array_merge([
                    'product_category_id' => $category->getKey(),
                    'image_path' => '/demo/product-placeholder.svg',
                    'is_active' => true,
                    'is_available' => true,
                    'is_featured' => false,
                    'is_new' => false,
                    'is_bestseller' => false,
                    'is_vegetarian' => true,
                    'is_customizable' => false,
                ], $productData), ['category', 'flavours', 'variants', 'tags']),
            );

            $flavourIds = ProductFlavour::query()
                ->whereIn('name', $productData['flavours'])
                ->pluck('id')
                ->all();

            $product->flavours()->sync($flavourIds);

            $tagSlugs = $productData['tags'] ?? [];

            if ($product->is_new) {
                $tagSlugs[] = ProductMarketingTags::NEW;
            }

            if ($product->is_bestseller) {
                $tagSlugs[] = ProductMarketingTags::TOP_SELLER;
            }

            if ($product->is_featured) {
                $tagSlugs[] = ProductMarketingTags::FEATURED;
            }

            $product->tags()->sync(
                ProductTag::query()->whereIn('slug', array_values(array_unique($tagSlugs)))->pluck('id')->all()
            );

            foreach ($productData['variants'] as $variant) {
                $product->variants()->updateOrCreate(
                    ['name' => $variant['name']],
                    array_merge([
                        'is_active' => true,
                        'is_available' => true,
                    ], $variant),
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function products(): array
    {
        $ml = ProductServingUnit::Milliliter->value;

        return [
            [
                'category' => 'Hot Coffee',
                'name' => 'Cafe Latte',
                'sku' => 'LATTE-REG',
                'short_description' => 'Espresso with silky steamed milk.',
                'description' => 'A balanced espresso and milk drink built for everyday cafe service.',
                'customer_ingredient_summary' => 'Espresso, milk',
                'preparation_time_minutes' => 4,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla', 'Hazelnut', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Cappuccino',
                'sku' => 'CAPPUCCINO',
                'short_description' => 'Espresso topped with dense milk foam.',
                'description' => 'Classic cappuccino with a thicker foam layer than latte.',
                'customer_ingredient_summary' => 'Espresso, milk foam',
                'preparation_time_minutes' => 4,
                'sort_order' => 2,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla', 'Hazelnut'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '180.000', 'serving_size_unit' => $ml, 'price' => '4.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Flat White',
                'sku' => 'FLAT-WHITE',
                'short_description' => 'Ristretto-forward espresso with microfoam.',
                'description' => 'Smaller milk drink with a stronger espresso presence.',
                'customer_ingredient_summary' => 'Espresso, steamed milk',
                'preparation_time_minutes' => 3,
                'sort_order' => 3,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '200.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Americano',
                'sku' => 'AMERICANO',
                'short_description' => 'Espresso lengthened with hot water.',
                'description' => 'Clean black coffee option for guests who want espresso clarity.',
                'customer_ingredient_summary' => 'Espresso, hot water',
                'preparation_time_minutes' => 2,
                'sort_order' => 4,
                'is_vegetarian' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '3.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Hazelnut Mocha',
                'sku' => 'HAZ-MOCHA',
                'short_description' => 'Chocolate espresso with hazelnut syrup.',
                'description' => 'House mocha finished with hazelnut for a dessert-leaning hot drink.',
                'customer_ingredient_summary' => 'Espresso, milk, chocolate, hazelnut',
                'preparation_time_minutes' => 5,
                'sort_order' => 5,
                'is_featured' => true,
                'is_customizable' => true,
                'flavours' => ['Hazelnut', 'Mocha'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '400.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Iced Vanilla Latte',
                'sku' => 'ICED-VAN-LATTE',
                'short_description' => 'Espresso, milk, and vanilla over ice.',
                'description' => 'A refreshing chilled latte with a clean vanilla finish.',
                'customer_ingredient_summary' => 'Espresso, milk, vanilla, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '450.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Iced Americano',
                'sku' => 'ICED-AMERICANO',
                'short_description' => 'Espresso over cold water and ice.',
                'description' => 'Bright iced black coffee for all-day service.',
                'customer_ingredient_summary' => 'Espresso, water, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 2,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.00', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Cold Brew',
                'sku' => 'COLD-BREW',
                'short_description' => 'Slow-steeped coffee served over ice.',
                'description' => 'Smooth cold brew with lower perceived acidity.',
                'customer_ingredient_summary' => 'Cold brew concentrate, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 3,
                'is_bestseller' => true,
                'flavours' => ['Vanilla', 'Caramel', 'Classic'],
                'tags' => ['popular'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Brew',
                'name' => 'Classic Cold Brew',
                'sku' => 'CB-CLASSIC',
                'short_description' => 'House cold brew concentrate over ice.',
                'description' => 'DEMO cold-brew category drink for filter testing.',
                'customer_ingredient_summary' => 'Cold brew, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 1,
                'is_featured' => true,
                'flavours' => ['Classic'],
                'tags' => ['recommended'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Brew',
                'name' => 'Honey Lime Cold Brew',
                'sku' => 'CB-HONEY-LIME',
                'short_description' => 'Cold brew with honey and citrus.',
                'description' => 'Bright honey-lime twist on cold brew.',
                'customer_ingredient_summary' => 'Cold brew, honey, lime, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 2,
                'is_new' => true,
                'flavours' => ['Honey'],
                'tags' => ['seasonal', 'new'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Brew',
                'name' => 'Cold Brew Tonic',
                'sku' => 'CB-TONIC',
                'short_description' => 'Cold brew lengthened with tonic.',
                'description' => 'Sparkling tonic cold brew for afternoon service.',
                'customer_ingredient_summary' => 'Cold brew, tonic, ice',
                'preparation_time_minutes' => 2,
                'sort_order' => 3,
                'flavours' => ['Classic'],
                'tags' => ['limited'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Caramel Iced Latte',
                'sku' => 'CARAMEL-ICED',
                'short_description' => 'Iced latte finished with caramel syrup.',
                'description' => 'Sweet iced latte built for flavour-forward guests.',
                'customer_ingredient_summary' => 'Espresso, milk, caramel, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 4,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '450.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Mocha Frappe',
                'sku' => 'MOCHA-FRAPPE',
                'short_description' => 'Blended coffee, chocolate, and ice.',
                'description' => 'A dessert-style frappe topped with rich mocha flavour.',
                'customer_ingredient_summary' => 'Coffee, milk, mocha, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 1,
                'is_featured' => true,
                'is_bestseller' => true,
                'is_customizable' => true,
                'flavours' => ['Mocha', 'Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Vanilla Bean Frappe',
                'sku' => 'VAN-FRAPPE',
                'short_description' => 'Blended vanilla coffee shake.',
                'description' => 'Creamy vanilla frappe with espresso backbone.',
                'customer_ingredient_summary' => 'Coffee, milk, vanilla, ice cream, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 2,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Caramel Crunch Frappe',
                'sku' => 'CARAMEL-FRAPPE',
                'short_description' => 'Caramel frappe with whipped finish.',
                'description' => 'Blended caramel drink finished with whipped cream.',
                'customer_ingredient_summary' => 'Coffee, milk, caramel, whipped cream, ice',
                'preparation_time_minutes' => 6,
                'sort_order' => 3,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Classic Masala Chai',
                'sku' => 'MASALA-CHAI',
                'short_description' => 'Spiced black tea with milk.',
                'description' => 'House chai brewed for pickup guests who want a non-coffee option.',
                'customer_ingredient_summary' => 'Tea, milk, spices, sugar',
                'preparation_time_minutes' => 5,
                'sort_order' => 1,
                'is_bestseller' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '3.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '4.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Matcha Latte',
                'sku' => 'MATCHA-LATTE',
                'short_description' => 'Whisked matcha with steamed milk.',
                'description' => 'Bright green tea latte available hot for cafe pickup.',
                'customer_ingredient_summary' => 'Matcha, milk',
                'preparation_time_minutes' => 4,
                'sort_order' => 2,
                'is_featured' => true,
                'is_new' => true,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '400.000', 'serving_size_unit' => $ml, 'price' => '6.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Tea & Matcha',
                'name' => 'Iced Matcha Latte',
                'sku' => 'ICED-MATCHA',
                'short_description' => 'Chilled matcha latte over ice.',
                'description' => 'Refreshing iced matcha with lightly sweetened milk.',
                'customer_ingredient_summary' => 'Matcha, milk, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 3,
                'is_customizable' => true,
                'flavours' => ['Vanilla'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '6.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Pastries',
                'name' => 'Butter Croissant',
                'sku' => 'CROISSANT',
                'short_description' => 'Flaky butter croissant.',
                'description' => 'DEMO pastry intentionally missing image for readiness filters.',
                'customer_ingredient_summary' => 'Wheat flour, butter',
                'preparation_time_minutes' => 1,
                'sort_order' => 1,
                'is_vegetarian' => true,
                'image_path' => null,
                'flavours' => [],
                'tags' => ['popular'],
                'variants' => [
                    ['name' => 'Single', 'serving_size_value' => '1.000', 'serving_size_unit' => ProductServingUnit::Piece->value, 'price' => '3.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Pastries',
                'name' => 'Chocolate Muffin',
                'sku' => 'CHOC-MUFFIN',
                'short_description' => 'Rich chocolate muffin.',
                'description' => 'Soft muffin with chocolate chips for grab-and-go guests.',
                'customer_ingredient_summary' => 'Wheat flour, chocolate, eggs, sugar',
                'preparation_time_minutes' => 1,
                'sort_order' => 2,
                'is_new' => true,
                'is_vegetarian' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Single', 'serving_size_value' => '1.000', 'serving_size_unit' => ProductServingUnit::Piece->value, 'price' => '3.75', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Espresso',
                'sku' => 'ESPRESSO',
                'short_description' => 'Short concentrated espresso shot.',
                'description' => 'Single or double espresso for espresso lovers.',
                'customer_ingredient_summary' => 'Espresso',
                'preparation_time_minutes' => 2,
                'sort_order' => 0,
                'flavours' => ['Classic'],
                'variants' => [
                    ['name' => 'Single', 'serving_size_value' => '30.000', 'serving_size_unit' => $ml, 'price' => '2.50', 'sort_order' => 1],
                    ['name' => 'Double', 'serving_size_value' => '60.000', 'serving_size_unit' => $ml, 'price' => '3.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Mocha',
                'sku' => 'MOCHA',
                'short_description' => 'Espresso, steamed milk, and chocolate.',
                'description' => 'Classic cafe mocha.',
                'customer_ingredient_summary' => 'Espresso, milk, chocolate',
                'preparation_time_minutes' => 4,
                'sort_order' => 6,
                'is_customizable' => true,
                'flavours' => ['Mocha', 'Vanilla'],
                'tags' => ['popular', 'recommended'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '400.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Hazelnut Latte',
                'sku' => 'HAZ-LATTE',
                'short_description' => 'Latte finished with hazelnut syrup.',
                'description' => 'Nutty everyday latte.',
                'customer_ingredient_summary' => 'Espresso, milk, hazelnut',
                'preparation_time_minutes' => 4,
                'sort_order' => 7,
                'is_customizable' => true,
                'flavours' => ['Hazelnut'],
                'tags' => ['recommended'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Caramel Latte',
                'sku' => 'CAR-LATTE',
                'short_description' => 'Latte finished with caramel syrup.',
                'description' => 'Sweet caramel milk espresso.',
                'customer_ingredient_summary' => 'Espresso, milk, caramel',
                'preparation_time_minutes' => 4,
                'sort_order' => 8,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.25', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Irish Latte',
                'sku' => 'IRISH-LATTE',
                'short_description' => 'Latte with Irish-style syrup (non-alcoholic).',
                'description' => 'DEMO specialty latte.',
                'customer_ingredient_summary' => 'Espresso, milk, Irish syrup',
                'preparation_time_minutes' => 4,
                'sort_order' => 9,
                'is_customizable' => true,
                'flavours' => ['Irish'],
                'tags' => ['seasonal', 'limited'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Iced Latte',
                'sku' => 'ICED-LATTE',
                'short_description' => 'Espresso and milk over ice.',
                'description' => 'Clean iced latte without flavour syrup.',
                'customer_ingredient_summary' => 'Espresso, milk, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 0,
                'is_customizable' => true,
                'flavours' => ['Classic', 'Vanilla'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Classic Cold Coffee',
                'sku' => 'CLASSIC-CC',
                'short_description' => 'Blended cafe-style cold coffee.',
                'description' => 'Sweetened cold coffee shake.',
                'customer_ingredient_summary' => 'Coffee, milk, sugar, ice',
                'preparation_time_minutes' => 4,
                'sort_order' => 5,
                'is_bestseller' => true,
                'flavours' => ['Classic'],
                'tags' => ['popular'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Cold Coffee',
                'name' => 'Dark Cold Coffee',
                'sku' => 'DARK-CC',
                'short_description' => 'Stronger cold coffee with less milk.',
                'description' => 'Bold iced coffee for espresso fans.',
                'customer_ingredient_summary' => 'Coffee, milk, ice',
                'preparation_time_minutes' => 4,
                'sort_order' => 6,
                'flavours' => ['Classic'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Hazelnut Frappe',
                'sku' => 'HAZ-FRAPPE',
                'short_description' => 'Blended hazelnut coffee shake.',
                'description' => 'Nutty frappe build.',
                'customer_ingredient_summary' => 'Coffee, milk, hazelnut, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 4,
                'is_customizable' => true,
                'flavours' => ['Hazelnut'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.50', 'sort_order' => 1],
                    ['name' => 'Large', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '7.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Frappes',
                'name' => 'Strawberry Frappe',
                'sku' => 'STRAW-FRAPPE',
                'short_description' => 'Blended strawberry cream frappe.',
                'description' => 'Fruit-forward frappe for non-coffee guests.',
                'customer_ingredient_summary' => 'Milk, strawberry, ice',
                'preparation_time_minutes' => 5,
                'sort_order' => 5,
                'is_new' => true,
                'flavours' => ['Strawberry'],
                'tags' => ['seasonal', 'new', 'featured'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '350.000', 'serving_size_unit' => $ml, 'price' => '6.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Mojitos / Refreshers',
                'name' => 'Virgin Mojito',
                'sku' => 'VIRGIN-MOJITO',
                'short_description' => 'Mint lime soda refresher.',
                'description' => 'Non-alcoholic classic mojito cooler.',
                'customer_ingredient_summary' => 'Lime, mint, soda, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 1,
                'is_featured' => true,
                'flavours' => ['Classic'],
                'tags' => ['popular'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.50', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Mojitos / Refreshers',
                'name' => 'Strawberry Mojito',
                'sku' => 'STRAW-MOJITO',
                'short_description' => 'Strawberry mint cooler.',
                'description' => 'Berry twist on the house mojito.',
                'customer_ingredient_summary' => 'Strawberry, lime, mint, soda, ice',
                'preparation_time_minutes' => 4,
                'sort_order' => 2,
                'flavours' => ['Strawberry'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Mojitos / Refreshers',
                'name' => 'Blueberry Mojito',
                'sku' => 'BLUE-MOJITO',
                'short_description' => 'Blueberry mint cooler.',
                'description' => 'Berry cooler for warm-weather service.',
                'customer_ingredient_summary' => 'Blueberry, lime, mint, soda, ice',
                'preparation_time_minutes' => 4,
                'sort_order' => 3,
                'flavours' => ['Blueberry'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.95', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Mojitos / Refreshers',
                'name' => 'Mix Berry Lemonade',
                'sku' => 'MIX-BERRY-LEM',
                'short_description' => 'Mixed berry lemonade cooler.',
                'description' => 'Citrus berry refresher.',
                'customer_ingredient_summary' => 'Berry mix, lemon, soda, ice',
                'preparation_time_minutes' => 3,
                'sort_order' => 4,
                'is_new' => true,
                'flavours' => ['Strawberry', 'Blueberry'],
                'tags' => ['new', 'seasonal'],
                'variants' => [
                    ['name' => '300 ml', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '4.75', 'sort_order' => 1],
                    ['name' => '500 ml', 'serving_size_value' => '500.000', 'serving_size_unit' => $ml, 'price' => '5.75', 'sort_order' => 2],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Seasonal Spice Latte',
                'sku' => 'SEASONAL-SPICE',
                'short_description' => 'Limited seasonal latte (currently unavailable).',
                'description' => 'Kept in catalog for unavailable-state UI testing.',
                'customer_ingredient_summary' => 'Espresso, milk, spice syrup',
                'preparation_time_minutes' => 4,
                'sort_order' => 99,
                'is_available' => false,
                'is_customizable' => true,
                'flavours' => ['Caramel'],
                'tags' => ['seasonal', 'limited'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '300.000', 'serving_size_unit' => $ml, 'price' => '5.95', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Draft Rose Latte',
                'sku' => 'DRAFT-ROSE',
                'short_description' => 'Inactive draft product without a public recipe.',
                'description' => 'DEMO incomplete product for readiness filters (inactive + no recipe + no image).',
                'customer_ingredient_summary' => null,
                'preparation_time_minutes' => 4,
                'sort_order' => 98,
                'is_active' => false,
                'is_available' => false,
                'image_path' => null,
                'flavours' => ['Rose'],
                'tags' => ['limited'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '5.50', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Paused Cortado',
                'sku' => 'PAUSED-CORTADO',
                'short_description' => 'Configured but paused from sale.',
                'description' => 'DEMO paused product: active=false, recipe present for readiness testing.',
                'customer_ingredient_summary' => 'Espresso, milk',
                'preparation_time_minutes' => 3,
                'sort_order' => 97,
                'is_active' => false,
                'is_available' => true,
                'flavours' => ['Classic'],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '120.000', 'serving_size_unit' => $ml, 'price' => '4.25', 'sort_order' => 1],
                ],
            ],
            [
                'category' => 'Hot Coffee',
                'name' => 'Sugar-Free Americano Special',
                'sku' => 'SUGAR-AMERICANO',
                'short_description' => 'Uses out-of-stock sweetener (stock concern demo).',
                'description' => 'DEMO product linked to White Sugar (out of stock) for inventory readiness testing.',
                'customer_ingredient_summary' => 'Espresso, water, sugar',
                'preparation_time_minutes' => 2,
                'sort_order' => 96,
                'is_active' => true,
                'is_available' => true,
                'flavours' => [],
                'variants' => [
                    ['name' => 'Regular', 'serving_size_value' => '250.000', 'serving_size_unit' => $ml, 'price' => '3.95', 'sort_order' => 1],
                ],
            ],
        ];
    }
}
