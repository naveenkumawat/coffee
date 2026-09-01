<?php

namespace Database\Factories;

use App\Enums\PreparationStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->for(ProductCategory::factory(), 'category'),
            'product_variant_id' => ProductVariant::factory(),
            'recipe_id' => Recipe::factory(),
            'preparation_station' => PreparationStation::Bar,
            'product_name' => 'Cafe Latte',
            'variant_name' => 'Regular',
            'customer_ingredient_summary' => 'Espresso, milk',
            'unit_price' => '120.00',
            'quantity' => 1,
            'line_subtotal' => '120.00',
        ];
    }
}
