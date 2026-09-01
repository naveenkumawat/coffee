<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('referral_free_drink_reward_id')
                ->nullable()
                ->after('promo_code')
                ->constrained('customer_rewards')
                ->nullOnDelete();
            $table->foreignId('referral_coupon_reward_id')
                ->nullable()
                ->after('referral_free_drink_reward_id')
                ->constrained('customer_rewards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('referral_free_drink_reward_id');
            $table->dropConstrainedForeignId('referral_coupon_reward_id');
        });
    }
};
