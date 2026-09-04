<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status', 32)->default('active')->index();
            $table->string('reward_type', 40)->index();
            $table->unsignedInteger('points_cost');
            $table->json('config')->nullable();
            $table->decimal('minimum_spend', 12, 2)->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('usage_limit_per_customer_period_days')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('customer_description', 500)->nullable();
            $table->string('internal_note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loyalty_reward_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loyalty_reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['loyalty_reward_id', 'product_id']);
        });

        Schema::create('loyalty_reward_product_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loyalty_reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->unique(['loyalty_reward_id', 'product_category_id'], 'loyalty_reward_category_unique');
        });

        Schema::create('loyalty_reward_add_on', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loyalty_reward_id')->constrained('loyalty_rewards')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->unique(['loyalty_reward_id', 'add_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_reward_add_on');
        Schema::dropIfExists('loyalty_reward_product_category');
        Schema::dropIfExists('loyalty_reward_product');
        Schema::dropIfExists('loyalty_rewards');
    }
};
