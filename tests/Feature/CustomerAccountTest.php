<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Notifications\CustomerResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_login_and_logout_without_affecting_internal_auth_rules(): void
    {
        $this->post(route('customer.register.store'), [
            'name' => 'Nina Customer',
            'email' => 'nina@example.test',
            'phone' => '9999999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('customer.account.show'));

        $customer = User::query()->where('email', 'nina@example.test')->firstOrFail();

        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertTrue(Hash::check('password123', $customer->password));
        $this->assertAuthenticatedAs($customer, 'web');

        auth('web')->logout();

        $this->post(route('customer.login.store'), [
            'email' => 'nina@example.test',
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.show'));

        $this->assertAuthenticated('web');

        $this->post(route('customer.logout'))->assertRedirect(route('home'));
        $this->assertGuest('web');
    }

    public function test_internal_role_cannot_use_customer_login_flow_and_customer_cannot_access_internal_panels(): void
    {
        $manager = User::factory()->manager()->create([
            'email' => 'manager@example.test',
            'password' => Hash::make('secret123'),
        ]);
        $customer = User::factory()->customer()->create();

        $this->from(route('customer.login'))
            ->post(route('customer.login.store'), [
                'email' => $manager->email,
                'password' => 'secret123',
            ])
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors('email');

        $this->actingAs($customer, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertForbidden();

        $this->actingAs($customer, 'admin')
            ->get(route('barista.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_can_update_profile_and_change_password_securely(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $this->actingAs($customer, 'web')
            ->put(route('customer.account.profile.update'), [
                'name' => 'Updated Customer',
                'email' => 'updated@example.test',
                'phone' => '8888888888',
            ])
            ->assertRedirect(route('customer.account.show'));

        $customer->refresh();

        $this->assertSame('Updated Customer', $customer->name);
        $this->assertSame('updated@example.test', $customer->email);
        $this->assertSame('8888888888', $customer->phone);

        $this->actingAs($customer, 'web')
            ->put(route('customer.account.password.update'), [
                'current_password' => 'oldpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect(route('customer.account.show'));

        $customer->refresh();

        $this->assertTrue(Hash::check('newpassword123', $customer->password));
        $this->assertFalse(Hash::check('oldpassword', $customer->password));
    }

    public function test_customer_can_request_and_reset_password_through_customer_flow(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create([
            'email' => 'reset@example.test',
            'password' => Hash::make('oldpassword'),
        ]);

        $this->post(route('customer.password.email'), [
            'email' => $customer->email,
        ])->assertSessionHas('status');

        Notification::assertSentTo($customer, CustomerResetPasswordNotification::class);

        $token = Password::broker('users')->createToken($customer);

        $this->post(route('customer.password.store'), [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'brandnew123',
            'password_confirmation' => 'brandnew123',
        ])->assertRedirect(route('customer.account.show'));

        $customer->refresh();

        $this->assertTrue(Hash::check('brandnew123', $customer->password));
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_guest_restrictions_apply_to_customer_account_and_order_pages(): void
    {
        $this->get(route('customer.account.show'))->assertRedirect(route('customer.login'));
        $this->get(route('customer.orders.index'))->assertRedirect(route('customer.login'));
    }

    public function test_customer_sees_only_own_orders_and_customer_safe_order_detail(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Primary Customer']);
        $otherCustomer = User::factory()->customer()->create(['name' => 'Other Customer']);
        $ownedOrder = $this->createCustomerOrder($customer, 'Iced Vanilla Latte');
        $otherOrder = $this->createCustomerOrder($otherCustomer, 'Hazelnut Mocha');

        $ownedOrder->statusHistory()->create([
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::PaymentConfirmed->value,
            'changed_by' => User::factory()->manager()->create()->id,
            'notes' => 'Internal payment note should stay private.',
        ]);
        $ownedOrder->update([
            'status' => OrderStatus::PaymentConfirmed->value,
            'payment_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'web')
            ->get(route('customer.orders.index'));

        $response
            ->assertOk()
            ->assertSee(route('customer.orders.show', $ownedOrder), false)
            ->assertDontSee(route('customer.orders.show', $otherOrder), false);

        $this->actingAs($customer, 'web')
            ->get(route('customer.orders.show', $ownedOrder))
            ->assertOk()
            ->assertSee($ownedOrder->order_number)
            ->assertSee('Iced Vanilla Latte')
            ->assertSee('Payment Confirmed')
            ->assertDontSee('Internal payment note should stay private.')
            ->assertDontSee('Steam and pour.')
            ->assertDontSee('Espresso Beans')
            ->assertDontSee('assigned_barista_id')
            ->assertDontSee('changedBy');

        $this->actingAs($customer, 'web')
            ->get(route('customer.orders.show', $otherOrder))
            ->assertForbidden();
    }

    protected function createCustomerOrder(User $customer, string $productName): Order
    {
        $variant = $this->makeVariantWithRecipe($productName, 'Regular', '89.00');
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'subtotal' => '178.00',
            'discount_total' => '0.00',
            'total_amount' => '178.00',
            'customer_notes' => 'Ring the bell on arrival.',
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'recipe_id' => $variant->recipe?->id,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'customer_ingredient_summary' => $variant->product->customer_ingredient_summary,
            'unit_price' => '89.00',
            'quantity' => 2,
            'line_subtotal' => '178.00',
        ]);

        $order->statusHistory()->create([
            'from_status' => null,
            'to_status' => OrderStatus::PendingPayment->value,
            'changed_by' => null,
            'notes' => 'Internal creation note.',
        ]);

        return $order->fresh(['items', 'statusHistory']);
    }

    protected function makeVariantWithRecipe(string $productName, string $variantName, string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $productName,
            'customer_ingredient_summary' => 'Espresso, milk, syrup',
            'is_active' => true,
            'is_available' => true,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => $variantName,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);

        $ingredient = Ingredient::factory()->create([
            'ingredient_category_id' => IngredientCategory::factory()->create()->id,
            'name' => 'Espresso Beans '.fake()->unique()->word(),
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'cost_per_unit' => '0.8000',
        ]);

        $recipe = Recipe::factory()->create([
            'product_variant_id' => $variant->id,
            'preparation_notes' => 'Steam and pour.',
        ]);

        $recipe->lines()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => '18.000',
            'measurement_unit' => IngredientUnit::Gram->value,
            'base_quantity' => '18.000',
            'base_measurement_unit' => IngredientUnit::Gram->value,
            'sort_order' => 1,
        ]);

        return $variant->fresh(['product', 'recipe.lines.ingredient']);
    }
}
