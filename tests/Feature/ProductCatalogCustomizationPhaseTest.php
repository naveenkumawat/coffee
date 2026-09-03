<?php

namespace Tests\Feature;

use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Models\AddOn;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AddOn\AddOnServiceInterface;
use App\Services\Cart\CartServiceInterface;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Transfers\Cart\CartItemTransfer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductCatalogCustomizationPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_assigns_active_add_ons_visible_on_public_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cappuccino = Product::query()->where('name', 'Cappuccino')->firstOrFail();
        $latte = Product::query()->where('name', 'Cafe Latte')->firstOrFail();
        $grilledCheese = Product::query()->where('name', 'Grilled Cheese')->firstOrFail();

        $this->assertTrue(AddOn::query()->where('name', 'Extra Espresso Shot')->where('is_active', true)->exists());
        $this->assertTrue(AddOn::query()->where('name', 'Vanilla Syrup')->where('is_active', true)->exists());
        $this->assertTrue(AddOn::query()->where('name', 'Extra Cheese')->where('is_active', true)->exists());
        $this->assertTrue(AddOn::query()->where('name', 'Demo Inactive Add-On')->where('is_active', false)->exists());

        $payload = collect(app(ProductCatalogServiceInterface::class)->listPublicProductPayload());

        $cappuccinoRow = $payload->firstWhere('name', 'Cappuccino');
        $this->assertIsArray($cappuccinoRow);
        $addOnNames = collect($cappuccinoRow['add_ons'] ?? [])->pluck('name')->all();
        $this->assertContains('Extra Espresso Shot', $addOnNames);
        $this->assertContains('Vanilla Syrup', $addOnNames);
        $this->assertNotContains('Demo Inactive Add-On', $addOnNames);
        $this->assertNotContains('Extra Cheese', $addOnNames);

        $latteRow = $payload->firstWhere('name', 'Cafe Latte');
        $latteNames = collect($latteRow['add_ons'] ?? [])->pluck('name')->all();
        $this->assertContains('Extra Espresso Shot', $latteNames);
        $this->assertContains('Vanilla Syrup', $latteNames);
        $this->assertContains('Hazelnut Syrup', $latteNames);

        $foodRow = $payload->firstWhere('name', 'Grilled Cheese');
        $this->assertContains(
            'Extra Cheese',
            collect($foodRow['add_ons'] ?? [])->pluck('name')->all(),
        );

        $this->getJson('/api/v1/catalog/products/'.$cappuccino->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Extra Espresso Shot'])
            ->assertJsonFragment(['name' => 'Vanilla Syrup'])
            ->assertJsonMissing(['name' => 'Demo Inactive Add-On']);

        $this->assertSame(
            PreparationStation::Kitchen,
            $grilledCheese->preparation_station,
        );
    }

    public function test_catalog_hides_inactive_and_unassigned_add_ons(): void
    {
        $variant = $this->makePurchasableVariant('80.00');
        $activeAssigned = AddOn::factory()->create([
            'name' => 'Visible Shot',
            'default_price' => '15.00',
            'is_active' => true,
        ]);
        $inactiveAssigned = AddOn::factory()->inactive()->create([
            'name' => 'Hidden Shot',
            'default_price' => '15.00',
        ]);
        $unassigned = AddOn::factory()->create([
            'name' => 'Orphan Shot',
            'default_price' => '15.00',
            'is_active' => true,
        ]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [
            ['add_on_id' => $activeAssigned->id, 'max_quantity' => 2, 'sort_order' => 10],
            ['add_on_id' => $inactiveAssigned->id, 'max_quantity' => 1, 'sort_order' => 20],
        ]);

        $catalog = app(AddOnServiceInterface::class)->catalogAddOnsForProduct($variant->product->fresh());
        $names = collect($catalog)->pluck('name')->all();

        $this->assertSame(['Visible Shot'], $names);
        $this->assertNotContains('Orphan Shot', $names);
        $this->assertSame(2, $catalog[0]['max_quantity']);

        $this->getJson('/api/v1/catalog/products/'.$variant->product_id)
            ->assertOk()
            ->assertJsonPath('data.add_ons.0.name', 'Visible Shot')
            ->assertJsonPath('data.add_ons.0.price', '15.00')
            ->assertJsonMissing(['name' => 'Hidden Shot'])
            ->assertJsonMissing(['name' => 'Orphan Shot']);

        unset($unassigned);
    }

    public function test_selected_add_ons_change_authoritative_cart_total(): void
    {
        $carts = app(CartServiceInterface::class);
        $variant = $this->makePurchasableVariant('100.00');
        $addOn = AddOn::factory()->create(['default_price' => '25.00', 'is_active' => true]);

        app(AddOnServiceInterface::class)->syncProductAssignments($variant->product, [[
            'add_on_id' => $addOn->id,
            'max_quantity' => 2,
        ]]);

        $plainCustomer = User::factory()->customer()->create();
        $plain = new CartItemTransfer;
        $plain->setProductVariantId($variant->id);
        $plain->setQuantity(1);
        $plain->setAddOns([]);
        $plainSummary = $carts->summarize($carts->addItem($plainCustomer, $plain));

        $configuredCustomer = User::factory()->customer()->create();
        $configured = new CartItemTransfer;
        $configured->setProductVariantId($variant->id);
        $configured->setQuantity(1);
        $configured->setAddOns([['add_on_id' => $addOn->id, 'quantity' => 1]]);
        $configuredSummary = $carts->summarize($carts->addItem($configuredCustomer, $configured));

        $this->assertSame('100.00', $plainSummary['subtotal']);
        $this->assertSame('125.00', $configuredSummary['subtotal']);
    }

    public function test_pwa_product_order_control_always_opens_shared_customization(): void
    {
        $orderControl = File::get(base_path('customer-pwa/src/components/catalog/ProductOrderControl.tsx'));
        $productActions = File::get(base_path('customer-pwa/src/utils/productActions.ts'));
        $overlay = File::get(base_path('customer-pwa/src/hooks/useProductOverlay.ts'));
        $scrollLock = File::get(base_path('customer-pwa/src/utils/overlayScrollLock.ts'));
        $sheet = File::get(base_path('customer-pwa/src/components/catalog/ProductCustomizationSheet.tsx'));

        $this->assertStringContainsString('bi-bag-plus', $orderControl);
        $this->assertStringNotContainsString('bi-sliders', $orderControl);
        $this->assertStringContainsString('Customize and add', $orderControl);
        $this->assertStringContainsString('ProductCustomizationSheet', $orderControl);
        $this->assertStringNotContainsString('addItem(', $orderControl);
        $this->assertStringContainsString('canQuickAddProduct(_product: Product): boolean', $productActions);
        $this->assertStringContainsString('return false', $productActions);

        $this->assertStringContainsString('useLayoutEffect', $overlay);
        $this->assertStringContainsString('lockOverlayBackgroundScroll', $overlay);
        $this->assertStringContainsString('unlockOverlayBackgroundScroll', $overlay);
        $this->assertStringContainsString('preventScroll: true', $overlay);
        $this->assertStringNotContainsString('scrollRestoration', $overlay);
        $this->assertStringNotContainsString('requestAnimationFrame', $overlay);

        $this->assertStringContainsString('lockDepth', $scrollLock);
        $this->assertStringContainsString("body.position = 'fixed'", $scrollLock);
        $this->assertStringContainsString("body.left = '0'", $scrollLock);
        $this->assertStringContainsString("body.right = '0'", $scrollLock);
        $this->assertStringContainsString('window.scrollTo(0, y)', $scrollLock);
        $this->assertStringNotContainsString('scrollRestoration', $scrollLock);

        $this->assertStringContainsString('Customize / Add-ons', $sheet);
        $this->assertStringContainsString('product-addon-name', $sheet);
        $this->assertStringContainsString('product-addon-desc', $sheet);
        $this->assertStringContainsString('getPreferredVariant', $sheet);
        $this->assertStringNotContainsString('<strong>{addOn.name}</strong>', $sheet);
        $this->assertStringNotContainsString('{addOn.description ? <small>', $sheet);
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
