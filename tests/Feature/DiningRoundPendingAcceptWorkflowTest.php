<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\ProductType;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryTransaction;
use App\Models\OrderInventoryConsumption;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Product\ProductCatalogService;
use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningRoundPendingAcceptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_placed_dining_round_is_pending_without_consumption_or_tickets(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $milk = $this->makeIngredient('Milk', '2000.000');
        $variant = $this->makeVariantWithRecipe('Cappuccino', [[$milk, '200.000']]);

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 2]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $order = $dining->placeRound($session, $customer);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->accepted_at);
        $this->assertSame(0, $order->preparations()->count());
        $this->assertSame(0, OrderInventoryConsumption::query()->where('order_id', $order->id)->count());
        $this->assertSame('2000.000', $milk->fresh()->current_stock);
        $this->assertTrue(
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('to_status', OrderStatus::Pending->value)
                ->exists(),
        );

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.rounds.0.status_label', 'Pending');

        Sanctum::actingAs($waiter);
        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.rounds.0.can_accept', true);

        $accepted = $dining->acceptRound($session->fresh(), $order->fresh(), $waiter);

        $this->assertSame(OrderStatus::Accepted, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertSame(1, $accepted->preparations()->count());
        $this->assertSame(
            OrderPreparationStatus::Pending,
            $accepted->preparations()->first()->status,
        );
        $this->assertSame(1, OrderInventoryConsumption::query()->where('order_id', $accepted->id)->count());
        $this->assertSame('1800.000', $milk->fresh()->current_stock);
        $this->assertTrue(
            OrderStatusHistory::query()
                ->where('order_id', $accepted->id)
                ->where('from_status', OrderStatus::Pending->value)
                ->where('to_status', OrderStatus::Accepted->value)
                ->exists(),
        );

        $dining->acceptRound($session->fresh(), $accepted->fresh(), $waiter);
        $this->assertSame(1, OrderInventoryConsumption::query()->where('order_id', $accepted->id)->count());
        $this->assertSame('1800.000', $milk->fresh()->current_stock);
        $this->assertSame(
            1,
            InventoryTransaction::query()
                ->where('ingredient_id', $milk->id)
                ->where('transaction_type', InventoryTransactionType::SaleConsumption->value)
                ->count(),
        );

        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.status', OrderStatus::Accepted->value)
            ->assertJsonPath('data.rounds.0.can_accept', false);
    }

    public function test_round_two_can_reorder_same_product_when_stock_remains_and_catalog_stays_available(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $milk = $this->makeIngredient('Milk', '2000.000');
        $variant = $this->makeVariantWithRecipe('Cappuccino', [[$milk, '200.000']]);
        $other = $this->makeVariantWithRecipe(
            'Muffin',
            [[$this->makeIngredient('Flour', '500.000', IngredientUnit::Gram), '50.000']],
            ProductType::Food,
        );

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 2]);

        $dining->addDraftItem($session, (int) $variant->id, 2, $customer);
        $round1 = $dining->placeRound($session, $customer);
        $this->assertSame(OrderStatus::Pending, $round1->status);
        $this->assertSame(1, (int) $round1->dining_round_number);

        Cache::forget(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY);
        $warm = app(ProductCatalogServiceInterface::class)->listPublicProductPayload();
        $this->assertNotEmpty($warm);
        // Second read must keep plain arrays (regression for all-Unavailable after Round 1).
        $cached = app(ProductCatalogServiceInterface::class)->listPublicProductPayload();
        $this->assertIsArray($cached[0]['variants'] ?? null);
        $this->assertTrue(collect($cached)->contains(
            fn (array $product): bool => collect($product['variants'] ?? [])->contains(
                fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $variant->id
                    && ! empty($row['is_available']),
            ),
        ));

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.dining.sessions.drafts.store', $session->id), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.dining.sessions.drafts.store', $session->id), [
            'product_variant_id' => $other->id,
            'quantity' => 1,
        ])->assertOk();

        $round2 = $dining->placeRound($session->fresh(), $customer);
        $this->assertSame(OrderStatus::Pending, $round2->status);
        $this->assertSame(2, (int) $round2->dining_round_number);
        $this->assertSame(OrderStatus::Pending, $round1->fresh()->status);
        $this->assertSame(2, (int) $round1->fresh()->items()->sum('quantity'));

        $dining->acceptRound($session->fresh(), $round1->fresh(), $waiter);
        $this->assertSame('1600.000', $milk->fresh()->current_stock);

        $dining->acceptRound($session->fresh(), $round2->fresh(), $waiter);
        $this->assertSame('1400.000', $milk->fresh()->current_stock);
        $this->assertSame(OrderStatus::Accepted, $round1->fresh()->status);
        $this->assertSame(2, (int) $round1->fresh()->items()->sum('quantity'));
    }

    protected function enableDining(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            [
                'section' => WebsiteSettingKey::FulfilmentDineInEnabled->section(),
                'value_type' => WebsiteSettingKey::FulfilmentDineInEnabled->valueType(),
                'value' => '1',
            ],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            [
                'section' => WebsiteSettingKey::OrderingManualClosed->section(),
                'value_type' => WebsiteSettingKey::OrderingManualClosed->valueType(),
                'value' => '0',
            ],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::TaxEnabled->value],
            [
                'section' => WebsiteSettingKey::TaxEnabled->section(),
                'value_type' => WebsiteSettingKey::TaxEnabled->valueType(),
                'value' => '0',
            ],
        );
    }

    protected function makeIngredient(
        string $name,
        string $stock,
        IngredientUnit $unit = IngredientUnit::Milliliter,
    ): Ingredient {
        $category = IngredientCategory::factory()->create();

        return Ingredient::factory()->create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'current_stock' => $stock,
            'measurement_unit' => $unit,
            'base_measurement_unit' => $unit,
        ]);
    }

    /**
     * @param  list<array{0: Ingredient, 1: string}>  $lines
     */
    protected function makeVariantWithRecipe(
        string $name,
        array $lines,
        ProductType $type = ProductType::Beverage,
    ): ProductVariant {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'product_type' => $type,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $type === ProductType::Food
                ? PreparationStation::Kitchen
                : PreparationStation::Bar,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '10.00',
            'serving_size_value' => '1',
            'serving_size_unit' => ProductServingUnit::Piece,
            'is_active' => true,
            'is_available' => true,
        ]);
        $recipe = Recipe::query()->create([
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        foreach ($lines as $index => [$ingredient, $qty]) {
            RecipeLine::query()->create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $qty,
                'measurement_unit' => $ingredient->measurement_unit->value,
                'base_quantity' => $qty,
                'base_measurement_unit' => $ingredient->base_measurement_unit->value,
                'sort_order' => $index + 1,
            ]);
        }

        return $variant->fresh(['recipe.lines.ingredient', 'product']);
    }
}
