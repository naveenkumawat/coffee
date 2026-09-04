<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Models\AddOn;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddOn;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductVariantAddOnRecipeLine;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Services\AddOn\AddOnServiceInterface;
use App\Services\OrderInventory\OrderInventoryConsumptionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantAddOnAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_product_with_one_variant_without_empty_placeholders(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.products.store'), $this->productPayload($category->id, [
                [
                    'name' => 'Single',
                    'serving_size_value' => '200',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '99.00',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ]))
            ->assertRedirect();

        $product = Product::query()->where('name', 'Solo Latte')->firstOrFail();
        $this->assertCount(1, $product->variants);
        $this->assertSame('solo-latte', $product->slug);
    }

    public function test_manager_can_create_product_with_four_variants(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $variants = [];
        foreach (['XS', 'S', 'M', 'L'] as $i => $name) {
            $variants[] = [
                'name' => $name,
                'serving_size_value' => (string) (100 + ($i * 50)),
                'serving_size_unit' => ProductServingUnit::Milliliter->value,
                'price' => (string) (50 + ($i * 10)).'.00',
                'sort_order' => $i + 1,
                'is_active' => 1,
                'is_available' => 1,
            ];
        }

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.products.store'), $this->productPayload($category->id, $variants))
            ->assertRedirect();

        $this->assertCount(4, Product::query()->where('name', 'Solo Latte')->firstOrFail()->variants);
    }

    public function test_edit_preserves_variant_ids_and_ignores_blank_extra_rows(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Edit Me',
            'slug' => 'edit-me',
            'is_active' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '80.00',
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.products.update', $product), $this->productPayload($category->id, [
                [
                    'id' => $variant->id,
                    'name' => 'Regular',
                    'serving_size_value' => '260',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '85.00',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
                [
                    'name' => '',
                    'serving_size_value' => '',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '',
                ],
            ], name: 'Edit Me'))
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'name' => 'Regular',
            'price' => '85.00',
        ]);
        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertSame('edit-me', $product->fresh()->slug);
    }

    public function test_add_on_catalog_create_does_not_require_recipe_and_generates_slug(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.add-ons.store'), [
                'name' => 'Extra Foam',
                'default_price' => '12.50',
                'is_active' => 1,
                'sort_order' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('add_ons', [
            'name' => 'Extra Foam',
            'slug' => 'extra-foam',
            'default_price' => '12.50',
        ]);
        $this->assertDatabaseCount('add_on_recipe_lines', 0);
    }

    public function test_same_add_on_can_have_different_product_price_and_recipe(): void
    {
        $ingredient = Ingredient::factory()->create([
            'is_active' => true,
            'cost_per_unit' => '2.0000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);
        $addOn = AddOn::factory()->create(['default_price' => '20.00', 'is_active' => true]);
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $service = app(AddOnServiceInterface::class);
        $service->syncProductAssignments($productA, [[
            'add_on_id' => $addOn->id,
            'price_override' => '30.00',
            'lines' => [[
                'ingredient_id' => $ingredient->id,
                'quantity' => '8.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ]],
        ]]);
        $service->syncProductAssignments($productB, [[
            'add_on_id' => $addOn->id,
            'price_override' => '35.00',
            'lines' => [[
                'ingredient_id' => $ingredient->id,
                'quantity' => '10.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ]],
        ]]);

        $catalogA = $service->catalogAddOnsForProduct($productA->fresh());
        $catalogB = $service->catalogAddOnsForProduct($productB->fresh());
        $this->assertSame('30.00', $catalogA[0]['price']);
        $this->assertSame('35.00', $catalogB[0]['price']);

        $assignmentA = ProductAddOn::query()->where('product_id', $productA->id)->firstOrFail();
        $assignmentB = ProductAddOn::query()->where('product_id', $productB->id)->firstOrFail();
        $this->assertSame('8.000', (string) $assignmentA->recipeLines()->first()->quantity);
        $this->assertSame('10.000', (string) $assignmentB->recipeLines()->first()->quantity);

        $economics = $service->calculateAssignmentEconomics($assignmentA->fresh(['recipeLines.ingredient', 'addOn']));
        $this->assertSame('16.0000', $economics['cost']);
        $this->assertSame('30.00', $economics['selling_price']);
    }

    public function test_variant_recipe_override_is_used_for_inventory_consumption(): void
    {
        $milk = Ingredient::factory()->create([
            'is_active' => true,
            'current_stock' => '1000.000',
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
        ]);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['product_category_id' => $category->id, 'is_active' => true]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => '90.00',
            'is_active' => true,
            'is_available' => true,
        ]);
        $recipe = Recipe::query()->create(['product_variant_id' => $variant->id, 'is_active' => true]);
        RecipeLine::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $milk->id,
            'quantity' => '100.000',
            'measurement_unit' => IngredientUnit::Milliliter->value,
            'base_quantity' => '100.000',
            'base_measurement_unit' => IngredientUnit::Milliliter->value,
            'sort_order' => 1,
        ]);

        $addOn = AddOn::factory()->create(['default_price' => '15.00', 'is_active' => true]);
        app(AddOnServiceInterface::class)->syncProductAssignments($product, [[
            'add_on_id' => $addOn->id,
            'lines' => [[
                'ingredient_id' => $milk->id,
                'quantity' => '50.000',
                'measurement_unit' => IngredientUnit::Milliliter->value,
            ]],
            'variant_overrides' => [[
                'product_variant_id' => $variant->id,
                'lines' => [[
                    'ingredient_id' => $milk->id,
                    'quantity' => '25.000',
                    'measurement_unit' => IngredientUnit::Milliliter->value,
                ]],
            ]],
        ]]);

        $this->assertDatabaseCount('product_variant_add_on_recipe_lines', 1);

        $order = Order::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'recipe_id' => $recipe->id,
            'preparation_station' => PreparationStation::Bar->value,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'unit_price' => '90.00',
            'quantity' => 1,
            'line_subtotal' => '105.00',
        ]);
        $orderItem->addOns()->create([
            'add_on_id' => $addOn->id,
            'name' => $addOn->name,
            'quantity' => 1,
            'unit_price' => '15.00',
            'total_price' => '15.00',
        ]);

        app(OrderInventoryConsumptionServiceInterface::class)
            ->consumeForAcceptedOrder($order->fresh(), User::factory()->operator()->create());

        // base 100 + variant override add-on 25
        $this->assertSame('875.000', $milk->fresh()->current_stock);
        $this->assertInstanceOf(ProductVariantAddOnRecipeLine::class, ProductVariantAddOnRecipeLine::query()->first());
    }

    public function test_customer_catalog_exposes_add_on_price_without_recipe_or_cost(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'is_available' => true]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_available' => true,
            'price' => '100.00',
        ]);
        $addOn = AddOn::factory()->create(['default_price' => '20.00', 'is_active' => true, 'name' => 'Extra Shot']);
        app(AddOnServiceInterface::class)->syncProductAssignments($product, [[
            'add_on_id' => $addOn->id,
            'price_override' => '22.00',
            'max_quantity' => 2,
            'lines' => [[
                'ingredient_id' => Ingredient::factory()->create([
                    'is_active' => true,
                    'measurement_unit' => IngredientUnit::Gram,
                    'base_measurement_unit' => IngredientUnit::Gram,
                ])->id,
                'quantity' => '5.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ]],
        ]]);

        $catalog = app(AddOnServiceInterface::class)->catalogAddOnsForProduct($product->fresh());
        $this->assertSame([
            'id' => $addOn->id,
            'name' => 'Extra Shot',
            'description' => $addOn->description,
            'price' => '22.00',
            'max_quantity' => 2,
        ], $catalog[0]);
        $this->assertArrayNotHasKey('cost', $catalog[0]);
        $this->assertArrayNotHasKey('lines', $catalog[0]);
        $this->assertArrayNotHasKey('recipe', $catalog[0]);
    }

    public function test_category_image_upload_and_preserve_without_new_file(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')
            ->post(route('administrator.products.categories.store'), [
                'name' => 'Cold Brew',
                'description' => 'Chilled',
                'sort_order' => 1,
                'is_active' => 1,
                'image' => UploadedFile::fake()->image('cat.jpg', 40, 40),
            ]);

        $response->assertRedirect();
        $category = ProductCategory::query()->where('name', 'Cold Brew')->firstOrFail();
        $this->assertNotNull($category->image_path);
        $path = $category->image_path;

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.products.categories.update', $category), [
                'name' => 'Cold Brew Renamed',
                'description' => 'Chilled',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $fresh = $category->fresh();
        $this->assertSame($path, $fresh->image_path);
        $this->assertSame('cold-brew', $fresh->slug);
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    protected function productPayload(int $categoryId, array $variants, string $name = 'Solo Latte'): array
    {
        return [
            'product_category_id' => $categoryId,
            'product_type' => 'beverage',
            'preparation_station' => PreparationStation::Bar->value,
            'name' => $name,
            'is_active' => 0,
            'is_available' => 1,
            'is_vegetarian' => 0,
            'is_customizable' => 0,
            'sort_order' => 10,
            'variants' => $variants,
            'add_ons' => [],
        ];
    }
}
