<?php

namespace App\Repositories\CafeTable;

use App\Models\CafeTable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CafeTableRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator;

    /**
     * @return Collection<int, CafeTable>
     */
    public function activeOrdered(): Collection;

    public function findActiveById(int $id): ?CafeTable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CafeTable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CafeTable $table, array $attributes): CafeTable;

    public function delete(CafeTable $table): void;

    public function setActive(CafeTable $table, bool $isActive): CafeTable;

    public function codeExists(string $code, ?int $ignoreId = null): bool;

    public function move(CafeTable $table, string $direction): void;
}
