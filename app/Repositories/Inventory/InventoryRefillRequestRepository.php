<?php

namespace App\Repositories\Inventory;

use App\Enums\InventoryRefillRequestStatus;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Transfers\Inventory\InventoryRefillRequestFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryRefillRequestRepository extends AbstractRepository implements InventoryRefillRequestRepositoryInterface
{
    public function __construct(
        protected InventoryRefillRequest $model,
        protected Ingredient $ingredientModel,
    ) {}

    public function paginateForAdministrator(InventoryRefillRequestFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForBarista(User $user, InventoryRefillRequestFilterTransferInterface $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->where('requested_by', $user->getKey())
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): InventoryRefillRequest
    {
        /** @var InventoryRefillRequest $request */
        $request = $this->persist($this->model->newInstance(), $attributes);

        return $request;
    }

    public function update(InventoryRefillRequest $request, array $attributes): InventoryRefillRequest
    {
        /** @var InventoryRefillRequest $request */
        $request = $this->persist($request, $attributes);

        return $request;
    }

    public function findById(int $requestId): ?InventoryRefillRequest
    {
        return $this->model->newQuery()->find($requestId);
    }

    public function findIngredient(int $ingredientId): Ingredient
    {
        return $this->ingredientModel->newQuery()
            ->with(['brand', 'category'])
            ->findOrFail($ingredientId);
    }

    public function hasActiveRequestForIngredient(int $ingredientId, ?int $ignoreRequestId = null): bool
    {
        return $this->model->newQuery()
            ->where('ingredient_id', $ingredientId)
            ->whereIn('status', [
                InventoryRefillRequestStatus::Pending->value,
                InventoryRefillRequestStatus::Approved->value,
            ])
            ->when($ignoreRequestId, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->exists();
    }

    public function requesterOptions(): array
    {
        return User::query()
            ->whereIn('id', function ($query): void {
                $query->select('requested_by')
                    ->from('inventory_refill_requests');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function approvedOptionsForIngredient(int $ingredientId): array
    {
        return $this->model->newQuery()
            ->with('requestedBy')
            ->where('ingredient_id', $ingredientId)
            ->where('status', InventoryRefillRequestStatus::Approved->value)
            ->orderBy('created_at')
            ->get()
            ->mapWithKeys(function (InventoryRefillRequest $request): array {
                return [
                    $request->getKey() => sprintf(
                        '#%d • %s %s • %s',
                        $request->getKey(),
                        number_format((float) $request->quantity, 3),
                        $request->measurement_unit->value,
                        $request->requestedBy?->name ?? 'Barista'
                    ),
                ];
            })
            ->all();
    }

    public function countPending(): int
    {
        return $this->model->newQuery()
            ->where('status', InventoryRefillRequestStatus::Pending->value)
            ->count();
    }

    protected function baseQuery(InventoryRefillRequestFilterTransferInterface $filters)
    {
        return $this->model->newQuery()
            ->with(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy'])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('notes', 'like', "%{$search}%")
                        ->orWhere('review_notes', 'like', "%{$search}%")
                        ->orWhereHas('ingredient', fn ($ingredientQuery) => $ingredientQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('requestedBy', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getIngredientId(), fn ($query) => $query->where('ingredient_id', $filters->getIngredientId()))
            ->when($filters->getStatus(), fn ($query) => $query->where('status', $filters->getStatus()))
            ->when($filters->getRequestedBy(), fn ($query) => $query->where('requested_by', $filters->getRequestedBy()));
    }
}
