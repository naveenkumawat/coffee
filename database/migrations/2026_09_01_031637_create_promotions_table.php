<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->string('description', 1000)->nullable();
            $table->string('type', 20);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('minimum_subtotal', 12, 2)->nullable();
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('stackable')->default(false);
            $table->boolean('applies_to_all_products')->default(true);
            $table->boolean('applies_to_all_customers')->default(true);
            $table->boolean('first_order_only')->default(false);
            $table->string('fulfilment_scope', 20)->default('any');
            $table->json('weekdays')->nullable();
            $table->time('daily_starts_at')->nullable();
            $table->time('daily_ends_at')->nullable();
            $table->string('customer_message', 500)->nullable();
            $table->string('internal_note', 1000)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'type', 'starts_at', 'ends_at'], 'promotions_active_window_idx');
        });

        Schema::create('promotion_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_id']);
        });

        Schema::create('promotion_product_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_category_id'], 'promotion_category_unique');
        });

        Schema::create('promotion_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_user');
        Schema::dropIfExists('promotion_product_category');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
    }
};
