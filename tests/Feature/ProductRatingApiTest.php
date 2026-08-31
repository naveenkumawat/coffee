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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductRatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_submit_rating(): void
    {
        $product = $this->makePublicProduct('Guest Latte');

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 5,
        ])->assertUnauthorized();
    }

    public function test_customer_cannot_rate_unpurchased_product(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Unbought Brew');

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 4,
            'review' => 'Looks good',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product']);
    }

    public function test_customer_cannot_rate_non_completed_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Pipeline Pour');

        foreach ([
            OrderStatus::PendingPayment,
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::ReadyForPickup,
        ] as $status) {
            $this->makeOrderWithItem($customer, $product, $status);

            $this->actingAs($customer, 'web')
                ->postJson(route('api.v1.products.rating.store', $product), [
                    'rating' => 5,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['product']);
        }
    }

    public function test_customer_can_create_update_and_delete_verified_rating(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Naveen Kumawat']);
        $other = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Verified Latte');
        $order = $this->makeOrderWithItem($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 5,
            'review' => 'Excellent foam.',
        ])->assertCreated()
            ->assertJsonPath('data.my_rating.rating', 5)
            ->assertJsonPath('data.rating_summary.count', 1)
            ->assertJsonPath('data.rating_summary.average', 5);

        $this->assertDatabaseHas('product_ratings', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'qualifying_order_id' => $order->id,
            'rating' => 5,
        ]);

        $this->putJson(route('api.v1.products.rating.update', $product), [
            'rating' => 4,
            'review' => 'Still great, a little cooler today.',
        ])->assertOk()
            ->assertJsonPath('data.my_rating.rating', 4)
            ->assertJsonPath('data.rating_summary.average', 4);

        $this->assertDatabaseCount('product_ratings', 1);

        Sanctum::actingAs($other);

        $this->putJson(route('api.v1.products.rating.update', $product), [
            'rating' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product']);

        Sanctum::actingAs($customer);

        $this->deleteJson(route('api.v1.products.rating.destroy', $product))
            ->assertOk()
            ->assertJsonPath('data.my_rating', null)
            ->assertJsonPath('data.rating_summary.count', 0);

        $this->assertSoftDeleted('product_ratings', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_rating_validation_and_public_review_privacy(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Naveen Kumawat']);
        $product = $this->makePublicProduct('Privacy Pour');
        $this->makeOrderWithItem($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 6,
        ])->assertStatus(422)->assertJsonValidationErrors(['rating']);

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 5,
            'review' => str_repeat('a', 1001),
        ])->assertStatus(422)->assertJsonValidationErrors(['review']);

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 5,
            'review' => 'Balanced and smooth.',
        ])->assertCreated();

        $this->getJson(route('api.v1.catalog.products.ratings.index', $product))
            ->assertOk()
            ->assertJsonPath('data.rating_summary.count', 1)
            ->assertJsonPath('data.rating_summary.average', 5)
            ->assertJsonPath('data.reviews.0.customer_display_name', 'Naveen K.')
            ->assertJsonMissingPath('data.reviews.0.email')
            ->assertJsonMissingPath('data.reviews.0.customer_id')
            ->assertJsonPath('data.my_rating.rating', 5)
            ->assertJsonPath('data.can_rate', true);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->getJson(route('api.v1.catalog.products.ratings.index', $product))
            ->assertOk()
            ->assertJsonPath('data.my_rating', null)
            ->assertJsonPath('data.can_rate', false)
            ->assertJsonPath('data.reviews.0.customer_display_name', 'Naveen K.');
    }

    public function test_catalog_includes_rating_summary_and_hidden_review_policy(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Priya Sharma']);
        $manager = User::factory()->manager()->create();
        $product = $this->makePublicProduct('Catalog Mocha');
        $this->makeOrderWithItem($customer, $product, OrderStatus::Completed);

        $secondCustomer = User::factory()->customer()->create();
        $this->makeOrderWithItem($secondCustomer, $product, OrderStatus::Completed);

        $rating = ProductRating::factory()->forPurchase($customer, $product)->create([
            'rating' => 4,
            'review' => 'Chocolate notes shine.',
            'is_public' => true,
        ]);

        ProductRating::factory()->forPurchase($secondCustomer, $product)->withoutReview()->create([
            'rating' => 5,
        ]);

        $this->getJson(route('api.v1.catalog.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.rating_summary.average', 4.5)
            ->assertJsonPath('data.rating_summary.count', 2);

        $this->getJson(route('api.v1.catalog.products.index', [
            'sort' => 'rating_high_to_low',
        ]))->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.rating_summary.count', 2);

        $rating->fill([
            'is_public' => false,
            'moderated_at' => now(),
            'moderated_by' => $manager->id,
        ])->save();

        $this->getJson(route('api.v1.catalog.products.ratings.index', $product))
            ->assertOk()
            ->assertJsonPath('data.rating_summary.count', 2)
            ->assertJsonCount(0, 'data.reviews');
    }

    public function test_second_post_updates_existing_rating_instead_of_duplicating(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Upsert Pour');
        $this->makeOrderWithItem($customer, $product, OrderStatus::Completed);

        $this->actingAs($customer, 'web');

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 3,
        ])->assertCreated();

        $this->postJson(route('api.v1.products.rating.store', $product), [
            'rating' => 5,
            'review' => 'Even better on round two.',
        ])->assertCreated()
            ->assertJsonPath('data.my_rating.rating', 5);

        $this->assertDatabaseCount('product_ratings', 1);
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create([
            'is_active' => true,
        ]);

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

        return $product->fresh(['category', 'defaultVariant', 'variants']);
    }

    protected function makeOrderWithItem(User $customer, Product $product, OrderStatus $status): Order
    {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'status' => $status,
            'payment_status' => $status === OrderStatus::Completed
                ? PaymentStatus::Confirmed
                : PaymentStatus::Pending,
            'completed_at' => $status === OrderStatus::Completed ? now() : null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->defaultVariant?->id,
            'product_name' => $product->name,
            'variant_name' => 'Regular',
            'unit_price' => '9.50',
            'quantity' => 1,
            'line_subtotal' => '9.50',
        ]);

        return $order;
    }
}
