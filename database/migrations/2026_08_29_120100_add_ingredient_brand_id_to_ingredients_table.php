<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->foreignId('ingredient_brand_id')->nullable()->after('ingredient_category_id')->constrained('ingredient_brands');
        });

        $this->backfillIngredientBrands();
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ingredient_brand_id');
        });
    }

    protected function backfillIngredientBrands(): void
    {
        $brandNames = DB::table('ingredients')
            ->whereNotNull('brand')
            ->pluck('brand')
            ->map(fn ($brand): string => trim((string) $brand))
            ->filter()
            ->unique()
            ->values();

        $brandMap = $brandNames->mapWithKeys(function (string $name): array {
            $existing = DB::table('ingredient_brands')->where('name', $name)->first();

            if ($existing) {
                return [$name => $existing->id];
            }

            $id = DB::table('ingredient_brands')->insertGetId([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$name => $id];
        });

        DB::table('ingredients')
            ->select(['id', 'brand'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $ingredients) use ($brandMap): void {
                foreach ($ingredients as $ingredient) {
                    $name = trim((string) $ingredient->brand);

                    if ($name === '' || ! $brandMap->has($name)) {
                        continue;
                    }

                    DB::table('ingredients')
                        ->where('id', $ingredient->id)
                        ->update(['ingredient_brand_id' => $brandMap->get($name)]);
                }
            });
    }

    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug === '' ? 'brand' : $baseSlug;
        $suffix = 2;

        while (DB::table('ingredient_brands')->where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $baseSlug === '' ? 'brand' : $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }
};
