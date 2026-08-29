<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Parsers\Menu\MenuCategoryParser;
use App\Parsers\Menu\MenuCategoryParserInterface;
use App\Parsers\Menu\MenuItemParser;
use App\Parsers\Menu\MenuItemParserInterface;
use App\Repositories\Menu\MenuCategoryRepository;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Repositories\Menu\MenuItemRepository;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use App\Services\Auth\RoleService;
use App\Services\Auth\RoleServiceInterface;
use App\Services\Menu\MenuCatalogService;
use App\Services\Menu\MenuCatalogServiceInterface;
use App\Services\Menu\MenuCategoryService;
use App\Services\Menu\MenuCategoryServiceInterface;
use App\Services\Menu\MenuItemService;
use App\Services\Menu\MenuItemServiceInterface;
use App\Transfers\Menu\MenuCategoryTransfer;
use App\Transfers\Menu\MenuCategoryTransferInterface;
use App\Transfers\Menu\MenuItemTransfer;
use App\Transfers\Menu\MenuItemTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdministratorMenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_menu_category(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.menu.categories.store'), [
            'name' => 'Seasonal Specials',
            'slug' => 'seasonal-specials',
            'description' => 'Limited-time drinks and desserts.',
            'sort_order' => 5,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('administrator.menu.categories.index'));

        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Seasonal Specials',
            'slug' => 'seasonal-specials',
        ]);
    }

    public function test_barista_cannot_manage_menu_catalog(): void
    {
        $barista = User::factory()->create([
            'role' => UserRole::Barista,
        ]);

        $response = $this->actingAs($barista, 'admin')->post(route('administrator.menu.categories.store'), [
            'name' => 'Cold Brew',
            'slug' => 'cold-brew',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_create_menu_item(): void
    {
        $manager = User::factory()->manager()->create();
        $category = MenuCategory::factory()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('administrator.menu.items.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Maple Cappuccino',
            'slug' => 'maple-cappuccino',
            'description' => 'Steamed milk and maple cream.',
            'price' => '5.25',
            'sort_order' => 2,
            'is_available' => 1,
            'is_featured' => 1,
        ]);

        $response->assertRedirect(route('administrator.menu.items.index'));

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Maple Cappuccino',
            'slug' => 'maple-cappuccino',
        ]);
    }

    public function test_menu_architecture_contracts_are_bound(): void
    {
        $this->assertInstanceOf(MenuCategoryRepository::class, $this->app->make(MenuCategoryRepositoryInterface::class));
        $this->assertInstanceOf(MenuItemRepository::class, $this->app->make(MenuItemRepositoryInterface::class));
        $this->assertInstanceOf(MenuCatalogService::class, $this->app->make(MenuCatalogServiceInterface::class));
        $this->assertInstanceOf(MenuCategoryService::class, $this->app->make(MenuCategoryServiceInterface::class));
        $this->assertInstanceOf(MenuItemService::class, $this->app->make(MenuItemServiceInterface::class));
        $this->assertInstanceOf(RoleService::class, $this->app->make(RoleServiceInterface::class));
        $this->assertInstanceOf(MenuCategoryTransfer::class, $this->app->make(MenuCategoryTransferInterface::class));
        $this->assertInstanceOf(MenuItemTransfer::class, $this->app->make(MenuItemTransferInterface::class));
        $this->assertInstanceOf(MenuCategoryParser::class, $this->app->make(MenuCategoryParserInterface::class));
        $this->assertInstanceOf(MenuItemParser::class, $this->app->make(MenuItemParserInterface::class));
    }

    public function test_manager_can_delete_menu_item_and_flush_catalog_cache(): void
    {
        $manager = User::factory()->manager()->create();
        $category = MenuCategory::factory()->create();
        $menuItem = MenuItem::factory()->create([
            'menu_category_id' => $category->id,
        ]);

        Cache::put(MenuCatalogService::PUBLIC_MENU_CACHE_KEY, ['cached'], now()->addMinutes(10));
        Cache::put(MenuCatalogService::FEATURED_MENU_CACHE_KEY, ['cached'], now()->addMinutes(10));

        $response = $this->actingAs($manager, 'admin')->delete(route('administrator.menu.items.destroy', $menuItem));

        $response->assertRedirect(route('administrator.menu.items.index'));
        $this->assertDatabaseMissing('menu_items', [
            'id' => $menuItem->id,
        ]);
        $this->assertFalse(Cache::has(MenuCatalogService::PUBLIC_MENU_CACHE_KEY));
        $this->assertFalse(Cache::has(MenuCatalogService::FEATURED_MENU_CACHE_KEY));
    }

    public function test_menu_category_index_uses_shared_action_dropdown(): void
    {
        $manager = User::factory()->manager()->create();
        $category = MenuCategory::factory()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.menu.categories.index'))
            ->assertOk()
            ->assertSee('internal-button-group', false)
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee(route('administrator.menu.categories.edit', $category), false);
    }

    public function test_menu_item_index_uses_shared_action_dropdown(): void
    {
        $manager = User::factory()->manager()->create();
        $category = MenuCategory::factory()->create();
        $menuItem = MenuItem::factory()->create([
            'menu_category_id' => $category->id,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.menu.items.index'))
            ->assertOk()
            ->assertSee('internal-button-group', false)
            ->assertSee('internal-action-dropdown-trigger', false)
            ->assertSee(route('administrator.menu.items.edit', $menuItem), false);
    }
}
