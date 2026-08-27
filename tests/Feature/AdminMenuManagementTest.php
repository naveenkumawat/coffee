<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_menu_category(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('admin.menu.categories.store'), [
            'name' => 'Seasonal Specials',
            'slug' => 'seasonal-specials',
            'description' => 'Limited-time drinks and desserts.',
            'sort_order' => 5,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.menu.categories.index'));

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

        $response = $this->actingAs($barista, 'admin')->post(route('admin.menu.categories.store'), [
            'name' => 'Cold Brew',
            'slug' => 'cold-brew',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_create_menu_item(): void
    {
        $manager = User::factory()->manager()->create();
        $category = MenuCategory::factory()->create();

        $response = $this->actingAs($manager, 'admin')->post(route('admin.menu.items.store'), [
            'menu_category_id' => $category->id,
            'name' => 'Maple Cappuccino',
            'slug' => 'maple-cappuccino',
            'description' => 'Steamed milk and maple cream.',
            'price' => '5.25',
            'sort_order' => 2,
            'is_available' => 1,
            'is_featured' => 1,
        ]);

        $response->assertRedirect(route('admin.menu.items.index'));

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Maple Cappuccino',
            'slug' => 'maple-cappuccino',
        ]);
    }
}
