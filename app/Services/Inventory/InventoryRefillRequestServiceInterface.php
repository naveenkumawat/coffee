<?php

namespace App\Services\Inventory;

use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;

interface InventoryRefillRequestServiceInterface
{
    public function store(User $requestedBy, InventoryRefillRequestTransferInterface $data): InventoryRefillRequest;

    public function approve(InventoryRefillRequest $request, User $reviewer, ?string $reviewNotes = null): InventoryRefillRequest;

    public function reject(InventoryRefillRequest $request, User $reviewer, ?string $reviewNotes = null): InventoryRefillRequest;

    public function completeFromInventoryTransaction(InventoryTransaction $transaction): void;

    public function approvedOptionsForIngredient(int $ingredientId): array;
}
