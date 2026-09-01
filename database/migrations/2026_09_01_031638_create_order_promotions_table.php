<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('code_snapshot', 40)->nullable();
            $table->string('discount_type_snapshot', 20);
            $table->decimal('discount_value_snapshot', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['promotion_id', 'order_id'], 'order_promotions_promotion_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_promotions');
    }
};
