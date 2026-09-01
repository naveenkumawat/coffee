<?php

namespace Tests\Feature;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\DemoPromotionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_automatic_offer_and_toggle_status(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.promotions.store'), [
                'name' => 'Happy Hour 10%',
                'type' => PromotionType::Automatic->value,
                'discount_type' => PromotionDiscountType::Percentage->value,
                'discount_value' => 10,
                'fulfilment_scope' => PromotionFulfilmentScope::DineIn->value,
                'priority' => 15,
                'is_active' => 1,
                'stackable' => 0,
                'applies_to_all_products' => 1,
                'applies_to_all_customers' => 1,
                'first_order_only' => 0,
                'customer_message' => 'Happy hour discount applied.',
            ])
            ->assertRedirect();

        $promotion = Promotion::query()->where('name', 'Happy Hour 10%')->first();
        $this->assertNotNull($promotion);
        $this->assertNull($promotion->code);
        $this->assertTrue($promotion->is_active);
        $this->assertSame(PromotionType::Automatic, $promotion->type);
        $this->assertSame(PromotionFulfilmentScope::DineIn, $promotion->fulfilment_scope);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.promotions.index'))
            ->assertOk()
            ->assertSee('Happy Hour 10%');

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.promotions.toggle', $promotion))
            ->assertRedirect(route('administrator.promotions.index'));

        $this->assertFalse($promotion->fresh()->is_active);
    }

    public function test_coupon_code_is_normalized_and_required(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.promotions.store'), [
                'name' => 'Missing Code',
                'type' => PromotionType::Coupon->value,
                'discount_type' => PromotionDiscountType::Fixed->value,
                'discount_value' => 50,
                'fulfilment_scope' => PromotionFulfilmentScope::Any->value,
                'applies_to_all_products' => 1,
                'applies_to_all_customers' => 1,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.promotions.store'), [
                'name' => 'Bulk Off',
                'code' => 'bulk500',
                'type' => PromotionType::Coupon->value,
                'discount_type' => PromotionDiscountType::Fixed->value,
                'discount_value' => 100,
                'minimum_subtotal' => 500,
                'fulfilment_scope' => PromotionFulfilmentScope::Any->value,
                'priority' => 5,
                'is_active' => 1,
                'stackable' => 0,
                'applies_to_all_products' => 1,
                'applies_to_all_customers' => 1,
                'first_order_only' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('promotions', [
            'name' => 'Bulk Off',
            'code' => 'BULK500',
            'type' => PromotionType::Coupon->value,
        ]);
    }

    public function test_scoped_products_detach_when_applies_to_all_products(): void
    {
        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['product_category_id' => $category->getKey()]);

        $promotion = Promotion::factory()->automatic()->create([
            'applies_to_all_products' => false,
        ]);
        $promotion->products()->sync([$product->getKey()]);
        $promotion->productCategories()->sync([$category->getKey()]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.promotions.update', $promotion), [
                'name' => $promotion->name,
                'type' => PromotionType::Automatic->value,
                'discount_type' => PromotionDiscountType::Percentage->value,
                'discount_value' => 10,
                'fulfilment_scope' => PromotionFulfilmentScope::Any->value,
                'priority' => 0,
                'is_active' => 1,
                'stackable' => 0,
                'applies_to_all_products' => 1,
                'applies_to_all_customers' => 1,
                'first_order_only' => 0,
                'product_ids' => [$product->getKey()],
                'product_category_ids' => [$category->getKey()],
            ])
            ->assertRedirect(route('administrator.promotions.edit', $promotion));

        $this->assertTrue($promotion->fresh()->applies_to_all_products);
        $this->assertCount(0, $promotion->products()->get());
        $this->assertCount(0, $promotion->productCategories()->get());
    }

    public function test_barista_cannot_manage_promotions(): void
    {
        $barista = User::factory()->barista()->create();
        $promotion = Promotion::factory()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.promotions.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.promotions.store'), [
                'name' => 'Nope',
                'type' => PromotionType::Automatic->value,
                'discount_type' => PromotionDiscountType::Percentage->value,
                'discount_value' => 5,
                'fulfilment_scope' => PromotionFulfilmentScope::Any->value,
                'applies_to_all_products' => 1,
                'applies_to_all_customers' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->delete(route('administrator.promotions.destroy', $promotion))
            ->assertForbidden();
    }

    public function test_delete_soft_deletes_and_deactivates(): void
    {
        $manager = User::factory()->manager()->create();
        $promotion = Promotion::factory()->create(['is_active' => true]);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.promotions.destroy', $promotion))
            ->assertRedirect(route('administrator.promotions.index'));

        $this->assertSoftDeleted($promotion);
        $this->assertFalse(Promotion::withTrashed()->findOrFail($promotion->getKey())->is_active);
    }

    public function test_demo_promotion_seeder_creates_expected_offers(): void
    {
        $this->seed(DemoPromotionSeeder::class);

        $this->assertDatabaseHas('promotions', [
            'name' => 'Dine-in 10%',
            'type' => PromotionType::Automatic->value,
            'fulfilment_scope' => PromotionFulfilmentScope::DineIn->value,
            'discount_value' => 10,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('promotions', [
            'code' => 'BULK500',
            'type' => PromotionType::Coupon->value,
            'discount_type' => PromotionDiscountType::Fixed->value,
            'minimum_subtotal' => 500,
        ]);
        $this->assertDatabaseHas('promotions', [
            'code' => 'DIWALI15',
            'maximum_discount_amount' => 150,
        ]);
        $this->assertDatabaseHas('promotions', [
            'code' => 'WELCOME20',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('promotions', [
            'name' => 'Expired Summer Special',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('promotions', [
            'name' => 'Festival Coffee Offer',
            'discount_value' => 5,
        ]);
    }
}
