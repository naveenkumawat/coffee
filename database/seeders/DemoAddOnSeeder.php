<?php

namespace Database\Seeders;

use App\Enums\IngredientUnit;
use App\Models\AddOn;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\AddOn\AddOnServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo C1 add-ons + product assignments for local/testing catalogues.
 *
 * Idempotent by slug — never duplicates Extra Shot / syrups on re-seed.
 * Preparation station still inherits the parent order item (no add-on station column).
 */
class DemoAddOnSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $addOns = app(AddOnServiceInterface::class);

        $espresso = Ingredient::query()->where('name', 'Davidoff Espresso')->firstOrFail();
        $vanilla = Ingredient::query()->where('name', 'Vanilla Syrup')->firstOrFail();
        $hazelnut = Ingredient::query()->where('name', 'Hazelnut Syrup')->firstOrFail();

        $extraShot = $this->upsertAddOn($addOns, [
            'name' => 'Extra Espresso Shot',
            'description' => 'One additional espresso shot.',
            'default_price' => '25.00',
            'sort_order' => 10,
            'lines' => [[
                'ingredient_id' => $espresso->id,
                'quantity' => '9.000',
                'measurement_unit' => IngredientUnit::Gram->value,
            ]],
        ]);

        $vanillaSyrup = $this->upsertAddOn($addOns, [
            'name' => 'Vanilla Syrup',
            'description' => 'Sweet vanilla syrup pump.',
            'default_price' => '20.00',
            'sort_order' => 20,
            'lines' => [[
                'ingredient_id' => $vanilla->id,
                'quantity' => '0.020',
                'measurement_unit' => IngredientUnit::Bottle->value,
            ]],
        ]);

        $hazelnutSyrup = $this->upsertAddOn($addOns, [
            'name' => 'Hazelnut Syrup',
            'description' => 'Nutty hazelnut syrup pump.',
            'default_price' => '20.00',
            'sort_order' => 30,
            'lines' => [[
                'ingredient_id' => $hazelnut->id,
                'quantity' => '0.020',
                'measurement_unit' => IngredientUnit::Bottle->value,
            ]],
        ]);

        $extraCheese = $this->upsertAddOn($addOns, [
            'name' => 'Extra Cheese',
            'description' => 'Additional melted cheese.',
            'default_price' => '30.00',
            'sort_order' => 40,
            'lines' => [],
        ]);

        $inactiveDemo = $this->upsertAddOn($addOns, [
            'name' => 'Demo Inactive Add-On',
            'description' => 'Seeded inactive — must not appear in public catalog.',
            'default_price' => '5.00',
            'sort_order' => 99,
            'is_active' => false,
            'lines' => [],
        ]);

        $this->assign($addOns, 'Cappuccino', [
            ['add_on_id' => $extraShot->id, 'max_quantity' => 2, 'sort_order' => 10],
            ['add_on_id' => $vanillaSyrup->id, 'max_quantity' => 1, 'sort_order' => 20],
            // Assigned but inactive — catalog must hide it.
            ['add_on_id' => $inactiveDemo->id, 'max_quantity' => 1, 'sort_order' => 90],
        ]);

        $this->assign($addOns, 'Cafe Latte', [
            ['add_on_id' => $extraShot->id, 'max_quantity' => 2, 'sort_order' => 10],
            ['add_on_id' => $vanillaSyrup->id, 'max_quantity' => 1, 'sort_order' => 20],
            ['add_on_id' => $hazelnutSyrup->id, 'max_quantity' => 1, 'sort_order' => 30],
        ]);

        $this->assign($addOns, 'Espresso', [
            ['add_on_id' => $extraShot->id, 'max_quantity' => 2, 'sort_order' => 10],
        ]);

        foreach (['Club Sandwich', 'Grilled Cheese', 'Creamy Penne'] as $foodName) {
            $this->assign($addOns, $foodName, [
                ['add_on_id' => $extraCheese->id, 'max_quantity' => 1, 'sort_order' => 10],
            ]);
        }
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: ?string,
     *     default_price: string,
     *     sort_order?: int,
     *     is_active?: bool,
     *     lines?: list<array<string, mixed>>,
     * }  $data
     */
    protected function upsertAddOn(AddOnServiceInterface $addOns, array $data): AddOn
    {
        $slug = Str::slug($data['name']);
        $existing = AddOn::withTrashed()->where('slug', $slug)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $addOns->update($existing, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'default_price' => $data['default_price'],
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 10,
                'lines' => $data['lines'] ?? [],
            ]);
        }

        return $addOns->store([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_price' => $data['default_price'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 10,
            'lines' => $data['lines'] ?? [],
        ]);
    }

    /**
     * @param  list<array{add_on_id: int, max_quantity?: int, sort_order?: int, price_override?: ?string}>  $assignments
     */
    protected function assign(AddOnServiceInterface $addOns, string $productName, array $assignments): void
    {
        $product = Product::query()->where('name', $productName)->first();

        if ($product === null) {
            return;
        }

        $addOns->syncProductAssignments($product, $assignments);
    }
}
