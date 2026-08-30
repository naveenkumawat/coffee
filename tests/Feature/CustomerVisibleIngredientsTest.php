<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\ProductServingUnit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerVisibleIngredientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_product_api_exposes_only_marked_major_ingredients(): void
    {
        [$product, $variant] = $this->makeProductWithRecipe();

        $response = $this->getJson(route('api.v1.catalog.products.show', $product))
            ->assertOk();

        $variantPayload = collect($response->json('data.variants'))
            ->firstWhere('id', $variant->id);

        $this->assertNotNull($variantPayload);
        $this->assertSame(
            ['Espresso', 'Vanilla'],
            collect($variantPayload['major_ingredients'])->pluck('label')->all(),
        );

        $encoded = json_encode($response->json());
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('Monin Vanilla Syrup 1L', $encoded);
        $this->assertStringNotContainsString('Cubed Ice', $encoded);
        $this->assertStringNotContainsString('production_cost', $encoded);
        $this->assertStringNotContainsString('base_quantity', $encoded);
        $this->assertStringNotContainsString('preparation_notes', $encoded);
        $this->assertStringNotContainsString('Secret barista build steps', $encoded);
    }

    public function test_hidden_recipe_ingredients_never_leak_on_catalog_list(): void
    {
        [$product] = $this->makeProductWithRecipe();

        $response = $this->getJson(route('api.v1.catalog.products.index'))
            ->assertOk();

        $productPayload = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($productPayload);

        $labels = collect($productPayload['variants'] ?? [])
            ->flatMap(fn (array $variant) => collect($variant['major_ingredients'] ?? [])->pluck('label'))
            ->all();

        $this->assertContains('Espresso', $labels);
        $this->assertContains('Vanilla', $labels);
        $this->assertNotContains('Cubed Ice', $labels);
        $this->assertNotContains('Monin Vanilla Syrup 1L', $labels);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    protected function makeProductWithRecipe(): array
    {
        $category = ProductCategory::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Iced Vanilla Latte',
            'is_active' => true,
            'is_available' => true,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '5.50',
            'serving_size_value' => '350',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);

        $espresso = Ingredient::factory()->create(['name' => 'Davidoff Espresso']);
        $vanilla = Ingredient::factory()->create(['name' => 'Monin Vanilla Syrup 1L']);
        $ice = Ingredient::factory()->create(['name' => 'Cubed Ice']);

        $recipe = Recipe::factory()->create([
            'product_variant_id' => $variant->id,
            'preparation_notes' => 'Secret barista build steps',
            'is_active' => true,
        ]);

        RecipeLine::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $espresso->id,
            'quantity' => '18.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_quantity' => '18.000',
            'base_measurement_unit' => IngredientUnit::Gram,
            'sort_order' => 1,
            'show_to_customer' => true,
            'customer_label' => 'Espresso',
        ]);

        RecipeLine::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $vanilla->id,
            'quantity' => '0.020',
            'measurement_unit' => IngredientUnit::Bottle,
            'base_quantity' => '0.020',
            'base_measurement_unit' => IngredientUnit::Bottle,
            'sort_order' => 2,
            'show_to_customer' => true,
            'customer_label' => 'Vanilla',
        ]);

        RecipeLine::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ice->id,
            'quantity' => '120.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_quantity' => '120.000',
            'base_measurement_unit' => IngredientUnit::Gram,
            'sort_order' => 3,
            'show_to_customer' => false,
            'customer_label' => null,
        ]);

        return [$product, $variant];
    }
}
