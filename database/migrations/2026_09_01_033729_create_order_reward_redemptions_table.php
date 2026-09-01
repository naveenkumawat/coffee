<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reward_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->string('reward_type', 20);
            $table->unsignedBigInteger('source_referral_id')->nullable();
            $table->string('description_snapshot');
            $table->decimal('benefit_amount', 12, 2);
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->decimal('preserved_taxable_amount', 12, 2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('coupon_code_snapshot', 40)->nullable();
            $table->string('discount_type_snapshot', 20)->nullable();
            $table->decimal('discount_value_snapshot', 12, 2)->nullable();
            $table->timestamps();

            $table->unique('customer_reward_id');
            $table->index(['order_id', 'reward_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reward_redemptions');
    }
};
