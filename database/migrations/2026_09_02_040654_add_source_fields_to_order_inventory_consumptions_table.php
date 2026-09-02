<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_inventory_consumptions', function (Blueprint $table): void {
            $table->string('source_type', 32)->default('base_recipe')->after('order_item_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('add_on_id')->nullable()->after('recipe_line_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('add_on_recipe_line_id')->nullable()->after('add_on_id');
        });

        DB::table('order_inventory_consumptions')->update([
            'source_type' => 'base_recipe',
            'source_id' => DB::raw('recipe_line_id'),
        ]);

        // MySQL may use the composite unique as the supporting index for order_item_id FK.
        $indexes = collect(Schema::getIndexes('order_inventory_consumptions'))->pluck('name')->all();
        if (! in_array('order_inventory_consumptions_order_item_id_index', $indexes, true)
            && ! in_array('order_inventory_consumptions_order_item_id_foreign', $indexes, true)) {
            Schema::table('order_inventory_consumptions', function (Blueprint $table): void {
                $table->index('order_item_id', 'order_inventory_consumptions_order_item_id_index');
            });
        }

        Schema::table('order_inventory_consumptions', function (Blueprint $table): void {
            $table->dropUnique('order_inventory_consumptions_item_ingredient_uq');
            $table->unique(
                ['order_item_id', 'ingredient_id', 'source_type', 'source_id'],
                'oic_item_ingredient_source_uq',
            );
            $table->index(['source_type', 'source_id'], 'oic_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_inventory_consumptions', function (Blueprint $table): void {
            $table->dropUnique('oic_item_ingredient_source_uq');
            $table->dropIndex('oic_source_index');
            $table->unique(['order_item_id', 'ingredient_id'], 'order_inventory_consumptions_item_ingredient_uq');
            $table->dropConstrainedForeignId('add_on_id');
            $table->dropColumn(['source_type', 'source_id', 'add_on_recipe_line_id']);
        });
    }
};
