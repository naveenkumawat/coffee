<?php

namespace App\Services\Inventory;

use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use App\Events\Inventory\InventoryRefillRequestCreated;
use App\Events\Inventory\InventoryRefillRequestStatusChanged;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Repositories\Inventory\InventoryRefillRequestRepositoryInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryRefillRequestService implements InventoryRefillRequestServiceInterface
{
    public function __construct(
        protected InventoryRefillRequestRepositoryInterface $requests,
    ) {}

    public function store(User $requestedBy, InventoryRefillRequestTransferInterface $data): InventoryRefillRequest
    {
        return DB::transaction(function () use ($requestedBy, $data): InventoryRefillRequest {
            $ingredient = $this->requests->findIngredient((int) $data->getIngredientId());

            if (! $ingredient->is_active) {
                throw ValidationException::withMessages([
                    'ingredient_id' => 'Only active ingredients can receive refill requests.',
                ]);
            }

            if ($this->requests->hasActiveRequestForIngredient($ingredient->getKey())) {
                throw ValidationException::withMessages([
                    'ingredient_id' => 'An active refill request already exists for this ingredient.',
                ]);
            }

            $measurementUnit = IngredientUnit::from((string) $data->getMeasurementUnit());
            $baseUnit = $ingredient->base_measurement_unit;

            if (! $baseUnit instanceof IngredientUnit || ! $measurementUnit->supportsBaseUnit($baseUnit)) {
                throw ValidationException::withMessages([
                    'measurement_unit' => 'Selected unit is not compatible with this ingredient.',
                ]);
            }

            $quantity = $this->normalizeDecimal((string) $data->getQuantity(), 3);
            $baseQuantity = $measurementUnit->normalize($quantity, 3);

            $request = $this->requests->create([
                'ingredient_id' => $ingredient->getKey(),
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'measurement_unit' => $measurementUnit->value,
                'base_measurement_unit' => $baseUnit->value,
                'notes' => $data->getNotes(),
                'requested_by' => $requestedBy->getKey(),
                'status' => InventoryRefillRequestStatus::Pending->value,
            ])->fresh(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']);

            InventoryRefillRequestCreated::dispatch($request);

            return $request;
        });
    }

    public function approve(InventoryRefillRequest $request, User $reviewer, ?string $reviewNotes = null): InventoryRefillRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $reviewNotes): InventoryRefillRequest {
            $this->ensurePending($request);
            $fromStatus = $request->status;

            $updated = $this->requests->update($request, [
                'status' => InventoryRefillRequestStatus::Approved->value,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? trim($reviewNotes) : null,
            ])->fresh(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']);

            InventoryRefillRequestStatusChanged::dispatch(
                $updated,
                $fromStatus,
                InventoryRefillRequestStatus::Approved,
            );

            return $updated;
        });
    }

    public function reject(InventoryRefillRequest $request, User $reviewer, ?string $reviewNotes = null): InventoryRefillRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $reviewNotes): InventoryRefillRequest {
            $this->ensurePending($request);
            $fromStatus = $request->status;

            $updated = $this->requests->update($request, [
                'status' => InventoryRefillRequestStatus::Rejected->value,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? trim($reviewNotes) : null,
            ])->fresh(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']);

            InventoryRefillRequestStatusChanged::dispatch(
                $updated,
                $fromStatus,
                InventoryRefillRequestStatus::Rejected,
            );

            return $updated;
        });
    }

    public function completeFromInventoryTransaction(InventoryTransaction $transaction): void
    {
        if ($transaction->reference_type !== 'inventory_refill_request' || ! $transaction->reference_id) {
            return;
        }

        if (! $transaction->transaction_type->isIncrease()) {
            throw ValidationException::withMessages([
                'inventory_refill_request_id' => 'Refill requests can only be completed by stock-increasing transactions.',
            ]);
        }

        $request = $this->requests->findById((int) $transaction->reference_id);

        if (! $request) {
            throw ValidationException::withMessages([
                'inventory_refill_request_id' => 'The selected refill request could not be found.',
            ]);
        }

        if ($request->ingredient_id !== $transaction->ingredient_id) {
            throw ValidationException::withMessages([
                'inventory_refill_request_id' => 'The selected refill request does not belong to this ingredient.',
            ]);
        }

        if ($request->status !== InventoryRefillRequestStatus::Approved) {
            throw ValidationException::withMessages([
                'inventory_refill_request_id' => 'Only approved refill requests can be completed from inventory intake.',
            ]);
        }

        $fromStatus = $request->status;

        $updated = $this->requests->update($request, [
            'status' => InventoryRefillRequestStatus::Completed->value,
        ])->fresh(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']);

        InventoryRefillRequestStatusChanged::dispatch(
            $updated,
            $fromStatus,
            InventoryRefillRequestStatus::Completed,
        );
    }

    public function approvedOptionsForIngredient(int $ingredientId): array
    {
        return $this->requests->approvedOptionsForIngredient($ingredientId);
    }

    protected function ensurePending(InventoryRefillRequest $request): void
    {
        if ($request->status !== InventoryRefillRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending refill requests can be reviewed.',
            ]);
        }
    }

    protected function normalizeDecimal(string $value, int $scale): string
    {
        return bcdiv($value, '1', $scale);
    }
}
