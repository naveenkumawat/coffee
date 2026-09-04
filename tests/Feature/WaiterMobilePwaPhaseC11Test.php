<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\IngredientUnit;
use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\AddOn;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AddOn\AddOnServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaiterMobilePwaPhaseC11Test extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_login_to_spa_and_customer_cannot_access_waiter_routes(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create([
            'email' => 'waiter@example.test',
            'password' => 'password',
        ]);
        $customer = User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'password',
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'waiter@example.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'waiter');

        $this->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.role', 'waiter');

        $this->getJson(route('api.v1.waiter.tables.index'))
            ->assertOk();

        $this->postJson(route('api.v1.auth.logout'))->assertOk();

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.role', 'customer');

        $this->getJson(route('api.v1.waiter.tables.index'))
            ->assertForbidden();
    }

    public function test_operator_cannot_login_via_spa(): void
    {
        $operator = User::factory()->operator()->create([
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'operator@example.test',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_waiter_can_start_walk_in_session_and_manage_independent_drafts(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $tableA = CafeTable::factory()->create(['code' => 'T2', 'is_active' => true]);
        $tableB = CafeTable::factory()->create(['code' => 'T5', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('100.00', 'Cappuccino');
        $addOn = AddOn::factory()->create(['default_price' => '20.00', 'is_active' => true]);
        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 2,
        ]]);

        Sanctum::actingAs($waiter);

        $sessionA = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $tableA->id,
            'guest_count' => 2,
        ])->assertCreated()->json('data.id');

        $sessionB = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $tableB->id,
        ])->assertCreated()->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionA), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 1]],
        ])->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionB), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionA))
            ->assertOk()
            ->assertJsonPath('data.drafts.0.add_ons.0.add_on_id', $addOn->id)
            ->assertJsonCount(1, 'data.drafts');

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionB))
            ->assertOk()
            ->assertJsonPath('data.drafts.0.quantity', 2)
            ->assertJsonPath('data.drafts.0.add_ons', []);

        $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $tableA->id,
        ])->assertStatus(422);
    }

    public function test_waiter_round_submission_is_idempotent_and_consumes_inventory_once(): void
    {
        $this->enableDining();
        $this->putSetting(WebsiteSettingKey::TaxEnabled, '0');

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $beans = Ingredient::factory()->create([
            'current_stock' => '1000.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'is_active' => true,
        ]);
        $variant = $this->makePurchasableVariant('120.00', 'Espresso', PreparationStation::Bar, withRecipe: false);
        $recipe = Recipe::query()->create([
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);
        RecipeLine::query()->create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $beans->id,
            'quantity' => '7.000',
            'measurement_unit' => IngredientUnit::Gram->value,
            'base_quantity' => '7.000',
            'base_measurement_unit' => IngredientUnit::Gram->value,
            'sort_order' => 1,
        ]);
        $addOn = AddOn::factory()->create(['name' => 'Extra Shot', 'default_price' => '30.00', 'is_active' => true]);
        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 2,
            'lines' => [[
                'ingredient_id' => $beans->id,
                'quantity' => '7.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ]],
        ]]);

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->assertCreated()->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 1]],
        ])->assertOk();

        $payload = ['idempotency_key' => 'round-abc-123'];

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), $payload)
            ->assertCreated()
            ->assertJsonPath('data.capabilities.has_unsent_draft', false);

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), $payload)
            ->assertOk();

        $this->assertSame(1, Order::query()->where('dining_session_id', $sessionId)->count());
        $this->assertSame('972.000', $beans->fresh()->current_stock);

        $order = Order::query()->where('dining_session_id', $sessionId)->firstOrFail();
        $this->assertDatabaseHas('order_item_add_ons', [
            'order_item_id' => $order->items()->first()->id,
            'name' => 'Extra Shot',
            'unit_price' => '30.00',
        ]);
    }

    public function test_ready_to_serve_requires_all_stations_and_waiter_cannot_confirm_upi(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $barVariant = $this->makePurchasableVariant('50.00', 'Latte', PreparationStation::Bar);
        $kitchenVariant = $this->makePurchasableVariant('80.00', 'Pasta', PreparationStation::Kitchen);

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $barVariant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $kitchenVariant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'mixed-1',
        ])->assertCreated();

        $order = Order::query()->where('dining_session_id', $sessionId)->firstOrFail();
        $bar = OrderPreparation::query()->where('order_id', $order->id)->where('station', PreparationStation::Bar->value)->firstOrFail();
        $kitchen = OrderPreparation::query()->where('order_id', $order->id)->where('station', PreparationStation::Kitchen->value)->firstOrFail();

        $bar->forceFill(['status' => OrderPreparationStatus::Ready])->save();
        $kitchen->forceFill(['status' => OrderPreparationStatus::Preparing])->save();

        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $table->id);
        $this->assertSame('preparing', $row['display_state']);

        $kitchen->forceFill(['status' => OrderPreparationStatus::Ready])->save();
        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $table->id);
        $this->assertSame('ready_to_serve', $row['display_state']);

        $session = DiningSession::query()->findOrFail($sessionId);
        $this->assertFalse($waiter->can('confirmPayment', $session));
        $this->assertFalse($waiter->can('rejectPaymentProof', $session));
        $this->assertTrue($waiter->can('markCashReceived', $session));
    }

    public function test_request_bill_blocks_unsent_draft_unless_discarded(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant('40.00', 'Tea');

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'r1',
        ])->assertCreated();

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))
            ->assertStatus(422);

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId), [
            'discard_draft' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value);
    }

    protected function makePurchasableVariant(
        string $price,
        string $name = 'Drink',
        PreparationStation $station = PreparationStation::Bar,
        bool $withRecipe = true,
    ): ProductVariant {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);

        if ($withRecipe) {
            $ingredient = Ingredient::factory()->create([
                'is_active' => true,
                'current_stock' => '10000.000',
                'measurement_unit' => IngredientUnit::Gram,
                'base_measurement_unit' => IngredientUnit::Gram,
            ]);
            $recipe = Recipe::query()->create([
                'product_variant_id' => $variant->id,
                'is_active' => true,
            ]);
            RecipeLine::query()->create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => '1.000',
                'measurement_unit' => IngredientUnit::Gram->value,
                'base_quantity' => '1.000',
                'base_measurement_unit' => IngredientUnit::Gram->value,
                'sort_order' => 1,
            ]);
        }

        return $variant;
    }

    protected function enableDining(): void
    {
        $this->putSetting(WebsiteSettingKey::FulfilmentDineInEnabled, '1');
        $this->putSetting(WebsiteSettingKey::OrderingManualClosed, '0');
    }

    protected function putSetting(WebsiteSettingKey $key, ?string $value): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $value,
            ],
        );
    }
}
