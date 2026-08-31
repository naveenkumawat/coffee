<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_sections_and_products(): void
    {
        $manager = User::factory()->manager()->create();
        $productA = $this->makePublicProduct('Section Latte');
        $productB = $this->makePublicProduct('Section Mocha');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.home-sections.store'), [
                'title' => 'Staff Picks',
                'subtitle' => 'Barista favourites',
                'sort_order' => 10,
                'is_active' => 1,
                'max_items' => 4,
            ])
            ->assertRedirect();

        $section = HomeSection::query()->where('slug', 'staff-picks')->firstOrFail();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.home-sections.update', $section), [
                'title' => 'Staff Picks',
                'subtitle' => 'Updated subtitle',
                'sort_order' => 10,
                'is_active' => 1,
                'max_items' => 4,
            ])
            ->assertRedirect(route('administrator.home-sections.edit', $section));

        $this->assertDatabaseHas('home_sections', [
            'id' => $section->id,
            'subtitle' => 'Updated subtitle',
        ]);

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.home-sections.products.attach', $section), [
                'product_id' => $productA->id,
            ])
            ->assertRedirect(route('administrator.home-sections.products', $section));

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.home-sections.products.attach', $section), [
                'product_id' => $productA->id,
            ])
            ->assertSessionHasErrors('product_id');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.home-sections.products.attach', $section), [
                'product_id' => $productB->id,
            ])
            ->assertRedirect();

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.home-sections.products.move-up', [$section, $productB]))
            ->assertRedirect();

        $ordered = $section->fresh()->sectionProducts()->orderBy('sort_order')->pluck('product_id')->all();
        $this->assertSame([(int) $productB->id, (int) $productA->id], array_map('intval', $ordered));

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.home-sections.products.detach', [$section, $productA]))
            ->assertRedirect();

        $this->assertDatabaseMissing('home_section_products', [
            'home_section_id' => $section->id,
            'product_id' => $productA->id,
        ]);
        $this->assertDatabaseHas('products', ['id' => $productA->id]);

        $other = HomeSection::factory()->create(['sort_order' => 20, 'title' => 'Later', 'slug' => 'later-section']);

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.home-sections.move-up', $other))
            ->assertRedirect();

        $this->assertLessThan(
            (int) $section->fresh()->sort_order,
            (int) $other->fresh()->sort_order,
        );

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.home-sections.toggle', $section))
            ->assertRedirect();

        $this->assertFalse((bool) $section->fresh()->is_active);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.home-sections.destroy', $section))
            ->assertRedirect(route('administrator.home-sections.index'));

        $this->assertSoftDeleted('home_sections', ['id' => $section->id]);
        $this->assertDatabaseHas('products', ['id' => $productB->id]);
    }

    public function test_barista_cannot_manage_home_sections(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.home-sections.index'))
            ->assertForbidden();
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '250',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '9.50',
            'is_active' => true,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        return $product->fresh();
    }
}
