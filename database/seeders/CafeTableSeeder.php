<?php

namespace Database\Seeders;

use App\Models\CafeTable;
use Illuminate\Database\Seeder;

class CafeTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $tables = [
            ['code' => 'T1', 'name' => null, 'sort_order' => 10, 'is_active' => true],
            ['code' => 'T2', 'name' => null, 'sort_order' => 20, 'is_active' => true],
            ['code' => 'T3', 'name' => null, 'sort_order' => 30, 'is_active' => true],
            ['code' => 'T4', 'name' => null, 'sort_order' => 40, 'is_active' => true],
            ['code' => 'T5', 'name' => null, 'sort_order' => 50, 'is_active' => true],
            ['code' => 'T6', 'name' => null, 'sort_order' => 60, 'is_active' => true],
            ['code' => 'T7', 'name' => null, 'sort_order' => 70, 'is_active' => true],
            ['code' => 'T8', 'name' => null, 'sort_order' => 80, 'is_active' => true],
            ['code' => 'OUTDOOR 1', 'name' => 'Patio A', 'sort_order' => 90, 'is_active' => true],
            ['code' => 'OUTDOOR 2', 'name' => 'Patio B', 'sort_order' => 100, 'is_active' => true],
            ['code' => 'T9', 'name' => 'Storage / unused', 'sort_order' => 999, 'is_active' => false],
        ];

        foreach ($tables as $definition) {
            $table = CafeTable::query()->withTrashed()->firstOrNew([
                'code' => $definition['code'],
            ]);

            $table->fill([
                'name' => $definition['name'],
                'sort_order' => $definition['sort_order'],
                'is_active' => $definition['is_active'],
            ]);
            $table->deleted_at = null;
            $table->save();
        }
    }
}
