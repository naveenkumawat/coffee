<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\ProductServingUnit;
use App\Models\HomeSection;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\Product\ProductReadinessServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_inactive_product_can_be_saved(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.products.store'), [
                'product_category_id' => $category->id,
                'name' => 'Draft Mocha',
                'product_type' => 'beverage',
                'preparation_station' => 'bar',
                'sort_order' => 1,
                'is_active' => 0,
                'is_available' => 1,
                'is_vegetarian' => 0,
                'is_customizable' => 0,
                'variants' => [
                    [
                        'name' => 'Regular',
                        'serving_size_value' => '300.000',
                        'serving_size_unit' => ProductServingUnit::Milliliter->value,
                        'price' => '0.00',
                        'sort_order' => 1,
                        'is_active' => 1,
                        'is_available' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('name', 'Draft Mocha')->firstOrFail();

        $this->assertFalse($product->is_active);

        $report = app(ProductReadinessServiceInterface::class)->evaluate($product->load(['category', 'variants.recipe.lines.ingredient']));

        $this->assertFalse($report->isReady());
        $this->assertContains('Product image', $report->missing);
        $this->assertTrue(collect($report->missing)->contains(fn (string $item): bool => str_contains($item, 'selling price')));
    }

    public function test_incomplete_product_cannot_become_publicly_active(): void
    {
        Storage::fake('public');

        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'image_path' => null,
            'is_active' => false,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '4.50',
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.products.update', $product), $this->productFormPayload($product, [
                'is_active' => 1,
            ]))
            ->assertSessionHasErrors('is_active');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_valid_product_is_marked_ready_and_can_activate(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/mocha.jpg', 'fake-image');

        $manager = User::factory()->manager()->create();
        $product = $this->makeLaunchReadyProduct([
            'image_path' => 'products/mocha.jpg',
            'is_active' => false,
        ]);

        $report = app(ProductReadinessServiceInterface::class)->evaluate($product->fresh()->load(['category', 'variants.recipe.lines.ingredient']));
        $this->assertTrue($report->isReady());
        $this->assertSame([], $report->missing);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.products.update', $product), $this->productFormPayload($product, [
                'is_active' => 1,
                'image_path' => 'products/mocha.jpg',
            ]))
            ->assertRedirect(route('administrator.products.edit', $product));

        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_missing_image_and_recipe_are_detected(): void
    {
        $product = Product::factory()->create([
            'image_path' => null,
            'is_active' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'price' => '5.00',
            'is_active' => true,
        ]);

        $report = app(ProductReadinessServiceInterface::class)->evaluate($product->load(['category', 'variants.recipe.lines.ingredient']));

        $this->assertFalse($report->isReady());
        $this->assertContains('Product image', $report->missing);
        $this->assertContains('Large: recipe missing', $report->missing);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_inventory_unavailable_is_distinguished_from_configuration_incomplete(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/ready.jpg', 'fake-image');

        $product = $this->makeLaunchReadyProduct([
            'image_path' => 'products/ready.jpg',
            'is_active' => true,
            'is_available' => false,
        ]);

        $ingredient = $product->variants->first()->recipe->lines->first()->ingredient;
        $ingredient->forceFill(['current_stock' => '0.000'])->save();

        $report = app(ProductReadinessServiceInterface::class)->evaluate($product->fresh()->load(['category', 'variants.recipe.lines.ingredient']));

        $this->assertTrue($report->isConfigurationComplete());
        $this->assertSame('Unavailable (paused)', $report->availabilityLabel(false));
        $this->assertTrue($report->hasInventoryConcern());
        $this->assertNotEmpty($report->inventoryNotes);
    }

    public function test_inactive_or_incomplete_homepage_product_is_excluded_publicly(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/ready.jpg', 'fake-image');

        $ready = $this->makeLaunchReadyProduct([
            'name' => 'Ready Latte',
            'image_path' => 'products/ready.jpg',
            'is_active' => true,
            'is_available' => true,
        ]);
        $incomplete = Product::factory()->create([
            'name' => 'Incomplete Frappe',
            'image_path' => null,
            'is_active' => false,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $incomplete->id,
            'price' => '4.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        $section = HomeSection::factory()->create([
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $section->products()->attach($ready->id, ['sort_order' => 1]);
        $section->products()->attach($incomplete->id, ['sort_order' => 2]);

        $this->assertDatabaseHas('home_section_products', [
            'home_section_id' => $section->id,
            'product_id' => $incomplete->id,
        ]);

        $this->getJson(route('api.v1.home.show'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ready Latte'])
            ->assertJsonMissing(['name' => 'Incomplete Frappe']);

        $this->getJson(route('api.v1.catalog.products.index'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ready Latte'])
            ->assertJsonMissing(['name' => 'Incomplete Frappe']);
    }

    public function test_customer_api_does_not_expose_readiness_or_financial_data(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/ready.jpg', 'fake-image');

        $product = $this->makeLaunchReadyProduct([
            'image_path' => 'products/ready.jpg',
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->getJson(route('api.v1.catalog.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonMissingPath('data.readiness')
            ->assertJsonMissingPath('data.missing')
            ->assertJsonMissingPath('data.cost')
            ->assertJsonMissingPath('data.margin')
            ->assertJsonMissingPath('data.variants.0.recipe')
            ->assertJsonMissingPath('data.variants.0.cost');
    }

    public function test_catalog_readiness_command_reuses_service_summary(): void
    {
        Product::factory()->create([
            'name' => 'Incomplete Drink',
            'image_path' => null,
            'is_active' => false,
        ]);

        $this->artisan('coffee:catalog-readiness')
            ->expectsOutputToContain('Incomplete: 1')
            ->expectsOutputToContain('Incomplete Drink')
            ->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeLaunchReadyProduct(array $overrides = []): Product
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(array_merge([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ], $overrides));

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '4.75',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);

        $ingredient = Ingredient::factory()->create([
            'is_active' => true,
            'current_stock' => '100.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $recipe = Recipe::factory()->create([
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        RecipeLine::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => '10.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_quantity' => '10.000',
            'base_measurement_unit' => IngredientUnit::Gram,
            'show_to_customer' => true,
            'customer_label' => 'Espresso',
        ]);

        return $product->fresh(['category', 'variants.recipe.lines.ingredient']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productFormPayload(Product $product, array $overrides = []): array
    {
        $variant = $product->variants()->first() ?? ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        return array_merge([
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'product_type' => $product->product_type?->value ?? 'beverage',
            'preparation_station' => $product->preparation_station?->value ?? 'bar',
            'short_description' => $product->short_description,
            'description' => $product->description,
            'customer_ingredient_summary' => $product->customer_ingredient_summary,
            'image_path' => $product->image_path,
            'preparation_time_minutes' => $product->preparation_time_minutes,
            'sort_order' => $product->sort_order,
            'is_active' => $product->is_active ? 1 : 0,
            'is_available' => $product->is_available ? 1 : 0,
            'is_vegetarian' => $product->is_vegetarian ? 1 : 0,
            'is_customizable' => $product->is_customizable ? 1 : 0,
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'serving_size_value' => $variant->serving_size_value,
                    'serving_size_unit' => $variant->serving_size_unit?->value ?? ProductServingUnit::Milliliter->value,
                    'price' => $variant->price,
                    'sort_order' => $variant->sort_order,
                    'is_active' => $variant->is_active ? 1 : 0,
                    'is_available' => $variant->is_available ? 1 : 0,
                ],
            ],
        ], $overrides);
    }
}
