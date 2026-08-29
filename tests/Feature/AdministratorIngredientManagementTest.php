<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Models\User;
use App\Parsers\Ingredient\IngredientBrandParser;
use App\Parsers\Ingredient\IngredientBrandParserInterface;
use App\Parsers\Ingredient\IngredientCategoryParser;
use App\Parsers\Ingredient\IngredientCategoryParserInterface;
use App\Parsers\Ingredient\IngredientParser;
use App\Parsers\Ingredient\IngredientParserInterface;
use App\Repositories\Ingredient\IngredientBrandRepository;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientCategoryRepository;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Ingredient\IngredientRepository;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Services\Ingredient\IngredientBrandService;
use App\Services\Ingredient\IngredientBrandServiceInterface;
use App\Services\Ingredient\IngredientCategoryService;
use App\Services\Ingredient\IngredientCategoryServiceInterface;
use App\Services\Ingredient\IngredientService;
use App\Services\Ingredient\IngredientServiceInterface;
use App\Transfers\Ingredient\IngredientBrandFilterTransfer;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use App\Transfers\Ingredient\IngredientBrandTransfer;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;
use App\Transfers\Ingredient\IngredientCategoryTransfer;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;
use App\Transfers\Ingredient\IngredientFilterTransfer;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use App\Transfers\Ingredient\IngredientTransfer;
use App\Transfers\Ingredient\IngredientTransferInterface;
use Database\Seeders\IngredientBrandSeeder;
use Database\Seeders\IngredientCategorySeeder;
use Database\Seeders\IngredientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdministratorIngredientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingredient_architecture_contracts_are_bound(): void
    {
        $this->assertInstanceOf(IngredientCategoryRepository::class, $this->app->make(IngredientCategoryRepositoryInterface::class));
        $this->assertInstanceOf(IngredientBrandRepository::class, $this->app->make(IngredientBrandRepositoryInterface::class));
        $this->assertInstanceOf(IngredientRepository::class, $this->app->make(IngredientRepositoryInterface::class));
        $this->assertInstanceOf(IngredientCategoryService::class, $this->app->make(IngredientCategoryServiceInterface::class));
        $this->assertInstanceOf(IngredientBrandService::class, $this->app->make(IngredientBrandServiceInterface::class));
        $this->assertInstanceOf(IngredientService::class, $this->app->make(IngredientServiceInterface::class));
        $this->assertInstanceOf(IngredientCategoryTransfer::class, $this->app->make(IngredientCategoryTransferInterface::class));
        $this->assertInstanceOf(IngredientBrandTransfer::class, $this->app->make(IngredientBrandTransferInterface::class));
        $this->assertInstanceOf(IngredientBrandFilterTransfer::class, $this->app->make(IngredientBrandFilterTransferInterface::class));
        $this->assertInstanceOf(IngredientTransfer::class, $this->app->make(IngredientTransferInterface::class));
        $this->assertInstanceOf(IngredientFilterTransfer::class, $this->app->make(IngredientFilterTransferInterface::class));
        $this->assertInstanceOf(IngredientCategoryParser::class, $this->app->make(IngredientCategoryParserInterface::class));
        $this->assertInstanceOf(IngredientBrandParser::class, $this->app->make(IngredientBrandParserInterface::class));
        $this->assertInstanceOf(IngredientParser::class, $this->app->make(IngredientParserInterface::class));
    }

    public function test_phase_3a_schema_uses_brand_foreign_keys_and_removes_legacy_sort_order(): void
    {
        $this->assertTrue(Schema::hasTable('ingredient_brands'));
        $this->assertTrue(Schema::hasColumn('ingredients', 'ingredient_brand_id'));
        $this->assertFalse(Schema::hasColumn('ingredients', 'brand'));
        $this->assertFalse(Schema::hasColumn('ingredient_categories', 'sort_order'));
    }

    public function test_manager_can_create_category_with_auto_generated_slug_and_view_it(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.ingredients.categories.store'), [
            'name' => 'Seasonal Fruit',
            'description' => 'Fresh fruit inventory for seasonal drinks.',
            'is_active' => 1,
        ]);

        $category = IngredientCategory::query()->firstOrFail();

        $response->assertRedirect(route('administrator.ingredients.categories.edit', $category));
        $this->assertSame('seasonal-fruit', $category->slug);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.categories.show', $category))
            ->assertOk()
            ->assertSee('Seasonal Fruit')
            ->assertSee('Ingredients in This Category');
    }

    public function test_category_slug_regenerates_uniquely_when_names_overlap_or_change(): void
    {
        $manager = User::factory()->manager()->create();
        $existing = IngredientCategory::factory()->create([
            'name' => 'Coffee',
            'slug' => 'coffee',
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.ingredients.categories.store'), [
            'name' => 'Coffee',
            'description' => 'Duplicate category name for slug uniqueness.',
            'is_active' => 1,
        ])->assertRedirect();

        $duplicate = IngredientCategory::query()->whereKeyNot($existing->id)->firstOrFail();
        $this->assertSame('coffee-2', $duplicate->slug);

        $this->actingAs($manager, 'admin')->put(route('administrator.ingredients.categories.update', $duplicate), [
            'name' => 'Coffee Beans',
            'description' => 'Updated category name.',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('coffee-beans', $duplicate->fresh()->slug);
    }

    public function test_manager_can_create_update_and_view_ingredient_brand_with_auto_slug(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')->post(route('administrator.ingredients.brands.store'), [
            'name' => 'Blue Tokai',
            'description' => 'Specialty coffee brand.',
            'is_active' => 1,
        ])->assertRedirect();

        $brand = IngredientBrand::query()->firstOrFail();
        $this->assertSame('blue-tokai', $brand->slug);

        $this->actingAs($manager, 'admin')->put(route('administrator.ingredients.brands.update', $brand), [
            'name' => 'Blue Tokai Coffee',
            'description' => 'Updated specialty coffee brand.',
            'is_active' => 0,
        ])->assertRedirect(route('administrator.ingredients.brands.edit', $brand));

        $brand->refresh();

        $this->assertSame('blue-tokai-coffee', $brand->slug);
        $this->assertFalse($brand->is_active);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.brands.show', $brand))
            ->assertOk()
            ->assertSee('Ingredients Using This Brand');
    }

    public function test_brand_slug_generation_is_unique_for_duplicate_names(): void
    {
        $manager = User::factory()->manager()->create();
        IngredientBrand::factory()->create([
            'name' => 'Monin',
            'slug' => 'monin',
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.ingredients.brands.store'), [
            'name' => 'Monin',
            'description' => 'Second Monin record for uniqueness test.',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('ingredient_brands', [
            'slug' => 'monin-2',
        ]);
    }

    public function test_manager_can_create_ingredient_with_brand_dropdown_and_normalized_unit_costs(): void
    {
        $manager = User::factory()->manager()->create();
        $category = IngredientCategory::factory()->create();
        $brand = IngredientBrand::factory()->create(['name' => 'Amul']);

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.ingredients.store'), [
            'ingredient_category_id' => $category->id,
            'ingredient_brand_id' => $brand->id,
            'name' => 'Cafe Milk Base',
            'slug' => 'cafe-milk-base',
            'description' => 'Used for lattes and cold coffee.',
            'measurement_unit' => IngredientUnit::Liter->value,
            'purchase_quantity' => '1.000',
            'purchase_cost' => '70.00',
            'current_stock' => '12.000',
            'minimum_stock' => '4.000',
            'reorder_level' => '6.000',
            'supplier_name' => 'Fresh Dairy',
            'supplier_email' => 'orders@freshdairy.test',
            'supplier_phone' => '9999999911',
            'supplier_notes' => 'Deliver before 9 AM.',
            'is_active' => 1,
        ]);

        $ingredient = Ingredient::query()->where('slug', 'cafe-milk-base')->firstOrFail();

        $response->assertRedirect(route('administrator.ingredients.edit', $ingredient));
        $this->assertSame($brand->id, $ingredient->ingredient_brand_id);
        $this->assertSame(IngredientUnit::Liter, $ingredient->measurement_unit);
        $this->assertSame(IngredientUnit::Milliliter, $ingredient->base_measurement_unit);
        $this->assertSame('1000.000', $ingredient->purchase_quantity_base);
        $this->assertSame('0.0700', $ingredient->cost_per_unit);
        $this->assertSame('12000.000', $ingredient->current_stock);
    }

    public function test_manager_can_update_ingredient_and_preserve_decimal_precision(): void
    {
        $manager = User::factory()->manager()->create();
        $category = IngredientCategory::factory()->create();
        $brand = IngredientBrand::factory()->create(['name' => 'Blue Tokai']);
        $ingredient = Ingredient::factory()->create([
            'ingredient_category_id' => $category->id,
            'ingredient_brand_id' => $brand->id,
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $this->actingAs($manager, 'admin')->put(route('administrator.ingredients.update', $ingredient), [
            'ingredient_category_id' => $category->id,
            'ingredient_brand_id' => $brand->id,
            'name' => 'Arabica Roast',
            'slug' => 'arabica-roast',
            'description' => 'Single origin roast.',
            'measurement_unit' => IngredientUnit::Kilogram->value,
            'purchase_quantity' => '1.250',
            'purchase_cost' => '625.50',
            'current_stock' => '2.500',
            'minimum_stock' => '0.750',
            'reorder_level' => '1.250',
            'supplier_name' => 'Blue Tokai Wholesale',
            'supplier_email' => 'orders@bluetokai.test',
            'supplier_phone' => '9999999912',
            'supplier_notes' => 'Weekly replenishment.',
            'is_active' => 0,
        ])->assertRedirect(route('administrator.ingredients.edit', $ingredient));

        $ingredient->refresh();

        $this->assertSame('1.250', $ingredient->purchase_quantity);
        $this->assertSame('1250.000', $ingredient->purchase_quantity_base);
        $this->assertSame('625.50', $ingredient->purchase_cost);
        $this->assertSame('0.5004', $ingredient->cost_per_unit);
        $this->assertSame('2500.000', $ingredient->current_stock);
        $this->assertFalse($ingredient->is_active);
    }

    public function test_reorder_level_cannot_be_lower_than_minimum_stock(): void
    {
        $manager = User::factory()->manager()->create();
        $category = IngredientCategory::factory()->create();

        $this->actingAs($manager, 'admin')->from(route('administrator.ingredients.create'))
            ->post(route('administrator.ingredients.store'), [
                'ingredient_category_id' => $category->id,
                'name' => 'Failed Ingredient',
                'measurement_unit' => IngredientUnit::Gram->value,
                'purchase_quantity' => '100.000',
                'purchase_cost' => '600.00',
                'current_stock' => '100.000',
                'minimum_stock' => '20.000',
                'reorder_level' => '10.000',
                'is_active' => 1,
            ])
            ->assertRedirect(route('administrator.ingredients.create'))
            ->assertSessionHasErrors('reorder_level');
    }

    public function test_manager_can_filter_ingredients_by_search_category_brand_unit_and_status(): void
    {
        $manager = User::factory()->manager()->create();
        $coffee = IngredientCategory::factory()->create(['name' => 'Coffee', 'slug' => 'coffee']);
        $milk = IngredientCategory::factory()->create(['name' => 'Milk', 'slug' => 'milk']);
        $davidoff = IngredientBrand::factory()->create(['name' => 'Davidoff', 'slug' => 'davidoff']);
        $amul = IngredientBrand::factory()->create(['name' => 'Amul', 'slug' => 'amul']);

        Ingredient::factory()->create([
            'ingredient_category_id' => $coffee->id,
            'ingredient_brand_id' => $davidoff->id,
            'name' => 'Davidoff Espresso',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'is_active' => true,
        ]);

        Ingredient::factory()->create([
            'ingredient_category_id' => $milk->id,
            'ingredient_brand_id' => $amul->id,
            'name' => 'Full Fat Milk',
            'measurement_unit' => IngredientUnit::Liter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'is_active' => false,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.index', [
                'search' => 'Davidoff',
                'ingredient_category_id' => $coffee->id,
                'ingredient_brand_id' => $davidoff->id,
                'measurement_unit' => IngredientUnit::Gram->value,
                'status' => 'active',
            ]))
            ->assertOk()
            ->assertSee('Davidoff Espresso')
            ->assertDontSee('Full Fat Milk');
    }

    public function test_brand_filter_dropdown_shows_active_brands_and_keeps_current_inactive_brand_on_edit(): void
    {
        $manager = User::factory()->manager()->create();
        IngredientBrand::factory()->create(['name' => 'Monin', 'is_active' => true]);
        $inactiveBrand = IngredientBrand::factory()->create(['name' => 'Legacy Syrups', 'is_active' => false]);
        $ingredient = Ingredient::factory()->create([
            'ingredient_brand_id' => $inactiveBrand->id,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.create'))
            ->assertOk()
            ->assertSee('Monin')
            ->assertDontSee('Legacy Syrups');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.edit', $ingredient))
            ->assertOk()
            ->assertSee('Legacy Syrups (Inactive)');
    }

    public function test_brand_show_page_lists_ingredients_using_that_brand(): void
    {
        $manager = User::factory()->manager()->create();
        $brand = IngredientBrand::factory()->create(['name' => 'Monin']);
        $ingredient = Ingredient::factory()->create([
            'ingredient_brand_id' => $brand->id,
            'name' => 'Vanilla Syrup',
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.brands.show', $brand))
            ->assertOk()
            ->assertSee('Vanilla Syrup')
            ->assertSee(route('administrator.ingredients.edit', $ingredient), false);
    }

    public function test_manager_cannot_archive_brand_or_category_that_is_still_referenced(): void
    {
        $manager = User::factory()->manager()->create();
        $category = IngredientCategory::factory()->create();
        $brand = IngredientBrand::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'ingredient_category_id' => $category->id,
            'ingredient_brand_id' => $brand->id,
        ]);

        $this->actingAs($manager, 'admin')->from(route('administrator.ingredients.brands.index'))
            ->delete(route('administrator.ingredients.brands.destroy', $brand))
            ->assertRedirect(route('administrator.ingredients.brands.index'))
            ->assertSessionHasErrors('brand');

        $this->actingAs($manager, 'admin')->delete(route('administrator.ingredients.destroy', $ingredient))
            ->assertRedirect(route('administrator.ingredients.index'));

        $this->actingAs($manager, 'admin')->from(route('administrator.ingredients.categories.index'))
            ->delete(route('administrator.ingredients.categories.destroy', $category))
            ->assertRedirect(route('administrator.ingredients.categories.index'))
            ->assertSessionHasErrors('category');
    }

    public function test_manager_can_archive_empty_brand_and_empty_category(): void
    {
        $manager = User::factory()->manager()->create();
        $category = IngredientCategory::factory()->create();
        $brand = IngredientBrand::factory()->create();

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.ingredients.brands.destroy', $brand))
            ->assertRedirect(route('administrator.ingredients.brands.index'));

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.ingredients.categories.destroy', $category))
            ->assertRedirect(route('administrator.ingredients.categories.index'));

        $this->assertSoftDeleted('ingredient_brands', ['id' => $brand->id]);
        $this->assertSoftDeleted('ingredient_categories', ['id' => $category->id]);
    }

    public function test_ingredient_pages_use_shared_action_dropdown_and_button_groups(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.index'))
            ->assertOk()
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee('internal-button-group', false)
            ->assertSee(route('administrator.ingredients.show', $ingredient), false);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.ingredients.brands.index'))
            ->assertOk()
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee('internal-button-group', false);
    }

    public function test_barista_cannot_manage_ingredient_master_data(): void
    {
        $barista = User::factory()->barista()->create();
        $category = IngredientCategory::factory()->create();
        $brand = IngredientBrand::factory()->create();
        $ingredient = Ingredient::factory()->create([
            'ingredient_category_id' => $category->id,
            'ingredient_brand_id' => $brand->id,
        ]);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.ingredients.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.ingredients.brands.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->delete(route('administrator.ingredients.destroy', $ingredient))
            ->assertForbidden();
    }

    public function test_ingredient_seeders_are_idempotent_and_map_brands_to_foreign_keys(): void
    {
        $this->seed(IngredientCategorySeeder::class);
        $this->seed(IngredientBrandSeeder::class);
        $this->seed(IngredientSeeder::class);
        $this->seed(IngredientBrandSeeder::class);
        $this->seed(IngredientSeeder::class);

        $this->assertSame(15, IngredientCategory::query()->count());
        $this->assertSame(5, IngredientBrand::query()->count());
        $this->assertSame(6, Ingredient::query()->count());
        $this->assertSame(0, Ingredient::query()->whereNull('ingredient_brand_id')->count());

        $vanillaSyrup = Ingredient::query()->where('name', 'Vanilla Syrup')->firstOrFail();
        $this->assertSame('Monin', $vanillaSyrup->brand?->name);
    }
}
