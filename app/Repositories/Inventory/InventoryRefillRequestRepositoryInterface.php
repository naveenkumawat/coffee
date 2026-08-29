<?php

namespace App\Repositories\Inventory;

use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\User;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRefillRequestRepositoryInterface
{
    public function paginateForAdministrator(InventoryRefillRequestFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator;

    public function paginateForBarista(User $user, InventoryRefillRequestFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): InventoryRefillRequest;

    public function update(InventoryRefillRequest $request, array $attributes): InventoryRefillRequest;

    public function findById(int $requestId): ?InventoryRefillRequest;

    public function findIngredient(int $ingredientId): Ingredient;

    public function hasActiveRequestForIngredient(int $ingredientId, ?int $ignoreRequestId = null): bool;

    public function requesterOptions(): array;

    public function approvedOptionsForIngredient(int $ingredientId): array;

    public function countPending(): int;
}
