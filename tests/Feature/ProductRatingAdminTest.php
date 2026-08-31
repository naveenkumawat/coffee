<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRating;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRatingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_view_hide_and_delete_ratings(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create(['name' => 'Review Customer']);
        $product = $this->makePublicProduct('Admin Review Latte');
        $order = $this->makeCompletedOrder($customer, $product);

        $rating = ProductRating::factory()->forPurchase($customer, $product, $order)->create([
            'rating' => 2,
            'review' => 'Too bitter for my taste.',
            'is_public' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.products.ratings.index'))
            ->assertOk()
            ->assertSee('Admin Review Latte')
            ->assertSee('Too bitter');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.products.ratings.show', $rating))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Too bitter for my taste.');

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.products.ratings.hide', $rating))
            ->assertRedirect(route('administrator.products.ratings.show', $rating));

        $this->assertDatabaseHas('product_ratings', [
            'id' => $rating->id,
            'is_public' => false,
            'moderated_by' => $manager->id,
        ]);

        $this->assertSame(1, ProductRating::query()->where('product_id', $product->id)->count());
        $this->assertSame(2.0, round((float) ProductRating::query()->where('product_id', $product->id)->avg('rating'), 1));

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.products.ratings.destroy', $rating))
            ->assertRedirect(route('administrator.products.ratings.index'));

        $this->assertDatabaseMissing('product_ratings', [
            'id' => $rating->id,
        ]);
    }

    public function test_barista_cannot_moderate_ratings(): void
    {
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Barista Blocked');
        $order = $this->makeCompletedOrder($customer, $product);
        $rating = ProductRating::factory()->forPurchase($customer, $product, $order)->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.products.ratings.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->patch(route('administrator.products.ratings.hide', $rating))
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

        return $product->fresh(['defaultVariant']);
    }

    protected function makeCompletedOrder(User $customer, Product $product): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'completed_at' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant_name' => 'Regular',
            'unit_price' => '9.50',
            'quantity' => 1,
            'line_subtotal' => '9.50',
        ]);

        return $order;
    }
}
