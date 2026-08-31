<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\InventoryStockStatus;
use App\Enums\InventoryTransactionType;
use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Parsers\Inventory\InventoryParser;
use App\Parsers\Inventory\InventoryParserInterface;
use App\Repositories\Inventory\InventoryRepository;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryServiceInterface;
use App\Transfers\Inventory\InventoryHistoryFilterTransfer;
use App\Transfers\Inventory\InventoryHistoryFilterTransferInterface;
use App\Transfers\Inventory\InventoryOverviewFilterTransfer;
use App\Transfers\Inventory\InventoryOverviewFilterTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransfer;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use Database\Seeders\IngredientBrandSeeder;
use Database\Seeders\IngredientCategorySeeder;
use Database\Seeders\IngredientSeeder;
use Database\Seeders\InventoryTransactionSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AdministratorInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_architecture_contracts_and_schema_are_bound(): void
    {
        $this->assertInstanceOf(InventoryRepository::class, $this->app->make(InventoryRepositoryInterface::class));
        $this->assertInstanceOf(InventoryService::class, $this->app->make(InventoryServiceInterface::class));
        $this->assertInstanceOf(InventoryParser::class, $this->app->make(InventoryParserInterface::class));
        $this->assertInstanceOf(InventoryTransactionTransfer::class, $this->app->make(InventoryTransactionTransferInterface::class));
        $this->assertInstanceOf(InventoryOverviewFilterTransfer::class, $this->app->make(InventoryOverviewFilterTransferInterface::class));
        $this->assertInstanceOf(InventoryHistoryFilterTransfer::class, $this->app->make(InventoryHistoryFilterTransferInterface::class));
        $this->assertTrue(Schema::hasTable('inventory_transactions'));
        $this->assertTrue(Schema::hasColumn('inventory_transactions', 'base_quantity'));
        $this->assertTrue(Schema::hasColumn('inventory_transactions', 'stock_before'));
        $this->assertTrue(Schema::hasColumn('inventory_transactions', 'stock_after'));
    }

    public function test_manager_can_record_stock_addition_with_normalized_quantity_and_audit_values(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Kilogram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'current_stock' => '500.000',
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.inventory.movements.store'), [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => InventoryTransactionType::StockAdded->value,
            'quantity' => '1.000',
            'measurement_unit' => IngredientUnit::Kilogram->value,
            'reference_type' => 'purchase_invoice',
            'reference_id' => 12,
            'notes' => 'Weekly replenishment',
        ])->assertRedirect(route('administrator.inventory.history', ['ingredient_id' => $ingredient->id]));

        $transaction = InventoryTransaction::query()->firstOrFail();

        $this->assertSame('1.000', $transaction->quantity);
        $this->assertSame('1000.000', $transaction->base_quantity);
        $this->assertSame('500.000', $transaction->stock_before);
        $this->assertSame('1500.000', $transaction->stock_after);
        $this->assertSame($manager->id, $transaction->created_by);
        $this->assertSame('1500.000', $ingredient->fresh()->current_stock);
    }

    public function test_manager_can_record_stock_reduction_types_and_absolute_adjustments(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Liter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'current_stock' => '3000.000',
        ]);

        $this->actingAs($manager, 'admin')->post(route('administrator.inventory.movements.store'), [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => InventoryTransactionType::Damage->value,
            'quantity' => '0.500',
            'measurement_unit' => IngredientUnit::Liter->value,
        ])->assertRedirect();

        $this->actingAs($manager, 'admin')->post(route('administrator.inventory.movements.store'), [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => InventoryTransactionType::ManualAdjustment->value,
            'quantity' => '1.750',
            'measurement_unit' => IngredientUnit::Liter->value,
            'notes' => 'Physical count reconciliation',
        ])->assertRedirect();

        $transactions = InventoryTransaction::query()->orderBy('id')->get();

        $this->assertCount(2, $transactions);
        $this->assertSame('2500.000', $transactions[0]->stock_after);
        $this->assertSame('2500.000', $transactions[1]->stock_before);
        $this->assertSame('1750.000', $transactions[1]->stock_after);
        $this->assertSame('1750.000', $ingredient->fresh()->current_stock);
    }

    public function test_manager_can_record_wastage_expiry_purchase_and_correction_history(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create([
            'current_stock' => '200.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        foreach ([
            [InventoryTransactionType::Purchase, '50.000', '250.000'],
            [InventoryTransactionType::Wastage, '20.000', '230.000'],
            [InventoryTransactionType::Expiry, '30.000', '200.000'],
            [InventoryTransactionType::Correction, '180.000', '180.000'],
        ] as [$type, $quantity, $expectedStock]) {
            $this->actingAs($manager, 'admin')->post(route('administrator.inventory.movements.store'), [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => $type->value,
                'quantity' => $quantity,
                'measurement_unit' => IngredientUnit::Gram->value,
                'notes' => $type->label(),
            ])->assertRedirect();

            $this->assertSame($expectedStock, $ingredient->fresh()->current_stock);
        }

        $this->assertSame(4, InventoryTransaction::query()->count());
    }

    public function test_incompatible_units_and_negative_stock_are_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create([
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'current_stock' => '25.000',
        ]);

        $this->actingAs($manager, 'admin')->from(route('administrator.inventory.movements.create'))
            ->post(route('administrator.inventory.movements.store'), [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => InventoryTransactionType::ManualAddition->value,
                'quantity' => '1.000',
                'measurement_unit' => IngredientUnit::Liter->value,
            ])
            ->assertRedirect(route('administrator.inventory.movements.create'))
            ->assertSessionHasErrors('measurement_unit');

        $this->actingAs($manager, 'admin')->from(route('administrator.inventory.movements.create'))
            ->post(route('administrator.inventory.movements.store'), [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => InventoryTransactionType::ManualReduction->value,
                'quantity' => '50.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ])
            ->assertRedirect(route('administrator.inventory.movements.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('25.000', $ingredient->fresh()->current_stock);
        $this->assertCount(0, InventoryTransaction::all());
    }

    public function test_inventory_transaction_creation_rolls_back_when_repository_fails_mid_transaction(): void
    {
        $ingredient = Ingredient::factory()->create([
            'current_stock' => '100.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $this->app->bind(InventoryRepositoryInterface::class, function () {
            return new class implements InventoryRepositoryInterface
            {
                public function paginateOverview(InventoryOverviewFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
                {
                    throw new RuntimeException('Not used in this test.');
                }

                public function paginateHistory(InventoryHistoryFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
                {
                    throw new RuntimeException('Not used in this test.');
                }

                public function createTransaction(array $attributes): InventoryTransaction
                {
                    InventoryTransaction::query()->create($attributes);

                    throw new RuntimeException('Inventory write failed after transaction insert.');
                }

                public function findIngredient(int $ingredientId): Ingredient
                {
                    return Ingredient::query()->findOrFail($ingredientId);
                }

                public function lockIngredient(int $ingredientId): Ingredient
                {
                    return Ingredient::query()->lockForUpdate()->findOrFail($ingredientId);
                }

                public function updateIngredientStock(Ingredient $ingredient, string $currentStock): Ingredient
                {
                    throw new RuntimeException('Not used in this test.');
                }

                public function ingredientsWithoutTransactionsWithStock(): Collection
                {
                    throw new RuntimeException('Not used in this test.');
                }

                public function ingredientOptions(bool $activeOnly = false): array
                {
                    throw new RuntimeException('Not used in this test.');
                }

                public function transactionUserOptions(): array
                {
                    throw new RuntimeException('Not used in this test.');
                }
            };
        });

        $service = $this->app->make(InventoryServiceInterface::class);
        $transfer = $this->app->make(InventoryTransactionTransferInterface::class);
        $transfer->setIngredientId($ingredient->id);
        $transfer->setTransactionType(InventoryTransactionType::StockAdded->value);
        $transfer->setQuantity('25.000');
        $transfer->setMeasurementUnit(IngredientUnit::Gram->value);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Inventory write failed');

        try {
            $service->recordTransaction($transfer);
        } finally {
            $this->assertSame('100.000', $ingredient->fresh()->current_stock);
            $this->assertDatabaseCount('inventory_transactions', 0);
        }
    }

    public function test_opening_balance_backfill_is_idempotent_for_existing_stock(): void
    {
        $ingredient = Ingredient::factory()->create([
            'current_stock' => '240.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $service = $this->app->make(InventoryServiceInterface::class);

        $service->backfillOpeningBalances('test_opening_balance');
        $service->backfillOpeningBalances('test_opening_balance');

        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $ingredient->id,
            'transaction_type' => InventoryTransactionType::OpeningBalance->value,
            'stock_before' => '0.000',
            'stock_after' => '240.000',
            'reference_type' => 'test_opening_balance',
        ]);
    }

    public function test_inventory_seeders_backfill_opening_stock_without_duplicates(): void
    {
        $this->seed(IngredientCategorySeeder::class);
        $this->seed(IngredientBrandSeeder::class);
        $this->seed(IngredientSeeder::class);
        $this->seed(InventoryTransactionSeeder::class);
        $this->seed(InventoryTransactionSeeder::class);

        $ingredientCount = Ingredient::query()->count();
        $openingBalanceCount = Ingredient::query()->where('current_stock', '>', 0)->count();

        $this->assertSame($ingredientCount, Ingredient::query()->count());
        $this->assertSame($openingBalanceCount, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::OpeningBalance->value)
            ->count());
        $this->assertSame(
            $openingBalanceCount,
            InventoryTransaction::query()
                ->where('reference_type', 'seeder_opening_balance')
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            InventoryTransaction::query()->where('reference_type', 'seeder_demo_movement')->count(),
        );
        $this->assertSame(
            InventoryTransaction::query()->where('reference_type', 'seeder_demo_movement')->count(),
            InventoryTransaction::query()
                ->where('reference_type', 'seeder_demo_movement')
                ->distinct('reference_id')
                ->count('reference_id'),
        );
    }

    public function test_inventory_overview_and_history_support_filters_and_low_stock_statuses(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Inventory Manager']);
        $coffee = IngredientCategory::factory()->create(['name' => 'Coffee', 'slug' => 'coffee']);
        $milk = IngredientCategory::factory()->create(['name' => 'Milk', 'slug' => 'milk']);
        $brandA = IngredientBrand::factory()->create(['name' => 'Davidoff']);
        $brandB = IngredientBrand::factory()->create(['name' => 'Amul']);

        $lowStock = Ingredient::factory()->create([
            'ingredient_category_id' => $coffee->id,
            'ingredient_brand_id' => $brandA->id,
            'name' => 'Davidoff Espresso',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'current_stock' => '120.000',
            'minimum_stock' => '80.000',
            'reorder_level' => '150.000',
        ]);

        $inStock = Ingredient::factory()->create([
            'ingredient_category_id' => $milk->id,
            'ingredient_brand_id' => $brandB->id,
            'name' => 'Full Fat Milk',
            'measurement_unit' => IngredientUnit::Liter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'current_stock' => '7000.000',
            'minimum_stock' => '3000.000',
            'reorder_level' => '5000.000',
        ]);

        InventoryTransaction::factory()->create([
            'ingredient_id' => $lowStock->id,
            'transaction_type' => InventoryTransactionType::Wastage,
            'quantity' => '15.000',
            'base_quantity' => '15.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'stock_before' => '135.000',
            'stock_after' => '120.000',
            'created_by' => $manager->id,
        ]);

        $this->assertSame(InventoryStockStatus::LowStock, $lowStock->stockStatus());
        $this->assertSame(InventoryStockStatus::InStock, $inStock->stockStatus());

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.index', [
                'search' => 'Davidoff',
                'ingredient_category_id' => $coffee->id,
                'ingredient_brand_id' => $brandA->id,
                'measurement_unit' => IngredientUnit::Gram->value,
                'stock_status' => InventoryStockStatus::LowStock->value,
            ]))
            ->assertOk()
            ->assertSee('Davidoff Espresso')
            ->assertDontSee('Full Fat Milk');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.history', [
                'ingredient_id' => $lowStock->id,
                'ingredient_category_id' => $coffee->id,
                'ingredient_brand_id' => $brandA->id,
                'transaction_type' => InventoryTransactionType::Wastage->value,
                'created_by' => $manager->id,
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Davidoff Espresso')
            ->assertSee('Wastage');
    }

    public function test_barista_can_view_inventory_but_cannot_mutate_stock_or_access_admin_inventory_routes(): void
    {
        $barista = User::factory()->barista()->create();
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('barista.inventory.index'))
            ->assertOk()
            ->assertSee('Inventory', false)
            ->assertSee('Request Refill', false)
            ->assertDontSee('Record Movement', false);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.inventory.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.inventory.movements.store'), [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => InventoryTransactionType::StockAdded->value,
                'quantity' => '10.000',
                'measurement_unit' => $ingredient->base_measurement_unit->value,
            ])
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->get(route('barista.inventory.index'))
            ->assertForbidden();
    }

    public function test_inventory_pages_use_shared_action_dropdowns_and_button_groups(): void
    {
        $manager = User::factory()->manager()->create();
        $ingredient = Ingredient::factory()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.index'))
            ->assertOk()
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee('internal-button-group', false)
            ->assertSee(route('administrator.inventory.movements.create', ['ingredient_id' => $ingredient->id]), false);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.inventory.history'))
            ->assertOk()
            ->assertSee('internal-button-group', false);
    }
}
