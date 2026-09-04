<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaiterDiningTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_manage_tables_place_round_request_bill_and_take_cash(): void
    {
        $this->enableDining();

        $waiter = User::factory()->create([
            'email' => 'waiter@coffee.local',
            'role' => UserRole::Waiter,
            'is_active' => true,
            'password' => 'password',
        ]);
        $table = CafeTable::factory()->create(['code' => 'W1', 'is_active' => true]);
        $variant = $this->makePurchasableVariant();

        $this->post(route('waiter.login.store'), [
            'email' => 'waiter@coffee.local',
            'password' => 'password',
        ])->assertRedirect(route('waiter.dashboard'));

        $this->get(route('waiter.tables.index'))->assertOk();

        $this->post(route('waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
            'guest_count' => 3,
        ])->assertRedirect();

        $session = app(DiningSessionServiceInterface::class)->findActiveForTable($table);
        $this->assertNotNull($session);

        $this->post(route('waiter.sessions.rounds.store', $session), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect();

        $session->refresh();
        $this->assertSame(1, $session->orders()->count());

        $this->post(route('waiter.sessions.request-bill', $session))->assertRedirect();
        $session->refresh();
        $this->assertTrue(in_array($session->status, [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
        ], true));

        $this->post(route('waiter.sessions.cash.receive', $session))->assertRedirect();
        $session->refresh();
        $this->assertSame(DiningSessionStatus::Closed, $session->status);

        $this->get(route('administrator.website-settings.edit'))->assertForbidden();
    }

    public function test_waiter_can_download_dining_invoice_after_bill_flow(): void
    {
        $this->enableDining();

        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);
        $table = CafeTable::factory()->create(['code' => 'W2', 'is_active' => true]);
        $variant = $this->makePurchasableVariant();

        $this->actingAs($waiter, 'admin');

        $this->post(route('waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
            'guest_count' => 2,
        ])->assertRedirect();

        $session = app(DiningSessionServiceInterface::class)->findActiveForTable($table);
        $this->assertNotNull($session);

        $this->post(route('waiter.sessions.rounds.store', $session), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('waiter.sessions.request-bill', $session))->assertRedirect();

        $this->get(route('waiter.sessions.invoice', $session))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_waiter_cannot_access_administrator_management_surfaces(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter, 'admin');

        $this->get(route('administrator.users.index'))->assertForbidden();
        $this->get(route('administrator.website-settings.edit'))->assertForbidden();
        $this->get(route('administrator.promotions.index'))->assertForbidden();
        $this->get(route('administrator.referrals.index'))->assertForbidden();
        $this->get(route('administrator.inventory.index'))->assertForbidden();
    }

    public function test_waiter_session_show_displays_station_preparation_progress(): void
    {
        $this->enableDining();

        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);
        $table = CafeTable::factory()->create(['code' => 'W3', 'is_active' => true]);
        $variant = $this->makePurchasableVariant();

        $this->actingAs($waiter, 'admin');

        $this->post(route('waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
            'guest_count' => 2,
        ])->assertRedirect();

        $session = app(DiningSessionServiceInterface::class)->findActiveForTable($table);
        $this->assertNotNull($session);

        $this->post(route('waiter.sessions.rounds.store', $session), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->get(route('waiter.sessions.show', $session))
            ->assertOk()
            ->assertSee('Bar', false)
            ->assertSee('Pending', false);
    }

    public function test_waiter_cannot_open_administrator_dashboard(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertForbidden();
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

    protected function makePurchasableVariant(): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'price' => '7.50',
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
