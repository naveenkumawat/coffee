<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_with_menu_content(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Signature Coffee',
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Citrus Espresso Tonic',
            'is_featured' => true,
        ]);
        $product->variants()->create([
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter->value,
            'price' => '5.25',
            'sort_order' => 1,
            'is_active' => true,
            'is_available' => true,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Laravel 13 Cafe Foundation')
            ->assertSee('Signature Coffee')
            ->assertSee('Citrus Espresso Tonic');
    }
}
