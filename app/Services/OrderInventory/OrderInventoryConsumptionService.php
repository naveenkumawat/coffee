<?php

namespace App\Services\OrderInventory;

use App\Enums\InventoryTransactionType;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\AddOn\AddOnServiceInterface;
use App\Services\Inventory\InventoryServiceInterface;
use App\Transfers\Inventory\InventoryTransactionTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderInventoryConsumptionService implements OrderInventoryConsumptionServiceInterface
{
    public const REFERENCE_TYPE_ORDER_ITEM = 'order_item';

    public function __construct(
        protected InventoryServiceInterface $inventory,
        protected AddOnServiceInterface $addOns,
    ) {}

    public function consumeForAcceptedOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing([
            'items.recipe.lines.ingredient',
            'items.addOns.addOn',
            'items.product',
            'items.productVariant',
        ]);

        if (OrderInventoryConsumption::query()->where('order_id', $order->getKey())->exists()) {
            return;
        }

        $planned = $this->planConsumptionRows($order);

        if ($planned === []) {
            return;
        }

        $ingredientIds = collect($planned)
            ->pluck('ingredient_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($ingredientIds as $ingredientId) {
            DB::table('ingredients')->where('id', $ingredientId)->lockForUpdate()->first();
        }

        foreach ($planned as $row) {
            $transfer = new InventoryTransactionTransfer;
            $transfer->setIngredientId($row['ingredient_id']);
            $transfer->setTransactionType(InventoryTransactionType::SaleConsumption->value);
            $transfer->setQuantity($row['quantity']);
            $transfer->setMeasurementUnit($row['measurement_unit']);
            $transfer->setReferenceType(self::REFERENCE_TYPE_ORDER_ITEM);
            $transfer->setReferenceId($row['order_item_id']);
            $transfer->setNotes($row['notes']);
            $transfer->setCreatedBy($actor?->getKey());

            try {
                $transaction = $this->inventory->recordTransaction($transfer);
            } catch (ValidationException $exception) {
                $messages = $exception->errors();
                $quantityError = $messages['quantity'][0] ?? null;

                throw ValidationException::withMessages([
                    'inventory' => [
                        $quantityError !== null
                            ? sprintf(
                                'Insufficient stock for %s to accept this order. %s',
                                $row['ingredient_name'],
                                $quantityError,
                            )
                            : sprintf(
                                'Unable to consume inventory for %s when accepting this order.',
                                $row['ingredient_name'],
                            ),
                    ],
                ]);
            }

            OrderInventoryConsumption::query()->create([
                'order_id' => $order->getKey(),
                'order_item_id' => $row['order_item_id'],
                'source_type' => $row['source_type'] ?? 'base_recipe',
                'source_id' => $row['source_id'] ?? $row['recipe_line_id'] ?? null,
                'ingredient_id' => $row['ingredient_id'],
                'recipe_id' => $row['recipe_id'] ?? null,
                'recipe_line_id' => $row['recipe_line_id'] ?? null,
                'add_on_id' => $row['add_on_id'] ?? null,
                'add_on_recipe_line_id' => $row['add_on_recipe_line_id'] ?? null,
                'quantity' => $row['quantity'],
                'base_quantity' => $transaction->base_quantity,
                'measurement_unit' => $row['measurement_unit'],
                'base_measurement_unit' => $transaction->base_measurement_unit?->value
                    ?? $row['base_measurement_unit'],
                'inventory_transaction_id' => $transaction->getKey(),
            ]);
        }
    }

    public function reverseForCancelledOrder(Order $order, ?User $actor = null): void
    {
        if ($this->hasMaterialPreparationStarted($order)) {
            return;
        }

        $consumptions = OrderInventoryConsumption::query()
            ->where('order_id', $order->getKey())
            ->whereNull('reversed_at')
            ->orderBy('ingredient_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($consumptions->isEmpty()) {
            return;
        }

        foreach ($consumptions as $consumption) {
            if ($consumption->isReversed()) {
                continue;
            }

            $transfer = new InventoryTransactionTransfer;
            $transfer->setIngredientId((int) $consumption->ingredient_id);
            $transfer->setTransactionType(InventoryTransactionType::SaleReversal->value);
            $transfer->setQuantity((string) $consumption->quantity);
            $transfer->setMeasurementUnit(
                $consumption->measurement_unit instanceof \BackedEnum
                    ? $consumption->measurement_unit->value
                    : (string) $consumption->measurement_unit,
            );
            $transfer->setReferenceType(self::REFERENCE_TYPE_ORDER_ITEM);
            $transfer->setReferenceId((int) $consumption->order_item_id);
            $transfer->setNotes(sprintf(
                'Sale reversal for order #%s (restores original consumption qty).',
                $order->order_number,
            ));
            $transfer->setCreatedBy($actor?->getKey());

            $reversal = $this->inventory->recordTransaction($transfer);

            $updated = OrderInventoryConsumption::query()
                ->whereKey($consumption->getKey())
                ->whereNull('reversed_at')
                ->update([
                    'reversal_inventory_transaction_id' => $reversal->getKey(),
                    'reversed_at' => now(),
                ]);

            if ($updated === 0) {
                // Concurrent reversal already applied — compensating ledger remains balanced
                // only if we also reverse the duplicate; prefer leave as rare race under order lock.
                continue;
            }
        }
    }

    public function hasMaterialPreparationStarted(Order $order): bool
    {
        // Use timestamps so the decision survives cancelTicketsForOrder wiping
        // Preparing/Ready status before reverse runs in the same transaction.
        return $order->preparations()
            ->where(function ($query): void {
                $query->whereNotNull('preparing_at')
                    ->orWhereNotNull('ready_at');
            })
            ->exists();
    }

    /**
     * @return list<array{
     *     order_item_id: int,
     *     ingredient_id: int,
     *     ingredient_name: string,
     *     recipe_id: ?int,
     *     recipe_line_id: ?int,
     *     quantity: string,
     *     measurement_unit: string,
     *     base_measurement_unit: string,
     *     notes: string
     * }>
     */
    protected function planConsumptionRows(Order $order): array
    {
        $planned = [];

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $recipe = $this->resolveRecipeForItem($item);

            if ($recipe === null) {
                throw ValidationException::withMessages([
                    'inventory' => sprintf(
                        'Order item "%s" is missing a valid recipe and cannot consume inventory.',
                        $item->product_name,
                    ),
                ]);
            }

            $lines = $recipe->lines;

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'inventory' => sprintf(
                        'Recipe for "%s" has no ingredient lines; cannot accept this order.',
                        $item->product_name,
                    ),
                ]);
            }

            $qty = (int) $item->quantity;

            foreach ($lines as $line) {
                $ingredient = $line->ingredient;

                if ($ingredient === null) {
                    throw ValidationException::withMessages([
                        'inventory' => sprintf(
                            'Recipe line for "%s" references a missing ingredient.',
                            $item->product_name,
                        ),
                    ]);
                }

                $lineQty = (string) $line->quantity;
                $required = bcmul($lineQty, (string) $qty, 3);

                if (bccomp($required, '0', 3) <= 0) {
                    continue;
                }

                $unit = $line->measurement_unit instanceof \BackedEnum
                    ? $line->measurement_unit->value
                    : (string) $line->measurement_unit;
                $baseUnit = $line->base_measurement_unit instanceof \BackedEnum
                    ? $line->base_measurement_unit->value
                    : (string) ($line->base_measurement_unit ?? $ingredient->base_measurement_unit?->value);

                $planned[] = [
                    'order_item_id' => (int) $item->getKey(),
                    'ingredient_id' => (int) $ingredient->getKey(),
                    'ingredient_name' => (string) $ingredient->name,
                    'recipe_id' => (int) $recipe->getKey(),
                    'recipe_line_id' => (int) $line->getKey(),
                    'source_type' => 'base_recipe',
                    'source_id' => (int) $line->getKey(),
                    'add_on_id' => null,
                    'add_on_recipe_line_id' => null,
                    'quantity' => $required,
                    'measurement_unit' => $unit,
                    'base_measurement_unit' => $baseUnit,
                    'notes' => sprintf(
                        'Sale consumption for order #%s · %s × %d',
                        $order->order_number,
                        $item->product_name,
                        $qty,
                    ),
                ];
            }

            foreach ($item->addOns as $orderAddOn) {
                $addOn = $orderAddOn->addOn;
                if ($addOn === null) {
                    continue;
                }

                $product = $item->product
                    ?? ($item->product_id ? Product::query()->find($item->product_id) : null);
                $variant = $item->productVariant
                    ?? ($item->product_variant_id ? ProductVariant::query()->find($item->product_variant_id) : null);

                if ($product === null) {
                    continue;
                }

                $recipeLines = $this->addOns->resolveRecipeLinesForConsumption($product, $variant, $addOn);

                foreach ($recipeLines as $addOnLine) {
                    $ingredient = $addOnLine->ingredient;
                    if ($ingredient === null) {
                        continue;
                    }

                    $lineQty = (string) $addOnLine->quantity;
                    $perItem = bcmul($lineQty, (string) max(1, (int) $orderAddOn->quantity), 3);
                    $required = bcmul($perItem, (string) $qty, 3);

                    if (bccomp($required, '0', 3) <= 0) {
                        continue;
                    }

                    $unit = $addOnLine->measurement_unit instanceof \BackedEnum
                        ? $addOnLine->measurement_unit->value
                        : (string) $addOnLine->measurement_unit;
                    $baseUnit = $addOnLine->base_measurement_unit instanceof \BackedEnum
                        ? $addOnLine->base_measurement_unit->value
                        : (string) ($addOnLine->base_measurement_unit ?? $ingredient->base_measurement_unit?->value);

                    $planned[] = [
                        'order_item_id' => (int) $item->getKey(),
                        'ingredient_id' => (int) $ingredient->getKey(),
                        'ingredient_name' => (string) $ingredient->name,
                        'recipe_id' => null,
                        'recipe_line_id' => null,
                        'source_type' => 'add_on',
                        'source_id' => (int) $addOnLine->getKey(),
                        'add_on_id' => (int) $addOn->getKey(),
                        // Legacy column; product/variant recipe lines are tracked via source_id.
                        'add_on_recipe_line_id' => null,
                        'quantity' => $required,
                        'measurement_unit' => $unit,
                        'base_measurement_unit' => $baseUnit,
                        'notes' => sprintf(
                            'Add-on consumption for order #%s · %s + %s × %d',
                            $order->order_number,
                            $item->product_name,
                            $orderAddOn->name,
                            $qty,
                        ),
                    ];
                }
            }
        }

        return $planned;
    }

    protected function resolveRecipeForItem(OrderItem $item): ?Recipe
    {
        if ($item->recipe_id) {
            $recipe = Recipe::withTrashed()
                ->with(['lines.ingredient'])
                ->find($item->recipe_id);

            if ($recipe !== null) {
                return $recipe;
            }
        }

        if ($item->product_variant_id) {
            return Recipe::query()
                ->with(['lines.ingredient'])
                ->where('product_variant_id', $item->product_variant_id)
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        return null;
    }
}
