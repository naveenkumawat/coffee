<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_inventory_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('base_quantity', 12, 3);
            $table->string('measurement_unit', 20);
            $table->string('base_measurement_unit', 20);
            $table->unsignedBigInteger('inventory_transaction_id');
            $table->unsignedBigInteger('reversal_inventory_transaction_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->foreign('inventory_transaction_id', 'oic_inventory_txn_fk')
                ->references('id')
                ->on('inventory_transactions')
                ->restrictOnDelete();
            $table->foreign('reversal_inventory_transaction_id', 'oic_reversal_txn_fk')
                ->references('id')
                ->on('inventory_transactions')
                ->nullOnDelete();

            $table->unique(['order_item_id', 'ingredient_id'], 'order_inventory_consumptions_item_ingredient_uq');
            $table->index(['order_id', 'ingredient_id']);
            $table->index(['reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_inventory_consumptions');
    }
};
