<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Parsers\Order\OrderParser;
use App\Parsers\Order\OrderParserInterface;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Order\OrderService;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Order\OrderFilterTransfer;
use App\Transfers\Order\OrderFilterTransferInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransfer;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_architecture_bindings_and_schema_exist(): void
    {
        $this->assertInstanceOf(OrderRepository::class, $this->app->make(OrderRepositoryInterface::class));
        $this->assertInstanceOf(OrderService::class, $this->app->make(OrderServiceInterface::class));
        $this->assertInstanceOf(OrderParser::class, $this->app->make(OrderParserInterface::class));
        $this->assertInstanceOf(OrderTransfer::class, $this->app->make(OrderTransferInterface::class));
        $this->assertInstanceOf(OrderFilterTransfer::class, $this->app->make(OrderFilterTransferInterface::class));
        $this->assertInstanceOf(OrderStatusTransitionTransfer::class, $this->app->make(OrderStatusTransitionTransferInterface::class));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('order_items'));
        $this->assertTrue(Schema::hasTable('order_status_histories'));
        $this->assertTrue(Schema::hasColumn('orders', 'order_number'));
        $this->assertTrue(Schema::hasColumn('order_items', 'recipe_id'));
    }

    public function test_manager_can_create_order_with_totals_snapshots_and_unique_order_numbers(): void
    {
        Carbon::setTestNow('2026-08-29 10:15:00');

        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create([
            'name' => 'Aarav Customer',
            'email' => 'aarav@example.test',
        ]);
        $latte = $this->makeVariantWithRecipe('Cafe Latte', 'Regular', '80.25');
        $mocha = $this->makeVariantWithRecipe('Cafe Mocha', 'Large', '99.10');

        $this->actingAs($manager, 'admin')->post(route('administrator.orders.store'), [
            'customer_id' => $customer->id,
            'customer_notes' => 'Less sugar, please.',
            'items' => [
                ['product_variant_id' => $latte->id, 'quantity' => 2],
                ['product_variant_id' => $mocha->id, 'quantity' => 1],
                ['product_variant_id' => null, 'quantity' => null],
            ],
        ])->assertRedirect();

        $this->actingAs($manager, 'admin')->post(route('administrator.orders.store'), [
            'customer_id' => null,
            'items' => [
                ['product_variant_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $firstOrder = Order::query()->orderBy('id')->firstOrFail();
        $secondOrder = Order::query()->orderByDesc('id')->firstOrFail();

        $this->assertSame('CC-290826-0001', $firstOrder->order_number);
        $this->assertSame('CC-290826-0002', $secondOrder->order_number);
        $this->assertSame('259.60', $firstOrder->total_amount);
        $this->assertSame(OrderStatus::PendingPayment, $firstOrder->status);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $firstOrder->id,
            'product_name' => 'Cafe Latte',
            'variant_name' => 'Regular',
            'unit_price' => '80.25',
            'quantity' => 2,
            'line_subtotal' => '160.50',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $firstOrder->id,
            'product_name' => 'Cafe Mocha',
            'variant_name' => 'Large',
            'unit_price' => '99.10',
            'quantity' => 1,
            'line_subtotal' => '99.10',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $firstOrder->id,
            'from_status' => null,
            'to_status' => OrderStatus::PendingPayment->value,
        ]);
    }

    public function test_order_item_snapshots_do_not_change_after_product_or_variant_updates(): void
    {
        $manager = User::factory()->manager()->create();
        $variant = $this->makeVariantWithRecipe('Hazelnut Latte', 'Regular', '85.00');

        $this->actingAs($manager, 'admin')->post(route('administrator.orders.store'), [
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $order = Order::query()->with('items')->firstOrFail();

        $variant->product->update(['name' => 'Renamed Latte']);
        $variant->update(['name' => 'XL']);

        $this->assertSame('Hazelnut Latte', $order->fresh('items')->items[0]->product_name);
        $this->assertSame('Regular', $order->fresh('items')->items[0]->variant_name);
    }

    public function test_administrator_can_progress_order_and_status_history_is_audited(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->createPendingOrder();

        $this->actingAs($manager, 'admin')->patch(route('administrator.orders.status.update', $order), [
            'status' => OrderStatus::PaymentConfirmed->value,
            'notes' => 'Payment confirmed from WhatsApp screenshot.',
        ])->assertRedirect(route('administrator.orders.show', $order));

        $this->actingAs($manager, 'admin')->patch(route('administrator.orders.status.update', $order), [
            'status' => OrderStatus::Accepted->value,
        ])->assertRedirect(route('administrator.orders.show', $order));

        $this->actingAs($manager, 'admin')->patch(route('administrator.orders.status.update', $order), [
            'status' => OrderStatus::Preparing->value,
        ])->assertRedirect(route('administrator.orders.show', $order));

        $this->actingAs($manager, 'admin')->patch(route('administrator.orders.status.update', $order), [
            'status' => OrderStatus::ReadyForPickup->value,
        ])->assertRedirect(route('administrator.orders.show', $order));

        $this->actingAs($manager, 'admin')->patch(route('administrator.orders.status.update', $order), [
            'status' => OrderStatus::Completed->value,
        ])->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertNotNull($order->accepted_at);
        $this->assertNotNull($order->preparing_at);
        $this->assertNotNull($order->ready_for_pickup_at);
        $this->assertNotNull($order->completed_at);
        $this->assertCount(5, $order->statusHistory()->whereNotNull('from_status')->get());
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::PaymentConfirmed->value,
            'notes' => 'Payment confirmed from WhatsApp screenshot.',
        ]);
    }

    public function test_invalid_status_transitions_are_blocked_and_do_not_mutate_order(): void
    {
        $manager = User::factory()->manager()->create();
        $order = $this->createPendingOrder();

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.orders.show', $order))
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::Preparing->value,
            ])
            ->assertRedirect(route('administrator.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_admin_and_barista_order_detail_share_shell_with_role_specific_financial_controls(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $order = $this->createPendingOrder();
        $order->update([
            'status' => OrderStatus::PaymentConfirmed->value,
            'payment_status' => 'confirmed',
            'payment_confirmed_at' => now(),
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.show', $order))
            ->assertOk()
            ->assertSee('Order detail', false)
            ->assertSee('Preparation Detail', false)
            ->assertSee('Status History', false)
            ->assertSee('Subtotal', false)
            ->assertSee('Invoice', false)
            ->assertSee(route('administrator.orders.invoice.print', $order), false);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.orders.show', $order))
            ->assertOk()
            ->assertSee('Order detail', false)
            ->assertSee('Preparation Detail', false)
            ->assertSee('Status History', false)
            ->assertDontSee('Subtotal', false)
            ->assertDontSee('Invoice', false)
            ->assertDontSee(route('administrator.orders.invoice.print', $order), false);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.show', $order))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.orders.invoice.pdf', $order))
            ->assertForbidden();
    }

    public function test_barista_can_view_and_update_only_operational_statuses_without_financial_recipe_data(): void
    {
        $barista = User::factory()->barista()->create(['name' => 'Shift Barista']);
        $order = $this->createPendingOrder();
        $order->update([
            'status' => OrderStatus::PaymentConfirmed->value,
            'payment_confirmed_at' => now(),
        ]);
        $order->statusHistory()->create([
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::PaymentConfirmed->value,
            'changed_by' => User::factory()->manager()->create()->id,
        ]);

        $this->actingAs($barista, 'admin')
            ->get(route('barista.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($order->items()->firstOrFail()->product_name)
            ->assertSee('Preparation Detail')
            ->assertSee('Order detail')
            ->assertSee('Status History')
            ->assertSee('Customer')
            ->assertDontSee('Subtotal')
            ->assertDontSee('Line total')
            ->assertDontSee('Invoice')
            ->assertDontSee('Download PDF')
            ->assertDontSee('Print A4')
            ->assertDontSee('Production Cost')
            ->assertDontSee('Margin');

        $this->actingAs($barista, 'admin')->patch(route('barista.orders.status.update', $order), [
            'status' => OrderStatus::Accepted->value,
        ])->assertRedirect(route('barista.orders.show', $order));

        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
        $this->assertSame($barista->id, $order->fresh()->assigned_barista_id);

        $this->actingAs($barista, 'admin')
            ->from(route('barista.orders.show', $order))
            ->patch(route('barista.orders.status.update', $order), [
                'status' => OrderStatus::Cancelled->value,
            ])
            ->assertRedirect(route('barista.orders.show', $order))
            ->assertSessionHasErrors('status');
    }

    public function test_order_filters_and_barista_visibility_work_as_expected(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create(['name' => 'Asha']);
        $customer = User::factory()->customer()->create(['name' => 'Nina Customer']);

        $matchingOrder = $this->createPendingOrder(customer: $customer, productName: 'Iced Vanilla Latte');
        $matchingOrder->update([
            'status' => OrderStatus::Accepted->value,
            'assigned_barista_id' => $barista->id,
        ]);

        $hiddenOrder = $this->createPendingOrder(productName: 'Hot Mocha');
        $hiddenOrder->update([
            'status' => OrderStatus::Cancelled->value,
        ]);

        $adminIndex = $this->actingAs($manager, 'admin')
            ->get(route('administrator.orders.index', [
                'search' => 'Vanilla',
                'status' => OrderStatus::Accepted->value,
                'customer_id' => $customer->id,
                'assigned_barista_id' => $barista->id,
            ]))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<tbody[^>]*>.*'.preg_quote($matchingOrder->order_number, '/').'.*<\/tbody>/s',
            $adminIndex->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<tbody[^>]*>.*'.preg_quote($hiddenOrder->order_number, '/').'.*<\/tbody>/s',
            $adminIndex->getContent(),
        );

        $baristaIndex = $this->actingAs($barista, 'admin')
            ->get(route('barista.orders.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/<tbody[^>]*>.*'.preg_quote($matchingOrder->order_number, '/').'.*<\/tbody>/s',
            $baristaIndex->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<tbody[^>]*>.*'.preg_quote($hiddenOrder->order_number, '/').'.*<\/tbody>/s',
            $baristaIndex->getContent(),
        );
    }

    public function test_customer_and_wrong_role_access_to_internal_order_routes_is_blocked(): void
    {
        $customer = User::factory()->customer()->create();
        $manager = User::factory()->manager()->create();
        $order = $this->createPendingOrder();

        $this->actingAs($customer, 'admin')
            ->get(route('administrator.orders.index'))
            ->assertForbidden();

        $this->actingAs($customer, 'admin')
            ->get(route('barista.orders.index'))
            ->assertForbidden();

        $this->actingAs($manager, 'admin')
            ->get(route('barista.orders.show', $order))
            ->assertForbidden();
    }

    protected function createPendingOrder(?User $customer = null, string $productName = 'Cafe Latte'): Order
    {
        $variant = $this->makeVariantWithRecipe($productName, 'Regular', '75.00');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')->post(route('administrator.orders.store'), [
            'customer_id' => $customer?->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ])->assertRedirect();

        return Order::query()
            ->latest('id')
            ->with(['items.recipe.lines.ingredient.brand', 'statusHistory.changedBy'])
            ->firstOrFail();
    }

    protected function makeVariantWithRecipe(string $productName, string $variantName, string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'customer_ingredient_summary' => 'Espresso, milk, syrup',
            'is_active' => true,
            'is_available' => true,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);

        $ingredient = Ingredient::factory()->create([
            'ingredient_category_id' => IngredientCategory::factory()->create()->id,
            'name' => 'Espresso Beans '.fake()->unique()->word(),
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'cost_per_unit' => '0.8000',
        ]);

        $recipe = Recipe::factory()->create([
            'product_variant_id' => $variant->id,
            'preparation_notes' => 'Steam and pour.',
        ]);

        $recipe->lines()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => '18.000',
            'measurement_unit' => IngredientUnit::Gram->value,
            'base_quantity' => '18.000',
            'base_measurement_unit' => IngredientUnit::Gram->value,
            'sort_order' => 1,
        ]);

        return $variant->fresh(['product', 'recipe.lines.ingredient.brand']);
    }
}
