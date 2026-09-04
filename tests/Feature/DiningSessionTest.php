<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_start_session_place_multiple_rounds_and_request_bill(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['code' => 'D1', 'is_active' => true]);
        $variantA = $this->makePurchasableVariant('10.00', 'Latte');
        $variantB = $this->makePurchasableVariant('8.00', 'Muffin');

        Sanctum::actingAs($customer);

        $start = $this->postJson(route('api.v1.dining.sessions.store'), [
            'cafe_table_id' => $table->id,
            'guest_count' => 2,
        ])->assertCreated();

        $sessionId = (int) $start->json('data.id');

        $this->postJson(route('api.v1.dining.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variantA->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.dining.sessions.rounds.store', $sessionId))
            ->assertCreated();

        $this->postJson(route('api.v1.dining.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variantB->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson(route('api.v1.dining.sessions.rounds.store', $sessionId))
            ->assertCreated();

        $this->assertSame(2, Order::query()->where('dining_session_id', $sessionId)->count());
        $this->assertTrue(
            Order::query()->where('dining_session_id', $sessionId)->get()->every(
                fn (Order $order): bool => $order->status === OrderStatus::Pending
                    && $order->fulfilment_method === OrderFulfilmentMethod::DineIn
                    && $order->payment_status === PaymentStatus::Pending,
            ),
        );

        $this->postJson(route('api.v1.dining.sessions.request-bill', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value);

        $this->postJson(route('api.v1.dining.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variantA->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->postJson(route('api.v1.dining.sessions.rounds.store', $sessionId))
            ->assertStatus(422);
    }

    public function test_table_cannot_host_two_active_sessions_and_customer_only_one(): void
    {
        $this->enableDining();

        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['code' => 'D2', 'is_active' => true]);
        $dining = app(DiningSessionServiceInterface::class);

        Sanctum::actingAs($customerA);
        $this->postJson(route('api.v1.dining.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->assertCreated();

        Sanctum::actingAs($customerB);
        $this->postJson(route('api.v1.dining.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->assertStatus(422);

        $otherTable = CafeTable::factory()->create(['code' => 'D3', 'is_active' => true]);
        Sanctum::actingAs($customerA);
        $this->postJson(route('api.v1.dining.sessions.store'), [
            'cafe_table_id' => $otherTable->id,
        ])->assertStatus(422);

        $this->assertSame(1, DiningSession::query()->where('customer_id', $customerA->id)->count());
        $this->assertNotNull($dining->findActiveForTable($table));
    }

    public function test_existing_session_survives_manual_ordering_close(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['code' => 'D4', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('6.00');
        $dining = app(DiningSessionServiceInterface::class);

        $session = $dining->startSession($table, $customer, $customer);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);

        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '1'],
        );

        $session->refresh();
        $this->assertSame(DiningSessionStatus::Open, $session->status);
        $this->assertSame(1, $session->orders()->count());
    }

    protected function enableDining(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '0'],
        );
    }

    protected function makePurchasableVariant(string $price, string $name = 'Item'): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
