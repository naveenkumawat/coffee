<?php

namespace App\Repositories\CafeTable;

use App\Models\CafeTable;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CafeTableRepository extends AbstractRepository implements CafeTableRepositoryInterface
{
    public function __construct(
        protected CafeTable $model,
    ) {}

    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeOrdered(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    public function findActiveById(int $id): ?CafeTable
    {
        return $this->model->newQuery()
            ->whereKey($id)
            ->where('is_active', true)
            ->first();
    }

    public function create(array $attributes): CafeTable
    {
        /** @var CafeTable $table */
        $table = $this->persist($this->model->newInstance(), $attributes);

        return $table;
    }

    public function update(CafeTable $table, array $attributes): CafeTable
    {
        /** @var CafeTable $table */
        $table = $this->persist($table, $attributes);

        return $table;
    }

    public function delete(CafeTable $table): void
    {
        $this->remove($table);
    }

    public function setActive(CafeTable $table, bool $isActive): CafeTable
    {
        return $this->update($table, ['is_active' => $isActive]);
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function move(CafeTable $table, string $direction): void
    {
        $siblings = $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(
            fn (CafeTable $item): bool => (int) $item->getKey() === (int) $table->getKey(),
        );

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= $siblings->count()) {
            return;
        }

        /** @var CafeTable $current */
        $current = $siblings[$index];
        /** @var CafeTable $target */
        $target = $siblings[$swapWith];

        $currentOrder = $current->sort_order;
        $current->forceFill(['sort_order' => $target->sort_order])->save();
        $target->forceFill(['sort_order' => $currentOrder])->save();
    }
}
