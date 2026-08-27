<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_with_menu_content(): void
    {
        $category = MenuCategory::factory()->create([
            'name' => 'Signature Coffee',
        ]);

        MenuItem::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Citrus Espresso Tonic',
            'is_featured' => true,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Laravel 13 Cafe Foundation')
            ->assertSee('Signature Coffee')
            ->assertSee('Citrus Espresso Tonic');
    }
}
