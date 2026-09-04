<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\IngredientUnit;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\AddOn;
use App\Models\CafeTable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\AddOn\AddOnServiceInterface;
use App\Support\AddOnConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileOrderingJourneyPhaseC2Test extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_merge_preserves_add_on_configurations_without_duplicates(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant(price: '100.00', productName: 'Cappuccino');
        $addOn = AddOn::factory()->create(['default_price' => '20.00', 'is_active' => true]);
        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 3,
        ]]);

        $existingCart = Cart::factory()->create(['customer_id' => $customer->id]);
        $sameHash = AddOnConfiguration::hash((int) $variant->id, [
            ['add_on_id' => $addOn->id, 'quantity' => 1],
        ]);
        CartItem::factory()->create([
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'configuration_hash' => $sameHash,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.cart.merge'), [
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 1]],
                ],
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 2]],
                ],
            ],
            'idempotency_key' => 'c2-merge-addons',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.item_count', 4);

        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variant->id,
            'configuration_hash' => $sameHash,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $existingCart->id,
            'product_variant_id' => $variant->id,
            'configuration_hash' => AddOnConfiguration::hash((int) $variant->id, [
                ['add_on_id' => $addOn->id, 'quantity' => 2],
            ]),
            'quantity' => 1,
        ]);
    }

    public function test_duplicate_bill_request_returns_canonical_awaiting_payment_state(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant(price: '40.00', productName: 'Tea');

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'c2-bill-round',
        ])->assertCreated();

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value);

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value)
            ->assertJsonPath('data.capabilities.can_request_bill', false)
            ->assertJsonPath(
                'data.capabilities.close_blocked_reason',
                'Close the session only after payment is confirmed.',
            )
            ->assertJsonPath('data.capabilities.can_close', false);
    }

    public function test_close_rejected_until_payment_confirmed_then_releases_table(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant(price: '55.00', productName: 'Mocha');

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'c2-close-round',
        ])->assertCreated();
        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.close', $sessionId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);

        $this->postJson(route('api.v1.waiter.sessions.payment-method', $sessionId), [
            'payment_method' => 'cash',
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.cash.receive', $sessionId))->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.close', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Closed->value);

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $sessionId,
            'status' => DiningSessionStatus::Closed->value,
            'payment_status' => PaymentStatus::Confirmed->value,
        ]);

        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $table->id);
        $this->assertSame('available', $row['display_state']);
        $this->assertNull($row['session']);
    }

    public function test_paid_table_display_state_is_distinct_from_payment_pending(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant(price: '45.00', productName: 'Americano');

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'c2-paid-state',
        ])->assertCreated();
        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))->assertOk();

        $pending = collect($this->getJson(route('api.v1.waiter.tables.index'))->json('data'))
            ->firstWhere('id', $table->id);
        $this->assertSame('payment_pending', $pending['display_state']);

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.capabilities.can_change_payment_method', true)
            ->assertJsonPath('data.capabilities.can_request_bill', false)
            ->assertJsonPath('data.capabilities.can_close', false);

        $this->postJson(route('api.v1.waiter.sessions.payment-method', $sessionId), [
            'payment_method' => 'cash',
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.cash.receive', $sessionId))->assertOk();

        // Payment confirmation auto-closes the session and releases the table.
        $paid = collect($this->getJson(route('api.v1.waiter.tables.index'))->json('data'))
            ->firstWhere('id', $table->id);
        $this->assertSame('available', $paid['display_state']);
        $this->assertNull($paid['session']);

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Closed->value)
            ->assertJsonPath('data.capabilities.can_close', false)
            ->assertJsonPath('data.capabilities.can_change_payment_method', false);
    }

    public function test_idempotent_round_key_does_not_skip_new_draft(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant(price: '35.00', productName: 'Espresso');

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'reuse-key',
        ])->assertCreated();

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'reuse-key',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Dining round placed.');

        $this->assertSame(2, Order::query()->where('dining_session_id', $sessionId)->count());
    }

    public function test_refresh_recovers_session_draft_and_rejects_wrong_session_round(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $tableA = CafeTable::factory()->create(['code' => 'A1', 'is_active' => true]);
        $tableB = CafeTable::factory()->create(['code' => 'B2', 'is_active' => true]);
        $variant = $this->makePurchasableVariant(price: '70.00', productName: 'Filter');

        Sanctum::actingAs($waiter);

        $sessionA = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $tableA->id,
        ])->json('data.id');
        $sessionB = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $tableB->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionA), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionA))
            ->assertOk()
            ->assertJsonPath('data.drafts.0.quantity', 2)
            ->assertJsonPath('data.capabilities.has_unsent_draft', true);

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionB), [
            'idempotency_key' => 'wrong-table',
        ])->assertStatus(422);

        $this->getJson(route('api.v1.waiter.sessions.show', $sessionA))
            ->assertOk()
            ->assertJsonPath('data.drafts.0.quantity', 2);
    }

    protected function makePurchasableVariant(
        string $price = '4.75',
        string $productName = 'House Latte',
        string $variantName = 'Regular',
        PreparationStation $station = PreparationStation::Bar,
    ): ProductVariant {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'price' => $price,
            'serving_size_value' => '250',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);

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
