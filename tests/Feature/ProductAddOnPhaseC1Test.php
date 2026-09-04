<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\PaymentMethod;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\AddOn;
use App\Models\CafeTable;
use App\Models\Cart;
use App\Models\CustomerReward;
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
use App\Services\Cart\CartServiceInterface;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderInventory\OrderInventoryConsumptionServiceInterface;
use App\Support\AddOnConfiguration;
use App\Transfers\Cart\CartItemTransfer;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductAddOnPhaseC1Test extends TestCase
{
    use RefreshDatabase;

    public function test_product_assignment_applies_price_override(): void
    {
        $variant = $this->makePurchasableVariant('80.00');
        $addOn = AddOn::factory()->create([
            'name' => 'Extra Shot',
            'default_price' => '20.00',
            'is_active' => true,
        ]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'price_override' => '15.00',
            'max_quantity' => 2,
            'sort_order' => 10,
        ]]);

        $catalog = app(AddOnServiceInterface::class)->catalogAddOnsForProduct($variant->product->fresh());

        $this->assertCount(1, $catalog);
        $this->assertSame('15.00', $catalog[0]['price']);
        $this->assertSame(2, $catalog[0]['max_quantity']);
    }

    public function test_cart_merges_same_configuration_and_keeps_different_configurations_separate(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('100.00');
        $addOn = AddOn::factory()->create(['default_price' => '10.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 3,
        ]]);

        $carts = app(CartServiceInterface::class);

        $first = new CartItemTransfer;
        $first->setProductVariantId($variant->id);
        $first->setQuantity(1);
        $first->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 1]]);
        $carts->addItem($customer, $first);

        $same = new CartItemTransfer;
        $same->setProductVariantId($variant->id);
        $same->setQuantity(2);
        $same->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 1]]);
        $carts->addItem($customer, $same);

        $different = new CartItemTransfer;
        $different->setProductVariantId($variant->id);
        $different->setQuantity(1);
        $different->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 2]]);
        $carts->addItem($customer, $different);

        $cart = Cart::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(2, $cart->items()->count());

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'configuration_hash' => AddOnConfiguration::hash((int) $variant->id, [
                ['add_on_id' => $addOn->id, 'quantity' => 1],
            ]),
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'configuration_hash' => AddOnConfiguration::hash((int) $variant->id, [
                ['add_on_id' => $addOn->id, 'quantity' => 2],
            ]),
            'quantity' => 1,
        ]);
    }

    public function test_forged_client_add_on_price_is_ignored(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('50.00');
        $addOn = AddOn::factory()->create(['default_price' => '12.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'price_override' => '8.00',
            'max_quantity' => 1,
        ]]);

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId($variant->id);
        $transfer->setQuantity(1);
        $transfer->setAddOns([
            ['add_on_id' => $addOn->id, 'quantity' => 1, 'unit_price' => '999.00'],
        ]);

        $cart = app(CartServiceInterface::class)->addItem($customer, $transfer);
        $item = $cart->items->firstOrFail();

        $this->assertDatabaseHas('cart_item_add_ons', [
            'cart_item_id' => $item->id,
            'add_on_id' => $addOn->id,
            'unit_price' => '8.00',
        ]);

        $summary = app(CartServiceInterface::class)->summarize($cart);
        $this->assertSame('58.00', $summary['subtotal']);
    }

    public function test_free_drink_waives_base_price_only(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('100.00');
        $addOn = AddOn::factory()->create(['default_price' => '25.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 1,
        ]]);

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId($variant->id);
        $transfer->setQuantity(1);
        $transfer->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 1]]);
        app(CartServiceInterface::class)->addItem($customer, $transfer);

        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);

        $cart = app(CartServiceInterface::class)->addFreeDrinkRewardToCart($customer, (int) $reward->id);
        $summary = app(CartServiceInterface::class)->summarize($cart);

        $this->assertSame('125.00', $summary['subtotal']);
        $this->assertSame('100.00', $summary['referral_rewards'][0]['original_amount'] ?? null);
        $this->assertTrue(bccomp((string) $summary['free_drink_benefit'], '0', 2) > 0);
        $this->assertTrue(bccomp((string) $summary['total'], '125.00', 2) < 0);
    }

    public function test_inventory_consumes_base_and_add_on_quantities_for_shared_ingredient(): void
    {
        $milk = Ingredient::factory()->create([
            'name' => 'Milk',
            'is_active' => true,
            'current_stock' => '1000.000',
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
        ]);

        $variant = $this->makePurchasableVariant('90.00');
        $product = $variant->product;

        $recipe = Recipe::query()->create([
            'product_variant_id' => $variant->id,
            'is_active' => true,
        ]);
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
            'max_quantity' => 1,
            'lines' => [[
                'ingredient_id' => $milk->id,
                'quantity' => '50.000',
                'measurement_unit' => IngredientUnit::Milliliter->value,
            ]],
        ]]);

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

        $this->assertSame('850.000', $milk->fresh()->current_stock);
        $this->assertDatabaseHas('order_inventory_consumptions', [
            'order_item_id' => $orderItem->id,
            'ingredient_id' => $milk->id,
            'source_type' => 'base_recipe',
            'quantity' => '100.000',
        ]);
        $this->assertDatabaseHas('order_inventory_consumptions', [
            'order_item_id' => $orderItem->id,
            'ingredient_id' => $milk->id,
            'source_type' => 'add_on',
            'quantity' => '50.000',
        ]);
    }

    public function test_manager_can_manage_add_ons_but_operator_cannot(): void
    {
        $manager = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.add-ons.index'))
            ->assertOk();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.add-ons.store'), [
                'name' => 'Oat Milk',
                'default_price' => '18.00',
                'is_active' => 1,
                'sort_order' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('add_ons', ['name' => 'Oat Milk']);

        $this->actingAs($operator, 'admin')
            ->get(route('administrator.add-ons.index'))
            ->assertForbidden();

        $this->actingAs($operator, 'admin')
            ->post(route('administrator.add-ons.store'), [
                'name' => 'Blocked',
                'default_price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_inactive_and_unassigned_add_ons_are_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('60.00');
        $inactive = AddOn::factory()->inactive()->create(['default_price' => '5.00']);
        $unassigned = AddOn::factory()->create(['default_price' => '5.00', 'is_active' => true]);
        $assigned = AddOn::factory()->create(['default_price' => '5.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $assigned->id,
            'max_quantity' => 1,
        ]]);

        $carts = app(CartServiceInterface::class);

        foreach ([$inactive, $unassigned] as $blocked) {
            $transfer = new CartItemTransfer;
            $transfer->setProductVariantId($variant->id);
            $transfer->setQuantity(1);
            $transfer->setAddOns([['add_on_id' => $blocked->id, 'quantity' => 1]]);

            try {
                $carts->addItem($customer, $transfer);
                $this->fail('Expected validation exception for blocked add-on '.$blocked->id);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('add_ons.0.add_on_id', $exception->errors());
            }
        }
    }

    public function test_max_quantity_is_enforced(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('60.00');
        $addOn = AddOn::factory()->create(['default_price' => '5.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 2,
        ]]);

        $transfer = new CartItemTransfer;
        $transfer->setProductVariantId($variant->id);
        $transfer->setQuantity(1);
        $transfer->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 3]]);

        $this->expectException(ValidationException::class);
        app(CartServiceInterface::class)->addItem($customer, $transfer);
    }

    public function test_order_add_on_snapshots_survive_catalog_rename_and_price_change(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('100.00');
        $addOn = AddOn::factory()->create([
            'name' => 'Extra Shot',
            'default_price' => '20.00',
            'is_active' => true,
        ]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 1,
        ]]);

        $transfer = app(OrderTransferInterface::class);
        $transfer->setCustomerId($customer->id);
        $transfer->setCustomerName($customer->name);
        $transfer->setCustomerEmail($customer->email);
        $transfer->setCustomerPhone($customer->phone);
        $transfer->setPickupName($customer->name);
        $transfer->setPickupPhone($customer->phone);
        $transfer->setFulfilmentMethod(OrderFulfilmentMethod::Takeaway->value);
        $transfer->setPaymentMethod(PaymentMethod::Manual->apiKey());
        $transfer->setItems([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'add_ons' => [['add_on_id' => $addOn->id, 'quantity' => 1]],
            ],
        ]);

        $order = app(OrderServiceInterface::class)->store($customer, $transfer);

        $addOn->forceFill([
            'name' => 'Renamed Shot',
            'default_price' => '99.00',
        ])->save();

        $snapshot = $order->fresh('items.addOns')->items->first()->addOns->first();
        $this->assertSame('Extra Shot', $snapshot->name);
        $this->assertSame('20.00', number_format((float) $snapshot->unit_price, 2, '.', ''));
    }

    public function test_dining_round_persists_add_on_snapshots_and_consumes_once(): void
    {
        foreach ([
            [WebsiteSettingKey::FulfilmentDineInEnabled, '1'],
            [WebsiteSettingKey::OrderingManualClosed, '0'],
            [WebsiteSettingKey::TaxEnabled, '0'],
        ] as [$key, $value]) {
            WebsiteSetting::query()->updateOrCreate(
                ['key' => $key->value],
                [
                    'section' => $key->section(),
                    'value_type' => $key->valueType(),
                    'value' => $value,
                ],
            );
        }

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $beans = Ingredient::factory()->create([
            'name' => 'Coffee Beans',
            'is_active' => true,
            'current_stock' => '1000.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
        ]);

        $variant = $this->makePurchasableVariant('120.00');
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

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, null, $waiter);
        $dining->addDraftItem($session, (int) $variant->id, 2, $waiter, [
            ['add_on_id' => $addOn->id, 'quantity' => 1],
        ]);
        $order = $dining->placeRound($session->fresh(), $waiter);

        $item = $order->fresh('items.addOns')->items->first();
        $this->assertCount(1, $item->addOns);
        $this->assertSame('Extra Shot', $item->addOns->first()->name);
        $this->assertSame('30.00', number_format((float) $item->addOns->first()->unit_price, 2, '.', ''));
        $this->assertSame('300.00', number_format((float) $item->line_subtotal, 2, '.', ''));
        $this->assertSame('972.000', $beans->fresh()->current_stock);

        app(OrderInventoryConsumptionServiceInterface::class)
            ->consumeForAcceptedOrder($order->fresh(), $waiter);
        $this->assertSame('972.000', $beans->fresh()->current_stock);
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => PreparationStation::Bar,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
