<?php

namespace Tests\Feature;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\ProductType;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\CustomerReward;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\OrderItem;
use App\Models\OrderRewardRedemption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderInventory\OrderInventoryConsumptionServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderInventoryConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_order_consumes_recipe_with_quantity_multiplication(): void
    {
        $operator = User::factory()->operator()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $milk = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '5000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Cappuccino', [
            [$coffee, '4.000'],
            [$milk, '250.000'],
        ]);

        $order = $this->makePayableOrder($variant, quantity: 2);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame('992.000', $coffee->fresh()->current_stock);
        $this->assertSame('4500.000', $milk->fresh()->current_stock);
        $this->assertSame(2, OrderInventoryConsumption::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleConsumption->value)
            ->count());
    }

    public function test_consumption_is_exactly_once_on_repeated_accept_path(): void
    {
        $operator = User::factory()->operator()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Espresso', [
            [$coffee, '10.000'],
        ]);
        $order = $this->makePayableOrder($variant, quantity: 1);

        $service = app(OrderServiceInterface::class);
        $service->transition($order, $operator, $this->statusTransfer(OrderStatus::Accepted));

        app(OrderInventoryConsumptionServiceInterface::class)
            ->consumeForAcceptedOrder($order->fresh(), $operator);

        $this->assertSame('990.000', $coffee->fresh()->current_stock);
        $this->assertSame(1, OrderInventoryConsumption::query()->where('order_id', $order->id)->count());
    }

    public function test_food_and_beverage_and_mixed_stations_consume(): void
    {
        $operator = User::factory()->operator()->create();
        $beans = $this->makeIngredient('Beans', IngredientUnit::Gram, '1000.000');
        $bread = $this->makeIngredient('Bread', IngredientUnit::Piece, '50.000');

        $drink = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$beans, '8.000']], ProductType::Beverage);
        $food = $this->makeVariantWithRecipe(PreparationStation::Kitchen, 'Sandwich', [[$bread, '1.000']], ProductType::Food);

        $order = $this->makePayableOrderMulti([
            [$drink, 1],
            [$food, 2],
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame('992.000', $beans->fresh()->current_stock);
        $this->assertSame('48.000', $bread->fresh()->current_stock);
        $this->assertSame(2, $order->fresh()->preparations()->count());
    }

    public function test_dining_rounds_consume_independently_and_bill_does_not(): void
    {
        $this->enableDining();
        $this->putSetting(WebsiteSettingKey::TaxEnabled, '0');

        $waiter = User::factory()->waiter()->create();
        $milk = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '2000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$milk, '200.000']]);
        $table = CafeTable::factory()->create(['is_active' => true]);

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, null, $waiter, ['guest_count' => 2]);

        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $round1 = $dining->placeRound($session, $waiter);
        $this->assertSame('2000.000', $milk->fresh()->current_stock);
        $this->assertSame(0, OrderInventoryConsumption::query()->where('order_id', $round1->id)->count());

        $dining->acceptRound($session->fresh(), $round1->fresh(), $waiter);
        $this->assertSame('1800.000', $milk->fresh()->current_stock);
        $this->assertSame(1, OrderInventoryConsumption::query()->where('order_id', $round1->id)->count());

        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $waiter);
        $round2 = $dining->placeRound($session->fresh(), $waiter);
        $this->assertSame('1800.000', $milk->fresh()->current_stock);

        $dining->acceptRound($session->fresh(), $round2->fresh(), $waiter);
        $this->assertSame('1600.000', $milk->fresh()->current_stock);

        $beforeBill = $milk->fresh()->current_stock;
        $session = $dining->generateFinalBill($session->fresh(), $waiter);
        $session = $dining->markCashReceived($session, $waiter);
        $dining->closeSession($session, $waiter);

        $this->assertSame($beforeBill, $milk->fresh()->current_stock);
        $this->assertSame(2, OrderInventoryConsumption::query()->count());
    }

    public function test_promotion_and_free_drink_do_not_reduce_consumption(): void
    {
        $operator = User::factory()->operator()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '10.000']]);

        Promotion::factory()->automatic()->percentage(50)->create(['name' => 'Half']);

        $order = $this->makePayableOrder($variant, quantity: 1);
        $order->forceFill(['discount_total' => '50.00'])->save();

        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $order->customer_id,
            'status' => CustomerRewardStatus::Redeemed,
            'redeemed_order_id' => $order->id,
            'redeemed_at' => now(),
        ]);
        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => $reward->id,
            'reward_type' => CustomerRewardType::FreeDrink,
            'description_snapshot' => 'Free drink',
            'benefit_amount' => '100.00',
            'original_amount' => '100.00',
            'preserved_taxable_amount' => '100.00',
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        app(OrderServiceInterface::class)->transition(
            $order,
            $operator,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame('990.000', $coffee->fresh()->current_stock);
    }

    public function test_cancel_before_preparing_creates_reversal_using_original_qty(): void
    {
        $admin = User::factory()->manager()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '15.000']]);
        $order = $this->makePayableOrder($variant, quantity: 2);

        $orders = app(OrderServiceInterface::class);
        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::Accepted));
        $this->assertSame('970.000', $coffee->fresh()->current_stock);

        // Recipe change after sale must not affect reversal qty.
        RecipeLine::query()->where('recipe_id', $variant->recipe->id)->update(['quantity' => '99.000', 'base_quantity' => '99.000']);

        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Cancelled));

        $this->assertSame('1000.000', $coffee->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleReversal->value)
            ->count());
        $this->assertNotNull(OrderInventoryConsumption::query()->where('order_id', $order->id)->first()?->reversed_at);
        $this->assertSame(1, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleConsumption->value)
            ->count());
    }

    public function test_preparing_or_ready_ticket_blocks_automatic_reversal(): void
    {
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '10.000']]);
        $order = $this->makePayableOrder($variant, quantity: 1);

        $orders = app(OrderServiceInterface::class);
        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::Accepted));

        $ticket = $order->fresh('preparations')->preparations->first();
        $prep = app(OrderPreparationServiceInterface::class);
        $prep->transition($ticket, $barista, OrderPreparationStatus::Accepted);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Preparing);

        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Cancelled));

        $this->assertSame('990.000', $coffee->fresh()->current_stock);
        $this->assertSame(0, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleReversal->value)
            ->count());
    }

    public function test_mixed_order_one_station_preparing_blocks_full_reversal(): void
    {
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $beans = $this->makeIngredient('Beans', IngredientUnit::Gram, '1000.000');
        $bread = $this->makeIngredient('Bread', IngredientUnit::Piece, '20.000');
        $drink = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$beans, '10.000']]);
        $food = $this->makeVariantWithRecipe(PreparationStation::Kitchen, 'Sandwich', [[$bread, '1.000']], ProductType::Food);

        $order = $this->makePayableOrderMulti([[$drink, 1], [$food, 1]]);
        $orders = app(OrderServiceInterface::class);
        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::Accepted));

        $bar = $order->fresh('preparations')->preparations->firstWhere('station', PreparationStation::Bar);
        $prep = app(OrderPreparationServiceInterface::class);
        $prep->transition($bar, $barista, OrderPreparationStatus::Accepted);
        $prep->transition($bar->fresh(), $barista, OrderPreparationStatus::Preparing);

        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Cancelled));

        $this->assertSame('990.000', $beans->fresh()->current_stock);
        $this->assertSame('19.000', $bread->fresh()->current_stock);
        $this->assertSame(0, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleReversal->value)
            ->count());
    }

    public function test_insufficient_stock_rejects_acceptance_without_tickets(): void
    {
        $operator = User::factory()->operator()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '5.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '10.000']]);
        $order = $this->makePayableOrder($variant, quantity: 1);

        try {
            app(OrderServiceInterface::class)->transition(
                $order,
                $operator,
                $this->statusTransfer(OrderStatus::Accepted),
            );
            $this->fail('Expected insufficient stock to fail acceptance.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('inventory', $e->errors());
        }

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertSame(0, $order->fresh()->preparations()->count());
        $this->assertSame('5.000', $coffee->fresh()->current_stock);
    }

    public function test_preparation_ticket_transitions_do_not_consume_stock(): void
    {
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '10.000']]);
        $order = $this->makePayableOrder($variant, quantity: 1);

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $stockAfterAccept = $coffee->fresh()->current_stock;
        $ticket = $order->fresh('preparations')->preparations->first();
        $prep = app(OrderPreparationServiceInterface::class);
        $prep->transition($ticket, $barista, OrderPreparationStatus::Accepted);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Preparing);
        $prep->transition($ticket->fresh(), $barista, OrderPreparationStatus::Ready);

        $this->assertSame($stockAfterAccept, $coffee->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleConsumption->value)
            ->count());
    }

    public function test_reversal_is_idempotent(): void
    {
        $admin = User::factory()->manager()->create();
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $variant = $this->makeVariantWithRecipe(PreparationStation::Bar, 'Latte', [[$coffee, '10.000']]);
        $order = $this->makePayableOrder($variant, quantity: 1);

        $orders = app(OrderServiceInterface::class);
        $orders->transition($order, $admin, $this->statusTransfer(OrderStatus::Accepted));
        $orders->transition($order->fresh(), $admin, $this->statusTransfer(OrderStatus::Cancelled));

        app(OrderInventoryConsumptionServiceInterface::class)
            ->reverseForCancelledOrder($order->fresh(), $admin);

        $this->assertSame('1000.000', $coffee->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::query()
            ->where('transaction_type', InventoryTransactionType::SaleReversal->value)
            ->count());
    }

    public function test_operator_cannot_manually_post_sale_consumption_type(): void
    {
        $operator = User::factory()->operator()->create();
        $ingredient = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '1000.000');

        $this->actingAs($operator, 'admin')
            ->post(route('administrator.inventory.movements.store'), [
                'ingredient_id' => $ingredient->id,
                'transaction_type' => InventoryTransactionType::SaleConsumption->value,
                'quantity' => '10',
                'measurement_unit' => IngredientUnit::Milliliter->value,
            ])
            ->assertForbidden();
    }

    protected function makeIngredient(string $name, IngredientUnit $unit, string $stock): Ingredient
    {
        return Ingredient::factory()->create([
            'ingredient_category_id' => IngredientCategory::factory(),
            'name' => $name,
            'measurement_unit' => $unit,
            'base_measurement_unit' => $unit->baseUnit(),
            'current_stock' => $stock,
            'minimum_stock' => '1.000',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{0: Ingredient, 1: string}>  $lines
     */
    protected function makeVariantWithRecipe(
        PreparationStation $station,
        string $name,
        array $lines,
        ProductType $type = ProductType::Beverage,
    ): ProductVariant {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
            'product_type' => $type,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '100.00',
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);

        $recipe = Recipe::query()->create([
            'product_variant_id' => $variant->id,
            'version' => 1,
            'is_active' => true,
        ]);

        foreach ($lines as $index => [$ingredient, $qty]) {
            RecipeLine::query()->create([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredient->id,
                'quantity' => $qty,
                'measurement_unit' => $ingredient->base_measurement_unit->value,
                'base_quantity' => $qty,
                'base_measurement_unit' => $ingredient->base_measurement_unit->value,
                'sort_order' => $index,
            ]);
        }

        return $variant->fresh('recipe.lines');
    }

    protected function makePayableOrder(ProductVariant $variant, int $quantity = 1): Order
    {
        return $this->makePayableOrderMulti([[$variant, $quantity]]);
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $items
     */
    protected function makePayableOrderMulti(array $items): Order
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_confirmed_at' => now(),
        ]);

        foreach ($items as [$variant, $quantity]) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'recipe_id' => $variant->recipe?->id,
                'preparation_station' => $variant->product?->preparation_station ?? PreparationStation::Bar,
                'product_name' => $variant->product?->name ?? 'Item',
                'variant_name' => $variant->name,
                'unit_price' => $variant->price,
                'quantity' => $quantity,
                'line_subtotal' => bcmul((string) $variant->price, (string) $quantity, 2),
            ]);
        }

        return $order->fresh('items');
    }

    protected function statusTransfer(OrderStatus $status): OrderStatusTransitionTransfer
    {
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus($status->value);

        return $transfer;
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
