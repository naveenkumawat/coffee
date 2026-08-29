<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\RecipeCostingServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_recipe_for_variant_with_normalized_lines(): void
    {
        $manager = User::factory()->manager()->create();
        $variant = $this->makeVariant(price: '80.00');
        $coffee = $this->makeIngredient([
            'name' => 'Espresso Beans',
            'measurement_unit' => IngredientUnit::Kilogram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'purchase_quantity' => '1.000',
            'purchase_quantity_base' => '1000.000',
            'purchase_cost' => '600.00',
            'cost_per_unit' => '0.6000',
        ]);
        $milk = $this->makeIngredient([
            'name' => 'Milk',
            'measurement_unit' => IngredientUnit::Liter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'purchase_quantity' => '1.000',
            'purchase_quantity_base' => '1000.000',
            'purchase_cost' => '70.00',
            'cost_per_unit' => '0.0700',
        ]);

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.recipes.store'), [
            'product_variant_id' => $variant->id,
            'preparation_notes' => 'Steam milk to 60C and pour with latte art.',
            'is_active' => 1,
            'lines' => [
                [
                    'ingredient_id' => $coffee->id,
                    'quantity' => '0.018',
                    'measurement_unit' => IngredientUnit::Kilogram->value,
                    'sort_order' => 1,
                ],
                [
                    'ingredient_id' => $milk->id,
                    'quantity' => '0.220',
                    'measurement_unit' => IngredientUnit::Liter->value,
                    'sort_order' => 2,
                ],
                [
                    'ingredient_id' => null,
                    'quantity' => null,
                    'measurement_unit' => 'g',
                    'sort_order' => 3,
                ],
            ],
        ]);

        $response->assertRedirect();

        $recipe = Recipe::query()->with('lines')->firstOrFail();

        $this->assertSame($variant->id, $recipe->product_variant_id);
        $this->assertCount(2, $recipe->lines);
        $this->assertDatabaseHas('recipe_lines', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $coffee->id,
            'base_quantity' => '18.000',
            'base_measurement_unit' => IngredientUnit::Gram->value,
        ]);
        $this->assertDatabaseHas('recipe_lines', [
            'recipe_id' => $recipe->id,
            'ingredient_id' => $milk->id,
            'base_quantity' => '220.000',
            'base_measurement_unit' => IngredientUnit::Milliliter->value,
        ]);
    }

    public function test_recipe_costing_uses_current_ingredient_cost_and_margin_calculation(): void
    {
        $recipe = $this->makeRecipeWithTwoLines();

        $summary = $this->app->make(RecipeCostingServiceInterface::class)->summarize($recipe->fresh(['variant.product', 'lines.ingredient']));

        $this->assertSame('26.2000', $summary['production_cost']);
        $this->assertSame('80.0000', $summary['selling_price']);
        $this->assertSame('53.8000', $summary['gross_profit']);
        $this->assertSame('67.25', $summary['margin_percentage']);
    }

    public function test_ingredient_cost_change_recalculates_recipe_cost_without_snapshotting(): void
    {
        $recipe = $this->makeRecipeWithTwoLines();
        $milkLine = $recipe->lines->last();
        $milkLine->ingredient->update([
            'cost_per_unit' => '0.1000',
        ]);

        $summary = $this->app->make(RecipeCostingServiceInterface::class)->summarize($recipe->fresh(['variant.product', 'lines.ingredient']));

        $this->assertSame('32.8000', $summary['production_cost']);
        $this->assertSame('47.2000', $summary['gross_profit']);
        $this->assertSame('59.00', $summary['margin_percentage']);
    }

    public function test_duplicate_ingredient_line_is_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $variant = $this->makeVariant();
        $ingredient = $this->makeIngredient();

        $this->actingAs($manager, 'admin')->post(route('administrator.recipes.store'), [
            'product_variant_id' => $variant->id,
            'lines' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => '10.000',
                    'measurement_unit' => IngredientUnit::Gram->value,
                ],
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => '5.000',
                    'measurement_unit' => IngredientUnit::Gram->value,
                ],
            ],
        ])->assertSessionHasErrors('lines.1.ingredient_id');
    }

    public function test_incompatible_unit_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $variant = $this->makeVariant();
        $ingredient = $this->makeIngredient([
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.recipes.store'), [
            'product_variant_id' => $variant->id,
            'lines' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => '10.000',
                    'measurement_unit' => IngredientUnit::Liter->value,
                ],
            ],
        ])->assertSessionHasErrors('lines.0.measurement_unit');
    }

    public function test_manager_can_update_and_archive_recipe(): void
    {
        $manager = User::factory()->manager()->create();
        $recipe = $this->makeRecipeWithTwoLines();

        $this->actingAs($manager, 'admin')->put(route('administrator.recipes.update', $recipe), [
            'product_variant_id' => $recipe->product_variant_id,
            'preparation_notes' => 'Updated prep notes',
            'is_active' => 1,
            'lines' => [
                [
                    'id' => $recipe->lines[0]->id,
                    'ingredient_id' => $recipe->lines[0]->ingredient_id,
                    'quantity' => '20.000',
                    'measurement_unit' => IngredientUnit::Gram->value,
                    'sort_order' => 1,
                ],
            ],
        ])->assertRedirect(route('administrator.recipes.show', $recipe));

        $this->assertDatabaseHas('recipes', [
            'id' => $recipe->id,
            'preparation_notes' => 'Updated prep notes',
        ]);
        $this->assertDatabaseHas('recipe_lines', [
            'recipe_id' => $recipe->id,
            'quantity' => '20.000',
        ]);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.recipes.destroy', $recipe))
            ->assertRedirect(route('administrator.recipes.index'));

        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
    }

    public function test_barista_can_view_recipe_without_financial_data(): void
    {
        $barista = User::factory()->create(['role' => UserRole::Barista]);
        $recipe = $this->makeRecipeWithTwoLines();

        $this->actingAs($barista, 'admin')
            ->get(route('barista.recipes.show', $recipe))
            ->assertOk()
            ->assertSee($recipe->variant->product->name)
            ->assertSee($recipe->lines[0]->ingredient->name)
            ->assertDontSee('Production Cost')
            ->assertDontSee('Gross Profit')
            ->assertDontSee('Margin');
    }

    public function test_customer_cannot_access_internal_recipe_routes_and_public_home_does_not_expose_recipe_details(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $recipe = $this->makeRecipeWithTwoLines(preparationNotes: 'SECRET WHISK ORDER');

        $this->actingAs($customer, 'admin')
            ->get(route('administrator.recipes.show', $recipe))
            ->assertForbidden();

        $this->actingAs($customer, 'admin')
            ->get(route('barista.recipes.show', $recipe))
            ->assertForbidden();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('SECRET WHISK ORDER')
            ->assertDontSee((string) $recipe->lines[0]->quantity);
    }

    public function test_recipe_index_can_filter_by_ingredient(): void
    {
        $manager = User::factory()->manager()->create();
        $recipe = $this->makeRecipeWithTwoLines();
        $otherRecipe = $this->makeRecipeWithTwoLines(productName: 'Other Product', variantName: 'Large');

        $response = $this->actingAs($manager, 'admin')->get(route('administrator.recipes.index', [
            'ingredient_id' => $recipe->lines[0]->ingredient_id,
        ]));

        $response
            ->assertOk()
            ->assertSee($recipe->variant->product->name)
            ->assertSee(route('administrator.recipes.show', $recipe), false)
            ->assertDontSee(route('administrator.recipes.show', $otherRecipe), false);
    }

    protected function makeRecipeWithTwoLines(string $productName = 'Cafe Latte', string $variantName = 'Regular', string $preparationNotes = 'Standard prep'): Recipe
    {
        $variant = $this->makeVariant(name: $variantName, productName: $productName, price: '80.00');
        $coffee = $this->makeIngredient([
            'name' => 'Espresso Beans',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'cost_per_unit' => '0.6000',
        ]);
        $milk = $this->makeIngredient([
            'name' => 'Milk',
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'cost_per_unit' => '0.0700',
        ]);

        $recipe = Recipe::factory()->create([
            'product_variant_id' => $variant->id,
            'preparation_notes' => $preparationNotes,
            'is_active' => true,
        ]);

        $recipe->lines()->create([
            'ingredient_id' => $coffee->id,
            'quantity' => '18.000',
            'measurement_unit' => IngredientUnit::Gram->value,
            'base_quantity' => '18.000',
            'base_measurement_unit' => IngredientUnit::Gram->value,
            'sort_order' => 1,
        ]);

        $recipe->lines()->create([
            'ingredient_id' => $milk->id,
            'quantity' => '220.000',
            'measurement_unit' => IngredientUnit::Milliliter->value,
            'base_quantity' => '220.000',
            'base_measurement_unit' => IngredientUnit::Milliliter->value,
            'sort_order' => 2,
        ]);

        return $recipe->fresh(['variant.product.category', 'lines.ingredient']);
    }

    protected function makeVariant(string $name = 'Regular', string $productName = 'Cafe Latte', string $price = '60.00'): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $name,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    protected function makeIngredient(array $overrides = []): Ingredient
    {
        $category = IngredientCategory::factory()->create();

        return Ingredient::factory()->create(array_merge([
            'ingredient_category_id' => $category->id,
            'name' => 'Ingredient '.fake()->unique()->word(),
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'purchase_quantity' => '100.000',
            'purchase_quantity_base' => '100.000',
            'purchase_cost' => '100.00',
            'cost_per_unit' => '1.0000',
            'current_stock' => '1000.000',
            'minimum_stock' => '0.000',
            'reorder_level' => '0.000',
            'is_active' => true,
        ], $overrides));
    }
}
