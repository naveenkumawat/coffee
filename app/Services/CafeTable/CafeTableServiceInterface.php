<?php

namespace App\Services\CafeTable;

use App\Models\CafeTable;

interface CafeTableServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): CafeTable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CafeTable $table, array $data): CafeTable;

    public function delete(CafeTable $table): void;

    public function setActive(CafeTable $table, bool $isActive): CafeTable;

    public function move(CafeTable $table, string $direction): void;

    /**
     * @return list<array{id: int, code: string, name: string|null, label: string}>
     */
    public function publicActiveTables(): array;
}
