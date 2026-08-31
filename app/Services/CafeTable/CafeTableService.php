<?php

namespace App\Services\CafeTable;

use App\Models\CafeTable;
use App\Repositories\CafeTable\CafeTableRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CafeTableService implements CafeTableServiceInterface
{
    public function __construct(
        protected CafeTableRepositoryInterface $tables,
    ) {}

    public function store(array $data): CafeTable
    {
        return DB::transaction(fn (): CafeTable => $this->tables->create($this->prepareAttributes($data)));
    }

    public function update(CafeTable $table, array $data): CafeTable
    {
        return DB::transaction(
            fn (): CafeTable => $this->tables->update($table, $this->prepareAttributes($data, (int) $table->getKey())),
        );
    }

    public function delete(CafeTable $table): void
    {
        DB::transaction(function () use ($table): void {
            $table->forceFill(['is_active' => false])->save();
            $this->tables->delete($table);
        });
    }

    public function setActive(CafeTable $table, bool $isActive): CafeTable
    {
        return $this->tables->setActive($table, $isActive);
    }

    public function move(CafeTable $table, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Invalid reorder direction.',
            ]);
        }

        DB::transaction(fn () => $this->tables->move($table, $direction));
    }

    /**
     * @return list<array{id: int, code: string, name: string|null, label: string}>
     */
    public function publicActiveTables(): array
    {
        return $this->tables->activeOrdered()
            ->map(fn (CafeTable $table): array => [
                'id' => (int) $table->getKey(),
                'code' => (string) $table->code,
                'name' => filled($table->name) ? (string) $table->name : null,
                'label' => $table->displayLabel(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?int $ignoreId = null): array
    {
        $code = Str::upper(trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'A table code is required.',
            ]);
        }

        if ($this->tables->codeExists($code, $ignoreId)) {
            throw ValidationException::withMessages([
                'code' => 'This table code is already in use.',
            ]);
        }

        $name = filled($data['name'] ?? null) ? trim((string) $data['name']) : null;

        return [
            'code' => $code,
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
