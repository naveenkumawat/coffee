<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 30)->default('referral');
            $table->foreignId('source_referral_id')->nullable()->constrained('customer_referrals')->nullOnDelete();
            $table->string('reward_type', 20);
            $table->string('status', 20)->default('available');
            $table->timestamp('earned_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('redeemed_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->unsignedInteger('quantity')->nullable();

            $table->string('coupon_code', 40)->nullable()->unique();
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->decimal('maximum_discount_amount', 12, 2)->nullable();
            $table->decimal('minimum_subtotal', 12, 2)->nullable();

            $table->timestamps();

            $table->unique('source_referral_id');
            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rewards');
    }
};
