<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\OrderInventory\OrderInventoryConsumptionService;
use App\Services\Reporting\InventoryProductReportingService;
use App\Services\Reporting\InventoryProductReportingServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryProductReportingPhaseF32Test extends TestCase
{
    use RefreshDatabase;

    public function test_sale_consumption_reversal_restock_adjustment_and_net(): void
    {
        $milk = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '5000.000');

        $this->ledger($milk, InventoryTransactionType::SaleConsumption, '500.000');
        $this->ledger($milk, InventoryTransactionType::SaleReversal, '500.000');
        $this->ledger($milk, InventoryTransactionType::StockAdded, '1000.000');
        $this->ledger($milk, InventoryTransactionType::ManualReduction, '100.000');

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
            'ingredient_id' => $milk->id,
        ]);

        $unitRow = collect($report['overview']['period_by_unit'])->firstWhere('unit', IngredientUnit::Milliliter->value);
        $this->assertNotNull($unitRow);
        $this->assertSame('500.000', $unitRow['sale_consumption']);
        $this->assertSame('500.000', $unitRow['sale_reversal']);
        $this->assertSame('1000.000', $unitRow['restocked']);
        $this->assertSame('100.000', $unitRow['adjusted']);
        $this->assertSame('900.000', $unitRow['net_movement']);

        $ingredient = collect($report['ingredients']['rows'])->firstWhere('ingredient_id', $milk->id);
        $this->assertSame('500.000', $ingredient['consumed']);
        $this->assertSame('500.000', $ingredient['reversed']);
        $this->assertSame('1000.000', $ingredient['restocked']);
        $this->assertSame('100.000', $ingredient['adjusted']);
        $this->assertSame('1000.000', $ingredient['net_movement']);
    }

    public function test_date_filter_and_business_timezone_boundary(): void
    {
        $timezone = app(CafeAvailabilityServiceInterface::class)->timezone();
        $beans = $this->makeIngredient('Beans', IngredientUnit::Gram, '1000.000');

        $yesterday = CarbonImmutable::now($timezone)->subDay()->setTime(10, 0)->setTimezone('UTC');
        $today = CarbonImmutable::now($timezone)->setTime(10, 0)->setTimezone('UTC');

        $this->ledger($beans, InventoryTransactionType::SaleConsumption, '10.000', $yesterday);
        $this->ledger($beans, InventoryTransactionType::SaleConsumption, '25.000', $today);

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_CUSTOM,
            'from' => CarbonImmutable::now($timezone)->subDay()->format('Y-m-d'),
            'to' => CarbonImmutable::now($timezone)->subDay()->format('Y-m-d'),
        ]);

        $unitRow = collect($report['overview']['period_by_unit'])->firstWhere('unit', IngredientUnit::Gram->value);
        $this->assertSame('10.000', $unitRow['sale_consumption']);
        $this->assertSame($timezone, $report['timezone']);
    }

    public function test_current_low_and_out_of_stock_state(): void
    {
        $healthy = $this->makeIngredient('Healthy', IngredientUnit::Gram, '100.000', minimum: '5.000', reorder: '10.000');
        $low = $this->makeIngredient('Low', IngredientUnit::Gram, '8.000', minimum: '5.000', reorder: '10.000');
        $oos = $this->makeIngredient('Empty', IngredientUnit::Gram, '0.000', minimum: '5.000', reorder: '10.000');

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
        ]);

        $this->assertSame(1, $report['overview']['healthy']);
        $this->assertSame(1, $report['overview']['low_stock']);
        $this->assertSame(1, $report['overview']['out_of_stock']);

        $lowRow = collect($report['ingredients']['rows'])->firstWhere('ingredient_id', $low->id);
        $oosRow = collect($report['ingredients']['rows'])->firstWhere('ingredient_id', $oos->id);
        $healthyRow = collect($report['ingredients']['rows'])->firstWhere('ingredient_id', $healthy->id);

        $this->assertSame('low_stock', $lowRow['stock_status']);
        $this->assertSame('out_of_stock', $oosRow['stock_status']);
        $this->assertSame('Healthy', $healthyRow['stock_status_label']);
    }

    public function test_dining_inventory_included_unpaid_excluded_from_paid_and_counted_once_when_paid(): void
    {
        $milk = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '2000.000');
        $category = ProductCategory::factory()->create(['name' => 'Coffee']);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Latte',
            'product_type' => ProductType::Beverage,
            'preparation_station' => PreparationStation::Bar,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '150.00',
        ]);

        $unpaidSession = $this->makeDiningSession(PaymentStatus::Pending);
        $unpaidRound = $this->makeDiningRound($unpaidSession, OrderStatus::Accepted, PaymentStatus::Pending);
        $unpaidItem = $this->makeOrderItem($unpaidRound, $product, $variant, quantity: 1, lineSubtotal: '150.00');
        $this->ledger(
            $milk,
            InventoryTransactionType::SaleConsumption,
            '200.000',
            null,
            OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM,
            $unpaidItem->id,
        );

        $paidSession = $this->makeDiningSession(PaymentStatus::Confirmed, paidAt: CarbonImmutable::now('UTC'));
        $paidRound = $this->makeDiningRound($paidSession, OrderStatus::Completed, PaymentStatus::Confirmed);
        $paidItem = $this->makeOrderItem($paidRound, $product, $variant, quantity: 2, lineSubtotal: '300.00');
        $this->ledger(
            $milk,
            InventoryTransactionType::SaleConsumption,
            '400.000',
            null,
            OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM,
            $paidItem->id,
        );

        // Billing/payment/close must not invent inventory rows.
        $beforeCloseCount = InventoryTransaction::query()->count();
        $paidSession->update(['status' => DiningSessionStatus::Closed, 'closed_at' => now()]);
        $this->assertSame($beforeCloseCount, InventoryTransaction::query()->count());

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
        ]);

        $unitRow = collect($report['overview']['period_by_unit'])->firstWhere('unit', IngredientUnit::Milliliter->value);
        $this->assertSame('600.000', $unitRow['sale_consumption']);

        $productRow = collect($report['products']['rows'])->firstWhere('product', 'Latte');
        $this->assertSame(3, $productRow['units']);
        $this->assertSame(2, $productRow['paid_units']);
        $this->assertSame(1, $productRow['transaction_count']);
        $this->assertSame('300.00', $productRow['sales_amount']);
    }

    public function test_product_paid_rules_food_bar_kitchen_promos_and_price_snapshot_safety(): void
    {
        $coffeeCat = ProductCategory::factory()->create(['name' => 'Coffee']);
        $foodCat = ProductCategory::factory()->create(['name' => 'Food']);

        $latte = Product::factory()->create([
            'product_category_id' => $coffeeCat->id,
            'name' => 'Cappuccino',
            'product_type' => ProductType::Beverage,
            'preparation_station' => PreparationStation::Bar,
        ]);
        $sandwich = Product::factory()->create([
            'product_category_id' => $foodCat->id,
            'name' => 'Sandwich',
            'product_type' => ProductType::Food,
            'preparation_station' => PreparationStation::Kitchen,
        ]);
        $latteVariant = ProductVariant::factory()->create(['product_id' => $latte->id, 'name' => 'Regular', 'price' => '999.00']);
        $foodVariant = ProductVariant::factory()->create(['product_id' => $sandwich->id, 'name' => 'Classic', 'price' => '999.00']);

        $paidRetail = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
        ]);
        $this->makeOrderItem($paidRetail, $latte, $latteVariant, quantity: 1, lineSubtotal: '80.00', unitPrice: '80.00');
        $this->makeOrderItem($paidRetail, $sandwich, $foodVariant, quantity: 1, lineSubtotal: '120.00', unitPrice: '120.00');

        $promoOrder = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'discount_total' => '40.00',
        ]);
        $this->makeOrderItem($promoOrder, $latte, $latteVariant, quantity: 1, lineSubtotal: '40.00', unitPrice: '80.00');

        $freeDrink = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'discount_total' => '80.00',
        ]);
        $this->makeOrderItem($freeDrink, $latte, $latteVariant, quantity: 1, lineSubtotal: '0.00', unitPrice: '80.00');

        $unpaid = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::PendingPayment,
            'payment_confirmed_at' => null,
            'placed_at' => now(),
        ]);
        $this->makeOrderItem($unpaid, $latte, $latteVariant, quantity: 5, lineSubtotal: '400.00');

        $cancelled = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Cancelled,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'cancelled_at' => now(),
        ]);
        $this->makeOrderItem($cancelled, $latte, $latteVariant, quantity: 9, lineSubtotal: '720.00');

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
        ]);

        $latteRow = collect($report['products']['rows'])->first(
            fn (array $row): bool => $row['product'] === 'Cappuccino' && $row['station'] === PreparationStation::Bar->value,
        );
        $sandwichRow = collect($report['products']['rows'])->first(
            fn (array $row): bool => $row['product'] === 'Sandwich',
        );

        $this->assertSame(3, $latteRow['paid_units']);
        $this->assertSame(8, $latteRow['units']);
        $this->assertSame('120.00', $latteRow['sales_amount']);
        $this->assertSame(1, $sandwichRow['paid_units']);
        $this->assertSame('120.00', $sandwichRow['sales_amount']);

        $this->assertGreaterThanOrEqual(1, $report['stations']['bar_units']);
        $this->assertGreaterThanOrEqual(1, $report['stations']['kitchen_units']);
        $this->assertGreaterThanOrEqual(1, $report['categories']['food_units']);
        $this->assertGreaterThanOrEqual(1, $report['categories']['beverage_units']);

        $latteVariant->update(['price' => '1.00']);
        $reportAfterPriceChange = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
        ]);
        $latteAfter = collect($reportAfterPriceChange['products']['rows'])->first(
            fn (array $row): bool => $row['product'] === 'Cappuccino' && $row['station'] === PreparationStation::Bar->value,
        );
        $this->assertSame('120.00', $latteAfter['sales_amount']);
    }

    public function test_recipe_change_does_not_alter_historical_ledger_or_reversal_link(): void
    {
        $coffee = $this->makeIngredient('Coffee', IngredientUnit::Gram, '1000.000');
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Espresso',
            'product_type' => ProductType::Beverage,
            'preparation_station' => PreparationStation::Bar,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => '50.00']);
        $order = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Accepted,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
        ]);
        $item = $this->makeOrderItem($order, $product, $variant, quantity: 1, lineSubtotal: '50.00');

        $consumptionTxn = $this->ledger(
            $coffee,
            InventoryTransactionType::SaleConsumption,
            '8.000',
            null,
            OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM,
            $item->id,
        );
        $reversalTxn = $this->ledger(
            $coffee,
            InventoryTransactionType::SaleReversal,
            '8.000',
            null,
            OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM,
            $item->id,
        );

        OrderInventoryConsumption::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'ingredient_id' => $coffee->id,
            'recipe_id' => null,
            'recipe_line_id' => null,
            'quantity' => '8.000',
            'base_quantity' => '8.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'inventory_transaction_id' => $consumptionTxn->id,
            'reversal_inventory_transaction_id' => $reversalTxn->id,
            'reversed_at' => now(),
        ]);

        // Mutating current product/recipe assumptions must not rewrite history.
        $product->update(['name' => 'Changed Espresso']);

        $report = app(InventoryProductReportingServiceInterface::class)->buildAdminReport([
            'preset' => InventoryProductReportingService::PRESET_TODAY,
        ]);

        $unitRow = collect($report['overview']['period_by_unit'])->firstWhere('unit', IngredientUnit::Gram->value);
        $this->assertSame('8.000', $unitRow['sale_consumption']);
        $this->assertSame('8.000', $unitRow['sale_reversal']);
        $this->assertSame('0.000', $unitRow['net_movement']);

        $reversalRow = collect($report['movements'])->firstWhere('id', $reversalTxn->id);
        $this->assertSame($consumptionTxn->id, $reversalRow['reversal_of_transaction_id']);
        $this->assertSame('Espresso', $reversalRow['product']);
    }

    public function test_admin_operator_auth_and_station_roles_forbidden(): void
    {
        $manager = User::factory()->manager()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.inventory-products.index', ['preset' => 'today']))
            ->assertOk()
            ->assertSee('Inventory & Product Analytics');

        $this->actingAs($operator, 'admin')
            ->get(route('operator.reports.inventory-products.index'))
            ->assertOk()
            ->assertSee('Inventory & Product Ops')
            ->assertDontSee('Gross Paid Sales')
            ->assertDontSee('Export Product Sales CSV');

        $this->actingAs($operator, 'admin')
            ->get(route('administrator.reports.inventory-products.index'))
            ->assertForbidden();

        foreach ([
            User::factory()->barista()->create(),
            User::factory()->chef()->create(),
            User::factory()->waiter()->create(),
        ] as $user) {
            $this->actingAs($user, 'admin')
                ->get(route('administrator.reports.inventory-products.index'))
                ->assertForbidden();

            $this->actingAs($user, 'admin')
                ->get(route('operator.reports.inventory-products.index'))
                ->assertForbidden();
        }
    }

    public function test_csv_exports_match_service_results(): void
    {
        $milk = $this->makeIngredient('Milk', IngredientUnit::Milliliter, '1000.000');
        $category = ProductCategory::factory()->create(['name' => 'Drinks']);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Milkshake',
            'product_type' => ProductType::Beverage,
            'preparation_station' => PreparationStation::Bar,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Large', 'price' => '90.00']);
        $order = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_status' => PaymentStatus::Confirmed,
            'status' => OrderStatus::Completed,
            'payment_confirmed_at' => now(),
            'placed_at' => now(),
            'order_number' => 'TK-CSV-001',
        ]);
        $item = $this->makeOrderItem($order, $product, $variant, quantity: 2, lineSubtotal: '180.00');
        $this->ledger(
            $milk,
            InventoryTransactionType::SaleConsumption,
            '300.000',
            null,
            OrderInventoryConsumptionService::REFERENCE_TYPE_ORDER_ITEM,
            $item->id,
        );

        InventoryRefillRequest::factory()->create([
            'ingredient_id' => $milk->id,
            'status' => InventoryRefillRequestStatus::Pending,
        ]);

        $service = app(InventoryProductReportingServiceInterface::class);
        $report = $service->buildAdminReport(['preset' => InventoryProductReportingService::PRESET_TODAY]);
        $this->assertSame(1, $report['overview']['open_refill_requests']);

        $manager = User::factory()->manager()->create();

        $movements = $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.inventory-products.export.ingredient-movements', ['preset' => 'today']));
        $movements->assertOk();
        $movementCsv = $movements->streamedContent();
        $this->assertStringContainsString('sale_consumption', $movementCsv);
        $this->assertStringContainsString('Milk', $movementCsv);
        $this->assertStringContainsString('Milkshake', $movementCsv);
        $this->assertStringContainsString('TK-CSV-001', $movementCsv);
        $this->assertStringContainsString('300.000', $movementCsv);

        $products = $this->actingAs($manager, 'admin')
            ->get(route('administrator.reports.inventory-products.export.product-sales', ['preset' => 'today']));
        $products->assertOk();
        $productCsv = $products->streamedContent();
        $this->assertStringContainsString('Milkshake', $productCsv);
        $this->assertStringContainsString('180.00', $productCsv);
        $this->assertStringContainsString(PreparationStation::Bar->value, $productCsv);

        $productRow = collect($report['products']['rows'])->firstWhere('product', 'Milkshake');
        $this->assertSame('180.00', $productRow['sales_amount']);
        $this->assertSame(2, $productRow['paid_units']);
    }

    protected function makeIngredient(
        string $name,
        IngredientUnit $unit,
        string $stock,
        string $minimum = '1.000',
        string $reorder = '5.000',
    ): Ingredient {
        return Ingredient::factory()->create([
            'ingredient_category_id' => IngredientCategory::factory(),
            'name' => $name,
            'measurement_unit' => $unit,
            'base_measurement_unit' => $unit->baseUnit(),
            'current_stock' => $stock,
            'minimum_stock' => $minimum,
            'reorder_level' => $reorder,
            'is_active' => true,
        ]);
    }

    protected function ledger(
        Ingredient $ingredient,
        InventoryTransactionType $type,
        string $qty,
        ?CarbonImmutable $at = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): InventoryTransaction {
        $txn = InventoryTransaction::query()->create([
            'ingredient_id' => $ingredient->id,
            'transaction_type' => $type,
            'quantity' => $qty,
            'base_quantity' => $qty,
            'measurement_unit' => $ingredient->base_measurement_unit,
            'base_measurement_unit' => $ingredient->base_measurement_unit,
            'stock_before' => $ingredient->current_stock,
            'stock_after' => $ingredient->current_stock,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => null,
            'created_by' => null,
        ]);

        if ($at !== null) {
            InventoryTransaction::query()->whereKey($txn->id)->update([
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $txn->refresh();
        }

        return $txn;
    }

    protected function makeDiningSession(
        PaymentStatus $paymentStatus,
        ?CarbonImmutable $paidAt = null,
    ): DiningSession {
        $table = CafeTable::factory()->create();

        return DiningSession::query()->create([
            'session_number' => 'DS-'.now()->format('ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'cafe_table_id' => $table->id,
            'opened_by_user_id' => User::factory()->create(['role' => UserRole::Waiter])->id,
            'status' => $paymentStatus === PaymentStatus::Confirmed ? DiningSessionStatus::Closed : DiningSessionStatus::Open,
            'guest_count' => 2,
            'table_name_snapshot' => $table->snapshotLabel(),
            'opened_at' => now()->subHour(),
            'billing_requested_at' => $paymentStatus === PaymentStatus::Confirmed ? now()->subMinutes(30) : null,
            'bill_generated_at' => $paymentStatus === PaymentStatus::Confirmed ? now()->subMinutes(20) : null,
            'paid_at' => $paymentStatus === PaymentStatus::Confirmed ? ($paidAt ?? now()) : null,
            'closed_at' => $paymentStatus === PaymentStatus::Confirmed ? now() : null,
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => $paymentStatus,
            'subtotal_amount' => $paymentStatus === PaymentStatus::Confirmed ? '300.00' : '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'taxable_amount' => $paymentStatus === PaymentStatus::Confirmed ? '300.00' : '0.00',
            'tax_enabled_snapshot' => false,
            'tax_percent_snapshot' => '0.00',
            'tax_inclusive_snapshot' => false,
            'total_amount' => $paymentStatus === PaymentStatus::Confirmed ? '300.00' : '0.00',
        ]);
    }

    protected function makeDiningRound(
        DiningSession $session,
        OrderStatus $status,
        PaymentStatus $paymentStatus,
    ): Order {
        return Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'dining_session_id' => $session->id,
            'dining_round_number' => 1,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_confirmed_at' => $paymentStatus === PaymentStatus::Confirmed ? now() : null,
            'placed_at' => now(),
            'total_amount' => '0.00',
            'subtotal' => '0.00',
        ]);
    }

    protected function makeOrderItem(
        Order $order,
        Product $product,
        ProductVariant $variant,
        int $quantity,
        string $lineSubtotal,
        ?string $unitPrice = null,
    ): OrderItem {
        return OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'preparation_station' => $product->preparation_station,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'unit_price' => $unitPrice ?? $variant->price,
            'quantity' => $quantity,
            'line_subtotal' => $lineSubtotal,
        ]);
    }
}
